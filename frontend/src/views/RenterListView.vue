<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { IconPlus } from '@tabler/icons-vue';
import DataTable from '../components/ui/DataTable.vue';
import { useAuthStore } from '../store/auth';
import useApi from '../use/api';
import type { Renter } from '../types/models/Renter';
import type { RegistryList } from '../types/api/Registry';
import type { TableColumn } from '../types/components/ui/DataTable';

const auth = useAuthStore();
const deleteEndpoint = computed(() => (auth.canManageRentalRegistry ? '/api/renters' : undefined));
const { api, errorText } = useApi();
const items = ref<Renter[]>([]);
const query = ref('');
const page = ref(1);
const total = ref(0);
const loading = ref(false);
const error = ref('');
const columns: TableColumn<Renter>[] = [
  { key: 'name', label: 'Наименование' },
  { key: 'ownership', label: 'Форма собственности', value: (row) => row.ownership.name },
  { key: 'inn', label: 'ИНН' },
];
let controller: AbortController | undefined;
let timer: ReturnType<typeof setTimeout> | undefined;

function rowRoute(row: Renter) {
  return '/renters/' + row.id;
}

function deleted() {
  if (items.value.length === 1 && page.value > 1) page.value--;

  void load();
}

async function load() {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;
  error.value = '';

  try {
    const result = await api<RegistryList<Renter>>(
      '/api/renters?' + new URLSearchParams({ query: query.value, page: String(page.value) }),
      { signal: current.signal },
    );

    if (current.signal.aborted) return;

    items.value = result.items;
    total.value = result.total;
  } catch (e) {
    if (!current.signal.aborted) error.value = errorText(e);
  } finally {
    if (!current.signal.aborted) loading.value = false;
  }
}

function searchChanged() {
  clearTimeout(timer);
  page.value = 1;
  timer = setTimeout(load, 300);
}

function changePage(value: number) {
  page.value = value;
  void load();
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
        Арендаторы
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
      @deleted="deleted"
      :rows="items"
      :columns="columns"
      caption="Арендаторы"
      :total="total"
      :page="page"
      :loading="loading"
      :error="!!error"
      :row-route="rowRoute"
      @page-change="changePage"
    >
      <template #header>
        <input
          v-model="query"
          type="search"
          class="form-control"
          aria-label="Поиск арендаторов"
          placeholder="Наименование или ИНН"
          maxlength="120"
          @input="searchChanged"
        />
      </template>
      <template
        v-if="auth.canManageRentalRegistry"
        #actions
      >
        <RouterLink
          to="/renters/new"
          class="btn btn-primary list-add-button"
          aria-label="Добавить арендатора"
          title="Добавить арендатора"
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
