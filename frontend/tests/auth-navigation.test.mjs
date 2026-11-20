import { beforeEach, afterEach, test, mock } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, disposePinia } from 'pinia';
import { createRouter, createMemoryHistory } from 'vue-router';
import { configureAuthentication } from '../src/router/authentication.ts';
import { useAuthStore } from '../src/store/auth.ts';
import useApi from '../src/use/api.ts';

const originalStorage = Object.getOwnPropertyDescriptor(globalThis, 'sessionStorage');
const user = { id: 1, email: 'user@test.example', roles: ['ROLE_ADMIN'] };
let pinia, auth, router, dispose;

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
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/login', component: {} },
      { path: '/private', component: {}, meta: { requiresAuth: true } },
    ],
  });
  dispose = configureAuthentication(router, pinia);
  mock.method(globalThis, 'fetch', async (url) =>
    Response.json(
      url === '/api/auth/login'
        ? { accessToken: 'session-token', expiresAt: '2099-01-01T00:00:00Z', user }
        : user,
    ),
  );
});
afterEach(() => {
  dispose();
  disposePinia(pinia);
  useApi().setToken(null);
  mock.restoreAll();

  if (originalStorage) Object.defineProperty(globalThis, 'sessionStorage', originalStorage);
  else delete globalThis.sessionStorage;
});

async function openPrivate() {
  await auth.login(user.email, 'test-password');
  await router.push('/private?filter=active');
}

const navigationSettled = () => new Promise(setImmediate);

test('clearing the Pinia session redirects an open private page to login', async () => {
  await openPrivate();
  auth.clear();
  await navigationSettled();
  assert.equal(router.currentRoute.value.path, '/login');
});

test('401 with a missing bearer redirects the open page and removes the profile', async () => {
  await openPrivate();
  useApi().setToken(null);
  mock.method(globalThis, 'fetch', async () =>
    Response.json({ message: 'Login required' }, { status: 401 }),
  );
  await useApi()
    .api('/api/admin/users')
    .catch(() => {});
  await navigationSettled();
  assert.deepEqual([router.currentRoute.value.path, auth.user], ['/login', null]);
});

test('401 for an expired bearer redirects instead of leaving an error inside a private page', async () => {
  await openPrivate();
  mock.method(globalThis, 'fetch', async () =>
    Response.json({ message: 'Expired' }, { status: 401 }),
  );
  await useApi()
    .api('/api/admin/users')
    .catch(() => {});
  await navigationSettled();
  assert.deepEqual(
    [router.currentRoute.value.path, router.currentRoute.value.query.expired],
    ['/login', '1'],
  );
});

test('a rejected public login stays on the login form', async () => {
  await router.push('/login');
  mock.method(globalThis, 'fetch', async () =>
    Response.json({ message: 'Bad credentials' }, { status: 401 }),
  );
  await auth.login(user.email, 'wrong-password').catch(() => {});
  await navigationSettled();
  assert.deepEqual(
    [router.currentRoute.value.path, router.currentRoute.value.query],
    ['/login', {}],
  );
});
