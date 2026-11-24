import { nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import type { LocationQueryRaw } from 'vue-router';

export function creationReturnPath(value: unknown, fallback: string): string {
  return typeof value === 'string' &&
    value.startsWith('/') &&
    !value.startsWith('//') &&
    !value.includes('\\') &&
    !/[\r\n]/.test(value)
    ? value
    : fallback;
}

export default function useCreateNavigation() {
  const route = useRoute();
  const router = useRouter();

  function returnAfterCreate(fallback: string, query: LocationQueryRaw = {}) {
    const target = router.resolve(creationReturnPath(route.query.returnTo, fallback));

    return router.replace({
      path: target.path,
      query: { ...target.query, ...query },
      hash: target.hash,
    });
  }

  async function returnAfterSave() {
    const path = creationReturnPath(route.query.returnTo, '');

    if (!path || router.resolve(path).fullPath === route.fullPath) return false;

    await nextTick();
    await router.replace(path);

    return true;
  }

  return { returnAfterCreate, returnAfterSave };
}
