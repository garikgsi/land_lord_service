import test, { afterEach, mock } from 'node:test';
import assert from 'node:assert/strict';
import useApi from '../src/use/api.ts';

const client = useApi();

afterEach(() => {
  client.setToken(null);
  mock.restoreAll();
});

test('small catalogues use session cache and callers cannot mutate its records', async () => {
  client.setToken('cache-test');
  const fetch = mock.method(globalThis, 'fetch', async () =>
    Response.json({ items: [{ id: 1, name: 'One' }], total: 1, page: 1, pageSize: 20 }),
  );
  const first = await client.api('/api/objects');

  first.items[0].name = 'Local edit';
  const second = await client.api('/api/objects');

  assert.deepEqual([fetch.mock.callCount(), second.items[0].name], [1, 'One']);
});

test('paginated datasets are fetched again', async () => {
  client.setToken('cache-test');
  const fetch = mock.method(globalThis, 'fetch', async () =>
    Response.json({ items: [{ id: 1 }], total: 100, page: 1, pageSize: 20 }),
  );

  await client.api('/api/objects');
  await client.api('/api/objects');
  assert.equal(fetch.mock.callCount(), 2);
});

test('mutation and session replacement invalidate cached data', async () => {
  client.setToken('cache-test');
  const fetch = mock.method(globalThis, 'fetch', async () =>
    Response.json({ id: 1, name: 'Object' }),
  );

  await client.api('/api/objects/1');
  await client.api('/api/objects/1');
  await client.api('/api/objects/1', { method: 'PUT', body: '{}' });
  await client.api('/api/objects/1');
  client.setToken('next-session');
  await client.api('/api/objects/1');
  assert.equal(fetch.mock.callCount(), 4);
});

test('late responses cannot populate a new session cache', async () => {
  client.setToken('old-session');
  let finish;
  let started;
  const ready = new Promise((resolve) => {
    started = resolve;
  });
  const fetch = mock.method(globalThis, 'fetch', async () => {
    started();

    return await new Promise((resolve) => {
      finish = resolve;
    });
  });
  const old = client.api('/api/objects/1');

  await ready;
  client.setToken('new-session');
  finish(Response.json({ id: 1, name: 'Old' }));
  await old;
  fetch.mock.mockImplementation(async () => Response.json({ id: 1, name: 'New' }));
  const current = await client.api('/api/objects/1');

  assert.deepEqual([fetch.mock.callCount(), current.name], [2, 'New']);
});
