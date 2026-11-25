<script setup lang="ts">
import useCreateNavigation from '../use/createNavigation';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { IconPlus } from '@tabler/icons-vue';
import DataTable from '../components/ui/DataTable.vue';
import useApi from '../use/api';
import { useAuthStore } from '../store/auth';
import type { NamedRecord } from '../types/models/NamedRecord';
import type { RegistryList } from '../types/api/Registry';
import type { TableColumn } from '../types/components/ui/DataTable';
import type { NamedRegistryProps } from '../types/components/NamedRegistry';

const { fromTable } = useCreateNavigation();
const props = defineProps<NamedRegistryProps>();
const { api, errorText } = useApi();
const auth = useAuthStore();
const deleteEndpoint = computed(() =>
  auth.canManageRentalRegistry ? '/api/' + props.kind : undefined,
);
const title = computed(() =>
  props.kind === 'ownerships' ? 'Формы собственности' : 'Типы договоров',
);
const basePath = computed(() => '/catalogues/' + props.kind);
const items = ref<NamedRecord[]>([]);
const query = ref('');
const loading = ref(false);
const error = ref('');
const columns: TableColumn<NamedRecord>[] = [{ key: 'name', label: 'Наименование' }];
let controller: AbortController | undefined;
let timer: ReturnType<typeof setTimeout> | undefined;

function rowRoute(row: NamedRecord) {
  return basePath.value + '/' + row.id;
}

async function load() {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;
  error.value = '';

  try {
    const result = await api<RegistryList<NamedRecord>>(
      '/api/' + props.kind + '?' + new URLSearchParams({ query: query.value }),
      { signal: current.signal },
    );

    if (!current.signal.aborted) {
      items.value = result.items;
    }
  } catch (e) {
    if (!current.signal.aborted) error.value = errorText(e);
  } finally {
    if (!current.signal.aborted) loading.value = false;
  }
}

function search() {
  clearTimeout(timer);
  timer = setTimeout(load, 300);
}

onMounted(load);
onBeforeUnmount(() => {
  controller?.abort();
  clearTimeout(timer);
});
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>
        {{ title }}
      </h1>
    </div>
  </div>
  <div
    v-if="error"
    class="alert alert-danger"
    role="alert"
  >
    {{ error }}
    <button
      class="btn ms-2"
      @click="load"
    >
      Повторить
    </button>
  </div>
  <section class="card">
    <DataTable
      :delete-endpoint="deleteEndpoint"
      @deleted="load"
      :rows="items"
      :columns="columns"
      :caption="title"
      :loading="loading"
      :error="!!error"
      :row-route="rowRoute"
    >
      <template #header>
        <label
          for="catalogue-search"
          class="visually-hidden"
        >
          Поиск
        </label>
        <input
          id="catalogue-search"
          v-model="query"
          type="search"
          class="form-control"
          placeholder="Наименование"
          @input="search"
        />
      </template>
      <template
        v-if="auth.canManageRentalRegistry"
        #actions
      >
        <RouterLink
          :to="fromTable(basePath + '/new')"
          class="btn btn-primary list-add-button"
          aria-label="Добавить запись"
          title="Добавить запись"
        >
          <IconPlus :size="18" />
          <span class="list-add-label">
            Добавить
          </span>
        </RouterLink>
      </template>
    </DataTable>
  </section>
</template>
