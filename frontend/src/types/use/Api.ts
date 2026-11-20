export type ApiErrors = Record<string, string>;
export interface MessageResponse {
  message: string;
}

export interface ApiClient {
  api<T>(path: string, init?: RequestInit, authenticated?: boolean): Promise<T>;
  request(path: string, init?: RequestInit, authenticated?: boolean): Promise<Response>;
  setToken(token: string | null): void;
  errorText(error: unknown): string;
}
