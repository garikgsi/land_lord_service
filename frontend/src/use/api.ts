import useEvent from './event.ts';
import type { ApiClient, ApiErrors } from '../types/use/Api';

export class ApiError extends Error {
  readonly status: number;
  readonly errors: ApiErrors;

  constructor(status: number, message: string, errors: ApiErrors = {}) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
  }
}

const apiBase = (import.meta.env?.VITE_API_BASE_URL ?? '').replace(/\/$/, '');
const events = useEvent();
let accessToken: string | null = null;

function setToken(token: string | null) {
  accessToken = token;
}

/** Raw response, including binary downloads. Public endpoints use authenticated=false. */
async function request(
  path: string,
  init: RequestInit = {},
  authenticated = true,
): Promise<Response> {
  const requestToken = authenticated ? accessToken : null;
  const headers = new Headers(init.headers);

  headers.set('X-Requested-With', 'XMLHttpRequest');

  if (!headers.has('Accept')) headers.set('Accept', 'application/json');

  if (init.body && !(init.body instanceof FormData) && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  if (requestToken) headers.set('Authorization', `Bearer ${requestToken}`);

  let response: Response;

  try {
    response = await fetch(`${apiBase}${path}`, {
      ...init,
      headers,
      cache: 'no-store',
      signal: init.signal ?? AbortSignal.timeout(20000),
    });
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') throw error;

    throw new ApiError(0, 'Нет связи с сервером. Проверьте подключение и повторите.');
  }

  if (!response.ok) {
    const body = await response.json().catch(() => null);

    // A late response from an old session must not revoke a newer login.
    if (response.status === 401 && authenticated && requestToken === accessToken) {
      setToken(null);
      events.emit('session-expired');
    }

    throw new ApiError(
      response.status,
      body?.message ?? 'Не удалось выполнить операцию.',
      body?.errors ?? {},
    );
  }

  return response;
}

/** JSON response; 204 resolves to undefined. FormData is sent without a manual boundary. */
async function api<T>(path: string, init: RequestInit = {}, authenticated = true): Promise<T> {
  const response = await request(path, init, authenticated);

  return response.status === 204 ? (undefined as T) : (response.json() as Promise<T>);
}

function errorText(error: unknown): string {
  if (error instanceof ApiError) return [error.message, ...Object.values(error.errors)].join(' ');

  return 'Не удалось выполнить операцию. Повторите позже.';
}

const client = { api, request, setToken, errorText };

/** Shared HTTP adapter. Calling this function does not create a separate session. */
export default function useApi(): ApiClient {
  return client;
}
