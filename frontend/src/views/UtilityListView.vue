<script setup lang="ts">
import useCreateNavigation from '../use/createNavigation';
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { IconPlus } from '@tabler/icons-vue';
import DataTable from '../components/ui/DataTable.vue';
import { useAuthStore } from '../store/auth';
import useApi from '../use/api';
import type { Unity, UnityProvider } from '../types/models/Unity';
import type { UtilityRegistryProps } from '../types/forms/Meter';
import type { RegistryList } from '../types/api/Registry';
import type { TableColumn } from '../types/components/ui/DataTable';

const { fromTable } = useCreateNavigation();
const props = defineProps<UtilityRegistryProps>();
const auth = useAuthStore();
const deleteEndpoint = computed(() => (auth.canManageMeters ? '/api/' + props.kind : undefined));
const { api, errorText } = useApi();
const items = ref<(Unity | UnityProvider)[]>([]);
const query = ref('');
const loading = ref(false);
const error = ref('');
const title = computed(() =>
  props.kind === 'unities' ? 'Коммуникации' : 'Поставщики коммунальных услуг',
);
const base = computed(() => '/catalogues/' + props.kind);
const columns = computed<TableColumn<Unity | UnityProvider>[]>(() =>
  props.kind === 'unities'
    ? [
        { key: 'name', label: 'Наименование' },
        { key: 'id', label: 'Учёт', value: detail },
      ]
    : [{ key: 'name', label: 'Наименование' }],
);

function detail(row: Unity | UnityProvider) {
  return 'useMeter' in row
    ? (row.useMeter ? 'Возможна установка счётчика' : 'Без счётчика') +
        (row.unit ? ' · ' + row.unit : '')
    : '';
}

function rowRoute(row: Unity | UnityProvider) {
  return base.value + '/' + row.id;
}

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const result = await api<RegistryList<Unity | UnityProvider>>(
      '/api/' + props.kind + '?' + new URLSearchParams({ query: query.value }),
    );

    items.value = result.items;
  } catch (e) {
    error.value = errorText(e);
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div class="page-heading">
    <h1>
      {{ title }}
    </h1>
  </div>
  <div
    v-if="error"
    class="alert alert-danger"
    role="alert"
  >
    {{ error }}
  </div>
  <section class="card">
    <DataTable
      :delete-endpoint="deleteEndpoint"
      @deleted="load"
      :rows="items"
      :columns="columns"
      :caption="title"
      :row-route="rowRoute"
      :loading="loading"
      :error="!!error"
    >
      <template #header>
        <form
          class="d-flex gap-2 w-100"
          @submit.prevent="load"
        >
          <input
            v-model="query"
            type="search"
            class="form-control"
            placeholder="Наименование"
            aria-label="Поиск"
          />
          <button
            class="btn"
            type="submit"
          >
            Найти
          </button>
        </form>
      </template>
      <template
        v-if="auth.canManageMeters"
        #actions
      >
        <RouterLink
          :to="fromTable(base + '/new')"
          class="btn btn-primary list-add-button"
          title="Добавить"
          aria-label="Добавить"
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
