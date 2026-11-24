import test, { beforeEach, afterEach, mock } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, disposePinia } from 'pinia';
import { useRolesStore } from '../src/store/roles.ts';
import useApi from '../src/use/api.ts';

const items = [
  {
    code: 'ROLE_TECHNICIAN',
    label: 'Название с сервера',
    description: 'Описание с сервера',
    assignable: true,
  },
  {
    code: 'ROLE_SUPER_ADMIN',
    label: 'Системная роль с сервера',
    description: 'Не назначается',
    assignable: false,
  },
];
let pinia, roles;

beforeEach(() => {
  pinia = createPinia();
  roles = useRolesStore(pinia);
  useApi().setToken('fixture-token');
});

afterEach(() => {
  disposePinia(pinia);
  useApi().setToken(null);
  mock.restoreAll();
});

test('role catalogue is loaded once and labels come from the server', async () => {
  const calls = [];

  mock.method(globalThis, 'fetch', async (url, init) => {
    calls.push([url, init.headers.get('Authorization')]);

    return Response.json({ items });
  });

  await Promise.all([roles.load(), roles.load()]);
  await roles.load();

  assert.deepEqual(
    [calls, roles.loaded, roles.items, roles.labels.ROLE_TECHNICIAN, roles.colors.ROLE_SUPER_ADMIN],
    [[['/api/roles', 'Bearer fixture-token']], true, items, 'Название с сервера', 'purple'],
  );
});

test('failed catalogue request can be retried', async () => {
  let requests = 0;

  mock.method(globalThis, 'fetch', async () => {
    requests++;

    return requests === 1
      ? Response.json({ message: 'Unavailable' }, { status: 503 })
      : Response.json({ items });
  });

  await roles.load().catch(() => {});
  await roles.load();

  assert.deepEqual([requests, roles.loaded, roles.items.length], [2, true, 2]);
});

test('a late catalogue response cannot restore metadata after session reset', async () => {
  let resolve;

  mock.method(
    globalThis,
    'fetch',
    () =>
      new Promise((done) => {
        resolve = done;
      }),
  );
  const pending = roles.load();

  roles.reset();
  resolve(Response.json({ items }));
  await pending;

  assert.deepEqual([roles.loaded, roles.items, roles.labels], [false, [], {}]);
});
