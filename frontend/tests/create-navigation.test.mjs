import test from 'node:test';
import assert from 'node:assert/strict';
import { createRenderer } from 'vue';
import { createRouter, createMemoryHistory } from 'vue-router';
import useCreateNavigation, {
  configureLegacyReturns,
  creationReturnPath,
} from '../src/use/createNavigation.ts';
import { workspaceHistory } from '../src/router/workspaceHistory.ts';

function fixture(t, browser = createMemoryHistory()) {
  const history = workspaceHistory(browser);
  const router = createRouter({
    history,
    routes: [{ path: '/:pathMatch(.*)*', component: { render: () => null } }],
  });

  configureLegacyReturns(router);
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
  app.mount({});
  t.after(() => app.unmount());

  return { router, history, browser, navigation };
}

test('contextual creation, reload and both return levels keep clean addresses', async (t) => {
  const { router, history, browser, navigation } = fixture(t);

  await router.push('/objects');
  await router.push(navigation.fromTable('/objects/1'));
  await router.replace({ query: { tab: 'spaces' } });
  assert.equal(browser.location, '/objects/1?tab=spaces');

  const link = navigation.fromTable({ path: '/contracts/new', query: { objectId: '1' } });

  assert.equal(router.resolve(link).href, '/contracts/new');
  await router.push(link);
  assert.equal(browser.location, '/contracts/new');
  assert.deepEqual(browser.state.returnPages, ['/objects', '/objects/1?tab=spaces']);

  const reload = fixture(t, browser);

  await reload.router.replace(reload.history.location);
  assert.equal(reload.router.currentRoute.value.query.objectId, '1');
  await reload.navigation.returnAfterCreate('/contracts');
  assert.equal(reload.router.currentRoute.value.query.tab, 'spaces');
  assert.equal(browser.location, '/objects/1?tab=spaces');
  assert.equal(await reload.navigation.returnAfterSave(), true);
  assert.equal(browser.location, '/objects');
  assert.equal(await reload.navigation.returnAfterSave(), false);
  assert.equal(history.location, '/objects');
});

test('browser Back and Forward restore separate meter assignment contexts', async (t) => {
  const { router, browser, navigation } = fixture(t);

  await router.push('/contracts/9?tab=meters');
  await router.push(navigation.fromTable('/meters/new?objectId=1&spaceId=2'));
  await router.push('/meters');

  async function go(delta) {
    const navigated = new Promise((resolve) => {
      const off = router.afterEach(() => {
        off();
        resolve();
      });
    });

    router.go(delta);
    await navigated;
  }

  await go(-1);
  assert.deepEqual(router.currentRoute.value.query, { objectId: '1', spaceId: '2' });
  assert.equal(browser.location, '/meters/new');
  await go(-1);
  assert.equal(router.currentRoute.value.query.tab, 'meters');
  await go(1);
  assert.equal(router.currentRoute.value.query.spaceId, '2');
  await router.push(navigation.returnTarget('/meters'));
  assert.equal(router.currentRoute.value.fullPath, '/contracts/9?tab=meters');
  await router.push('/meters/new');
  assert.deepEqual(router.currentRoute.value.query, {});
  assert.equal(await navigation.returnAfterSave(), false);
});

test('bookmarked legacy links import both return levels and reject external returns', async (t) => {
  const { router, browser, navigation } = fixture(t);

  await router.push('/contracts/new?objectId=1&returnTo=/objects/1?returnTo=/objects%26tab=spaces');
  assert.equal(browser.location, '/contracts/new');
  assert.deepEqual(browser.state.returnPages, ['/objects', '/objects/1?tab=spaces']);
  await router.push(navigation.returnTarget('/contracts'));
  assert.equal(router.currentRoute.value.query.tab, 'spaces');
  await router.push('/contracts/new?returnTo=//outside.example');
  assert.equal(await navigation.returnAfterSave(), false);
  assert.equal(creationReturnPath('https://outside.example', '/contracts'), '/contracts');
});

test('only navigation context is hidden, public links and unrelated filters remain shareable', async (t) => {
  const { router, browser } = fixture(t);

  await router.push('/meters?status=installed&objectId=5#results');
  assert.equal(browser.location, '/meters?status=installed#results');
  assert.equal(router.currentRoute.value.query.objectId, '5');
  await router.push('/activate?token=example');
  assert.equal(browser.location, '/activate?token=example');
  await router.push({ path: '/login', query: { redirect: '/contracts/new?objectId=7' } });
  assert.equal(browser.location, '/login');
  assert.equal(router.currentRoute.value.query.redirect, '/contracts/new?objectId=7');
});

test('a copied tab link opens the same tab without carrying private form context', async (t) => {
  const source = fixture(t);
  const destination = '/contracts/9?tab=meters&objectId=7#details';
  const href = source.router.resolve(destination).href;

  assert.equal(href, '/contracts/9?tab=meters#details');
  const fresh = fixture(t);

  await fresh.router.push(href);
  assert.deepEqual(fresh.router.currentRoute.value.query, { tab: 'meters' });
  assert.equal(fresh.browser.location, href);
});

test('reloading an older history entry promotes its stored tab into the address', async (t) => {
  const browser = createMemoryHistory();

  browser.replace('/objects/1', { workspaceQuery: 'tab=utilities', returnPages: ['/objects'] });
  const { router, history } = fixture(t, browser);

  await router.replace(history.location);
  assert.equal(browser.location, '/objects/1?tab=utilities');
  assert.equal(router.currentRoute.value.query.tab, 'utilities');
  assert.deepEqual(browser.state.returnPages, ['/objects']);
});

test('return paths must be local absolute paths', () => {
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
