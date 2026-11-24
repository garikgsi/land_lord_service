<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { IconPlus } from '@tabler/icons-vue';
import DataTable from './ui/DataTable.vue';
import useApi from '../use/api';
import { useAuthStore } from '../store/auth';
import type { RenterContact } from '../types/models/RenterContact';
import type { RenterContactsProps, RenterContactRow } from '../types/components/RenterContacts';
import type { RegistryList } from '../types/api/Registry';
import type { TableColumn } from '../types/components/ui/DataTable';

const props = defineProps<RenterContactsProps>();
const { api, errorText } = useApi();
const auth = useAuthStore();
const items = ref<RenterContact[]>([]);
const page = ref(1);
const total = ref(0);
const query = ref('');
const loading = ref(false);
const error = ref('');
const basePath = computed(() => '/renters/' + props.renterId + '/contacts');
const rows = computed<RenterContactRow[]>(() =>
  items.value.map((item) => ({
    ...item,
    fullName: [item.name, item.lastName, item.patronymic].filter(Boolean).join(' '),
  })),
);
const columns: TableColumn<RenterContactRow>[] = [
  { key: 'fullName', label: 'Контакт' },
  { key: 'phone', label: 'Телефон' },
  { key: 'email', label: 'Email' },
];
let controller: AbortController | undefined;
let timer: ReturnType<typeof setTimeout> | undefined;

function rowRoute(row: RenterContactRow) {
  return basePath.value + '/' + row.id;
}

async function reload(nextPage = page.value) {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;
  error.value = '';

  try {
    const result = await api<RegistryList<RenterContact>>(
      '/api/renters/' +
        props.renterId +
        '/contacts?' +
        new URLSearchParams({ page: String(nextPage), query: query.value }),
      { signal: current.signal },
    );

    if (current.signal.aborted) return;

    items.value = result.items;
    total.value = result.total;
    page.value = result.page;
  } catch (e) {
    if (!current.signal.aborted) error.value = errorText(e);
  } finally {
    if (!current.signal.aborted) loading.value = false;
  }
}

function search() {
  clearTimeout(timer);
  timer = setTimeout(() => reload(1), 300);
}

onMounted(() => reload());
onBeforeUnmount(() => {
  controller?.abort();
  clearTimeout(timer);
});
defineExpose({ reload: () => reload() });
</script>

<template>
  <div
    v-if="error"
    class="alert alert-danger m-3"
    role="alert"
  >
    {{ error }}
    <button
      class="btn ms-2"
      @click="reload()"
    >
      Повторить
    </button>
  </div>
  <DataTable
    :rows="rows"
    :columns="columns"
    caption="Контакты"
    :row-route="rowRoute"
    :loading="loading"
    :error="!!error"
    :total="total"
    :page="page"
    empty-text="Контактов пока нет."
    @page-change="reload"
  >
    <template #header>
      <label
        for="contact-search"
        class="visually-hidden"
      >
        Поиск контактов
      </label>
      <input
        id="contact-search"
        v-model="query"
        type="search"
        class="form-control"
        placeholder="Имя, фамилия, телефон или email"
        @input="search"
      />
    </template>
    <template
      v-if="auth.user"
      #actions
    >
      <RouterLink
        :to="basePath + '/new'"
        class="btn btn-primary list-add-button"
        aria-label="Добавить контакт"
        title="Добавить контакт"
      >
        <IconPlus :size="18" />
        <span class="list-add-label">
          Добавить
        </span>
      </RouterLink>
    </template>
  </DataTable>
</template>
