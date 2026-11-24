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

test('technician can open meter creation and reading forms while verification is guarded', async () => {
  await openPrivate();
  auth.user = { ...user, roles: ['ROLE_TECHNICIAN'] };
  router.addRoute({ path: '/meters', component: {}, meta: { requiresAuth: true } });
  router.addRoute({
    path: '/meters/new',
    component: {},
    meta: { requiresAuth: true, meterInstall: true },
  });
  router.addRoute({
    path: '/meters/1/readings/new',
    component: {},
    meta: { requiresAuth: true, meterReadingEdit: true },
  });
  router.addRoute({
    path: '/meters/1/verifications/new',
    component: {},
    meta: { requiresAuth: true, meterEdit: true },
  });

  const paths = [];

  for (const path of ['/meters/new', '/meters/1/readings/new', '/meters/1/verifications/new']) {
    await router.push(path);
    paths.push(router.currentRoute.value.path);
  }

  assert.deepEqual(paths, ['/meters/new', '/meters/1/readings/new', '/meters']);
});

test('notification settings route is open to director and guarded for lawyer', async () => {
  await openPrivate();
  router.addRoute({ path: '/', component: {}, meta: { requiresAuth: true } });
  router.addRoute({
    path: '/settings/notification-types',
    component: {},
    meta: { requiresAuth: true, notificationSettings: true },
  });
  auth.user = { ...user, roles: ['ROLE_DIRECTOR'] };
  await router.push('/settings/notification-types');
  const directorPath = router.currentRoute.value.path;

  await router.push('/');
  auth.user = { ...user, roles: ['ROLE_LAWYER'] };
  await router.push('/settings/notification-types');
  assert.deepEqual(
    [directorPath, router.currentRoute.value.path],
    ['/settings/notification-types', '/'],
  );
});
