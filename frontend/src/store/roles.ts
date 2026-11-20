import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import useApi from '../use/api.ts';
import type { Role, RoleDefinition } from '../types/models/Role';
import type { RoleList } from '../types/api/Role';

export const useRolesStore = defineStore('roles', () => {
  const { api } = useApi();
  const items = ref<RoleDefinition[]>([]);
  const loaded = ref(false);
  const labels = computed(
    () =>
      Object.fromEntries(items.value.map((role) => [role.code, role.label])) as Partial<
        Record<Role, string>
      >,
  );
  const colors = computed(
    () =>
      Object.fromEntries(
        items.value.map((role) => [
          role.code,
          role.code === 'ROLE_SUPER_ADMIN' ? 'bg-purple-lt' : 'bg-blue-lt',
        ]),
      ) as Partial<Record<Role, string>>,
  );
  let pending: Promise<void> | undefined;
  let version = 0;

  function load() {
    pending ??= (async () => {
      const current = version;

      try {
        const result = await api<RoleList>('/api/roles');

        if (current === version) {
          items.value = result.items;
          loaded.value = true;
        }
      } catch (error) {
        if (current === version) pending = undefined;

        throw error;
      }
    })();

    return pending;
  }

  function reset() {
    version++;
    items.value = [];
    loaded.value = false;
    pending = undefined;
  }

  return { items, labels, colors, loaded, load, reset };
});
