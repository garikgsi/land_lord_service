import test from 'node:test';
import assert from 'node:assert/strict';
import { creationReturnPath } from '../src/use/createNavigation.ts';

test('creation keeps origin table tab and filters and rejects external targets', () => {
  assert.deepEqual(
    [
      creationReturnPath('/objects/1?tab=utilities', '/meters'),
      creationReturnPath('//outside.example', '/meters'),
      creationReturnPath('https://outside.example', '/meters'),
      creationReturnPath(undefined, '/meters'),
    ],
    ['/objects/1?tab=utilities', '/meters', '/meters', '/meters'],
  );
});

test('saving returns to the source table and its tab, while direct visits stay open', async () => {
  const { createRenderer } = await import('vue');
  const { createRouter, createMemoryHistory } = await import('vue-router');
  const { default: useCreateNavigation } = await import('../src/use/createNavigation.ts');
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { render: () => null } }],
  });
  const renderer = createRenderer({
    createComment: () => ({}),
    insert() {},
    remove() {},
    parentNode: () => null,
    nextSibling: () => null,
  });
  let navigation;
  const app = renderer.createApp({
    setup() {
      navigation = useCreateNavigation();

      return () => null;
    },
  });

  app.use(router);
  await router.push({ path: '/spaces/1', query: { returnTo: '/objects/1?tab=spaces' } });
  app.mount({});

  try {
    const returned = await navigation.returnAfterSave();
    const table = router.currentRoute.value.fullPath;

    await router.push('/spaces/1');
    const stayed = await navigation.returnAfterSave();

    assert.deepEqual(
      [returned, table, stayed, router.currentRoute.value.fullPath],
      [true, '/objects/1?tab=spaces', false, '/spaces/1'],
    );
  } finally {
    app.unmount();
  }
});
