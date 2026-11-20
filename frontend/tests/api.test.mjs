import { afterEach, mock, test } from 'node:test';
import assert from 'node:assert/strict';
import useApi, { ApiError } from '../src/use/api.ts';
import useEvent from '../src/use/event.ts';

const client = useApi();
const json = (body, status = 200) =>
  new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });

afterEach(() => {
  mock.restoreAll();
  client.setToken(null);
});

test('token configured by one script is attached to requests from another script', async () => {
  let observed;

  client.setToken('shared-token');
  mock.method(globalThis, 'fetch', async (url, init) => {
    observed = [
      url,
      init.headers.get('Authorization'),
      init.headers.get('X-Requested-With'),
      init.headers.get('Accept'),
      init.cache,
      init.signal instanceof AbortSignal,
    ];

    return json({ id: 1 });
  });
  const data = await useApi().api('/api/auth/me');

  assert.deepEqual(
    [observed, data],
    [
      [
        '/api/auth/me',
        'Bearer shared-token',
        'XMLHttpRequest',
        'application/json',
        'no-store',
        true,
      ],
      { id: 1 },
    ],
  );
});

test('public login failures do not attach Bearer or expire an existing session', async (t) => {
  let expired = 0;
  let authorization;
  const handler = () => expired++;

  useEvent().on('session-expired', handler);
  t.after(() => useEvent().off('session-expired', handler));
  client.setToken('valid-token');
  mock.method(globalThis, 'fetch', async (_url, init) => {
    authorization = init.headers.get('Authorization');

    return json({ message: 'Bad credentials' }, 401);
  });
  const error = await client
    .api('/api/auth/login', { method: 'POST', body: '{}' }, false)
    .catch((error) => error);

  assert.deepEqual(
    [error instanceof ApiError, error.status, authorization, expired],
    [true, 401, null, 0],
  );
});

test('FormData reaches fetch unchanged without a manually specified multipart boundary', async () => {
  const body = new FormData();

  body.append('avatar', new Blob(['avatar'], { type: 'image/png' }), 'avatar.png');
  let observed;

  mock.method(globalThis, 'fetch', async (_url, init) => {
    observed = [init.body === body, init.headers.get('Content-Type')];

    return json({ saved: true });
  });
  await client.api('/api/admin/users/1/avatar', { method: 'POST', body });
  assert.deepEqual(observed, [true, null]);
});

test('raw requests preserve binary response and an explicit Accept header', async () => {
  let accept;

  mock.method(globalThis, 'fetch', async (_url, init) => {
    accept = init.headers.get('Accept');

    return new Response(new Uint8Array([1, 2, 3]), { headers: { 'Content-Type': 'image/png' } });
  });
  const response = await client.request('/api/users/1/avatar', {
    headers: { Accept: 'image/png' },
  });

  assert.deepEqual(
    [accept, [...new Uint8Array(await response.arrayBuffer())]],
    ['image/png', [1, 2, 3]],
  );
});

test('caller-specified content type is retained', async () => {
  let observed;

  mock.method(globalThis, 'fetch', async (_url, init) => {
    observed = [init.headers.get('Content-Type'), init.body];

    return json({});
  });
  await client.api('/api/example', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'field=value',
  });
  assert.deepEqual(observed, ['application/x-www-form-urlencoded', 'field=value']);
});

test('JSON requests get the JSON content type', async () => {
  let type;

  mock.method(globalThis, 'fetch', async (_url, init) => {
    type = init.headers.get('Content-Type');

    return json({});
  });
  await client.api(
    '/api/auth/login',
    { method: 'POST', body: JSON.stringify({ email: 'test@example.com' }) },
    false,
  );
  assert.equal(type, 'application/json');
});

