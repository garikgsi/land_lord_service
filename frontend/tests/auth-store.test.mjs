import test, { beforeEach, afterEach, mock } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, disposePinia } from 'pinia';
import { useAuthStore } from '../src/store/auth.ts';
import useApi from '../src/use/api.ts';
import useEvent from '../src/use/event.ts';

const storageKey = 'landlordservice.session';
const user = {
  id: 1,
  email: 'admin@example.com',
  firstName: 'Анна',
  lastName: null,
  roles: ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
  phone: null,
  position: null,
  avatarUrl: null,
  blocked: false,
  active: true,
  createdAt: '2026-11-19',
};
const session = { accessToken: 'test-session', expiresAt: '2099-01-01T00:00:00Z', user };
const originalStorage = Object.getOwnPropertyDescriptor(globalThis, 'sessionStorage');
let pinia, auth;

beforeEach(() => {
  const data = new Map();

  Object.defineProperty(globalThis, 'sessionStorage', {
    configurable: true,
    value: {
      getItem: (key) => data.get(key) ?? null,
      setItem: (key, value) => data.set(key, value),
      removeItem: (key) => data.delete(key),
    },
  });
  pinia = createPinia();
  auth = useAuthStore(pinia);
});
afterEach(() => {
  disposePinia(pinia);
  useApi().setToken(null);
  mock.restoreAll();

  if (originalStorage) Object.defineProperty(globalThis, 'sessionStorage', originalStorage);
  else delete globalThis.sessionStorage;
});

test('login shares user and roles through Pinia without exposing the bearer in its state', async () => {
  let payload, bearer;

  mock.method(globalThis, 'fetch', async (url, init) => {
    if (url === '/api/auth/login') {
      payload = JSON.parse(init.body);

      return Response.json(session);
    }

    bearer = init.headers.get('Authorization');

    return Response.json({});
  });
  await auth.login(' ADMIN@EXAMPLE.COM ', 'test-password');
  await useApi().api('/api/private');
  assert.deepEqual(
    [
      useAuthStore(pinia) === auth,
      auth.user.email,
      auth.isAdmin,
      auth.isSuperAdmin,
      payload.email,
      bearer,
      'accessToken' in auth.$state,
    ],
    [true, user.email, true, true, user.email, 'Bearer test-session', false],
  );
});

test('session restoration checks the server once even with concurrent router guards', async () => {
  sessionStorage.setItem(storageKey, JSON.stringify(session));
  let count = 0;

  mock.method(globalThis, 'fetch', async () => {
    count++;

    return Response.json(user);
  });
  await Promise.all([auth.initialize(), auth.initialize()]);
  assert.deepEqual([count, auth.user.email, auth.isSuperAdmin], [1, user.email, true]);
});

test('invalid expiration is rejected before any authenticated request', async () => {
  sessionStorage.setItem(storageKey, JSON.stringify({ accessToken: 'old', expiresAt: 'invalid' }));
  let requests = 0;

  mock.method(globalThis, 'fetch', async () => {
    requests++;

    return Response.json(user);
  });
  await auth.initialize();
  assert.deepEqual([requests, auth.user, sessionStorage.getItem(storageKey)], [0, null, null]);
});

test('401 clears the Pinia profile, derived rights and persisted session', async () => {
  mock.method(globalThis, 'fetch', async (url) =>
    url === '/api/auth/login'
      ? Response.json(session)
      : Response.json({ message: 'Expired' }, { status: 401 }),
  );
  await auth.login(user.email, 'test-password');
  await useApi()
    .api('/api/private')
    .catch(() => {});
  assert.deepEqual(
    [auth.user, auth.isAdmin, auth.isSuperAdmin, sessionStorage.getItem(storageKey)],
    [null, false, false, null],
  );
});

test('logout clears the local session even when the server is unavailable', async () => {
  mock.method(globalThis, 'fetch', async (url) => {
    if (url === '/api/auth/login') return Response.json(session);

    throw new TypeError('offline');
  });
  await auth.login(user.email, 'test-password');
  await auth.logout().catch(() => {});
  assert.deepEqual([auth.user, sessionStorage.getItem(storageKey)], [null, null]);
});

test('a late restoration response cannot restore the account after session expiration', async () => {
  sessionStorage.setItem(storageKey, JSON.stringify(session));
  let resolve;

  mock.method(
    globalThis,
    'fetch',
    () =>
      new Promise((done) => {
        resolve = done;
      }),
  );
  const restoring = auth.initialize();

  useEvent().emit('session-expired');
  resolve(Response.json(user));
  await restoring;
  assert.deepEqual([auth.user, sessionStorage.getItem(storageKey)], [null, null]);
});

test('disposing a store removes its subscription to application events', async () => {
  mock.method(globalThis, 'fetch', async () => Response.json(session));
  await auth.login(user.email, 'test-password');
  auth.$dispose();
  useEvent().emit('session-expired');
  assert.equal(auth.user.email, user.email);
});
