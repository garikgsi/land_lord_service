import { ref, shallowRef } from 'vue';
import { defineStore } from 'pinia';
import type { CachedResponse } from '../types/use/SessionCache';

export const useSessionCacheStore = defineStore('sessionCache', () => {
  const entries = shallowRef<Record<string, CachedResponse>>({});
  const generation = ref(0);
  let day = new Date().toDateString();

  function reset() {
    generation.value++;
    entries.value = {};
    day = new Date().toDateString();
  }

  function get(path: string) {
    if (day !== new Date().toDateString()) reset();

    return entries.value[path];
  }

  function put(path: string, value: unknown, version: number) {
    if (version !== generation.value) return;

    if (value && typeof value === 'object' && 'items' in value) {
      if (
        !Array.isArray(value.items) ||
        !('total' in value) ||
        value.total !== value.items.length ||
        value.items.length > 500 ||
        ('page' in value && value.page !== 1)
      )
        return;
    }

    entries.value = { ...entries.value, [path]: { value: structuredClone(value) } };
  }

  return { entries, generation, get, put, reset };
});

export function sessionCacheKey(path: string): string | null {
  const [pathname, query = ''] = path.split('?');

  if (
    !/^\/api\/(?:land-lords|ownerships|contract-types|unities|unity-providers|objects|spaces)(?:\/\d+)?$/.test(
      pathname,
    ) &&
    !/^\/api\/objects\/\d+\/spaces$/.test(pathname)
  )
    return null;

  const params = new URLSearchParams(query);

  params.sort();

  return pathname + (params.size ? '?' + params : '');
}
