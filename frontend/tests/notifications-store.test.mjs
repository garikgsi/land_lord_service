import test, { beforeEach, afterEach, mock } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, disposePinia } from 'pinia';
import useApi from '../src/use/api.ts';
import { useNotificationsStore } from '../src/store/notifications.ts';

let pinia, notifications;

beforeEach(() => {
  pinia = createPinia();
  notifications = useNotificationsStore(pinia);
  useApi().setToken('notification-fixture');
});

afterEach(() => {
  disposePinia(pinia);
  useApi().setToken(null);
  mock.restoreAll();
});

test('unread count is shared and the compact badge keeps the complete accessible count', async () => {
  mock.method(globalThis, 'fetch', async () => Response.json({ unreadCount: 150 }));
  await notifications.refresh();

  assert.deepEqual(
    [
      useNotificationsStore(pinia) === notifications,
      notifications.unreadCount,
      notifications.countLabel,
      notifications.accessibleLabel,
    ],
    [true, 150, '99+', 'Уведомления: 150 непрочитанных'],
  );
});

test('reading a notification refreshes the shared counter', async () => {
  const calls = [];

  mock.method(globalThis, 'fetch', async (url, init) => {
    calls.push([url, init.method ?? 'GET']);

    return Response.json(
      url.endsWith('/read') ? { id: 1, readAt: '2026-11-21T00:00:00Z' } : { unreadCount: 2 },
    );
  });
  const record = await notifications.markRead(1);

  assert.deepEqual(
    [record.id, notifications.unreadCount, calls],
    [
      1,
      2,
      [
        ['/api/notifications/1/read', 'PUT'],
        ['/api/notifications/unread-count', 'GET'],
      ],
    ],
  );
});

test('reset ignores a late result from the previous account', async () => {
  let resolve;

  mock.method(
    globalThis,
    'fetch',
    () =>
      new Promise((done) => {
        resolve = done;
      }),
  );
  const pending = notifications.refresh();

  notifications.reset();
  resolve(Response.json({ unreadCount: 8 }));
  await pending;

  assert.equal(notifications.unreadCount, 0);
});

test('read all uses the server count and does not expose other account state', async () => {
  mock.method(globalThis, 'fetch', async () => Response.json({ unreadCount: 0 }));
  await notifications.markAllRead();

  assert.deepEqual([notifications.unreadCount, notifications.countLabel], [0, '0']);
});