test('validation errors keep their status and field messages', async () => {
  mock.method(globalThis, 'fetch', async () =>
    json({ message: 'Проверьте поля.', errors: { email: 'Некорректная почта.' } }, 422),
  );
  const error = await client
    .api('/api/admin/users', { method: 'POST', body: '{}' })
    .catch((error) => error);

  assert.deepEqual(
    [error instanceof ApiError, error.status, error.errors, client.errorText(error)],
    [true, 422, { email: 'Некорректная почта.' }, 'Проверьте поля. Некорректная почта.'],
  );
});

test('204 responses do not attempt to parse a JSON body', async () => {
  mock.method(globalThis, 'fetch', async () => new Response(null, { status: 204 }));
  assert.equal(await client.api('/api/auth/logout', { method: 'POST' }), undefined);
});

test('401 for the current token emits expiration once and removes the token', async (t) => {
  let expired = 0;
  const handler = () => expired++;

  useEvent().on('session-expired', handler);
  t.after(() => useEvent().off('session-expired', handler));
  client.setToken('expired-token');
  let authorization;

  mock.method(globalThis, 'fetch', async (_url, init) => {
    if (_url === '/api/probe') {
      authorization = init.headers.get('Authorization');

      return json({});
    }

    return json({ message: 'Expired' }, 401);
  });
  const errors = await Promise.all(
    [client.api('/api/private'), client.api('/api/private')].map((promise) =>
      promise.catch((error) => error.status),
    ),
  );

  await client.api('/api/probe');
  assert.deepEqual([errors, expired, authorization], [[401, 401], 1, null]);
});

test('an authenticated request without a bearer still expires the session on 401', async (t) => {
  let expired = 0;
  const handler = () => expired++;

  useEvent().on('session-expired', handler);
  t.after(() => useEvent().off('session-expired', handler));
  client.setToken(null);
  mock.method(globalThis, 'fetch', async () => json({ message: 'Login required' }, 401));
  await client.api('/api/admin/users').catch(() => {});
  assert.equal(expired, 1);
});

test('a late 401 from an earlier session does not clear a newer token', async (t) => {
  let resolveOld;
  let expired = 0;
  let authorization;
  const handler = () => expired++;

  useEvent().on('session-expired', handler);
  t.after(() => useEvent().off('session-expired', handler));
  mock.method(globalThis, 'fetch', (_url, init) => {
    if (_url === '/api/old')
      return new Promise((resolve) => {
        resolveOld = resolve;
      });

    authorization = init.headers.get('Authorization');

    return Promise.resolve(json({}));
  });
  client.setToken('old-token');
  const previous = client.api('/api/old').catch((error) => error.status);

  client.setToken('new-token');
  resolveOld(json({ message: 'Expired' }, 401));
  await previous;
  await client.api('/api/new');
  assert.deepEqual([expired, authorization], [0, 'Bearer new-token']);
});

test('explicit request cancellation keeps the AbortError and caller signal', async () => {
  const controller = new AbortController();

  controller.abort();
  const cancellation = new DOMException('Cancelled', 'AbortError');
  let signal;

  mock.method(globalThis, 'fetch', async (_url, init) => {
    signal = init.signal;
    throw cancellation;
  });
  const error = await client
    .request('/api/private', { signal: controller.signal })
    .catch((error) => error);

  assert.deepEqual([error === cancellation, signal === controller.signal], [true, true]);
});

test('network failures are reported as ApiError with status zero', async () => {
  mock.method(globalThis, 'fetch', async () => {
    throw new TypeError('Failed to fetch');
  });
  const error = await client.api('/api/private').catch((error) => error);

  assert.deepEqual(
    [error instanceof ApiError, error.status, client.errorText(error)],
    [true, 0, 'Нет связи с сервером. Проверьте подключение и повторите.'],
  );
});

test('non-JSON server errors use the generic message instead of response content', async () => {
  mock.method(
    globalThis,
    'fetch',
    async () => new Response('Internal server details', { status: 500 }),
  );
  const error = await client.api('/api/private').catch((error) => error);

  assert.deepEqual([error.status, error.message], [500, 'Не удалось выполнить операцию.']);
});
