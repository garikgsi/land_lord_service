import { nextTick } from 'vue';
import { useRouter } from 'vue-router';
import type { LocationQueryRaw, RouteLocationRaw, Router } from 'vue-router';

export function creationReturnPath(value: unknown, fallback: string): string {
  return typeof value === 'string' &&
    value.startsWith('/') &&
    !value.startsWith('//') &&
    !value.includes('\\') &&
    !/[\r\n]/.test(value)
    ? value
    : fallback;
}

function returnPages(router: Router): string[] {
  const pages = router.options.history.state.returnPages;

  return Array.isArray(pages)
    ? pages.filter((page): page is string => !!creationReturnPath(page, ''))
    : [];
}

export function configureLegacyReturns(router: Router) {
  return router.beforeEach((to) => {
    if (!('returnTo' in to.query)) return;

    const { returnTo, ...query } = to.query;
    const pages: string[] = [];
    let previous = creationReturnPath(returnTo, '');

    while (previous) {
      const parent = router.resolve(previous);
      const { returnTo: ancestor, ...parentQuery } = parent.query;

      pages.push(
        router.resolve({ path: parent.path, query: parentQuery, hash: parent.hash }).fullPath,
      );
      previous = creationReturnPath(ancestor, '');
    }

    return { path: to.path, query, hash: to.hash, state: { returnPages: pages.reverse() } };
  });
}

export default function useCreateNavigation() {
  const router = useRouter();

  function fromTable(to: RouteLocationRaw) {
    const { path, query, hash } = router.resolve(to);
    const pages = [...returnPages(router), router.currentRoute.value.fullPath];

    return { path, query, hash, state: { returnPages: pages } };
  }

  function returnTarget(fallback: RouteLocationRaw) {
    const pages = returnPages(router);
    const { path, query, hash } = router.resolve(pages.pop() ?? fallback);

    return { path, query, hash, state: { returnPages: pages } };
  }

  function returnAfterCreate(fallback: RouteLocationRaw, query: LocationQueryRaw = {}) {
    const target = returnTarget(fallback);

    return router.replace({ ...target, query: { ...target.query, ...query } });
  }

  async function returnAfterSave() {
    const path = returnPages(router).at(-1);

    if (!path || path === router.currentRoute.value.fullPath) return false;

    const target = returnTarget(path);

    await nextTick();
    await router.replace(target);

    return true;
  }

  return { fromTable, returnTarget, returnAfterCreate, returnAfterSave };
}
