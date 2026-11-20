<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { IconPlus } from '@tabler/icons-vue';
import DataTable from '../components/ui/DataTable.vue';
import type { TableColumn } from '../types/components/ui/DataTable';
import useApi from '../use/api';
import useColor from '../use/color';
import { useAuthStore } from '../store/auth';
import type { RegistryList } from '../types/api/Registry';
import type { RentalObject } from '../types/models/RentalObject';
import type { LandLord } from '../types/models/LandLord';

import type { RegistryProps, RegistryTableRow } from '../types/components/Registry';

const props = defineProps<RegistryProps>();
const auth = useAuthStore();
const { colorStyle } = useColor();
const { api, errorText } = useApi();
const items = ref<(RentalObject | LandLord)[]>([]);
const query = ref('');
const page = ref(1);
const total = ref(0);
const loading = ref(false);
const error = ref('');
const objects = computed(() => props.kind === 'objects');
const title = computed(() => (objects.value ? 'Объекты' : 'Арендодатели'));
const description = computed(() =>
  objects.value ? 'Объекты недвижимости и их основные данные' : 'Справочник арендодателей объектов',
);
const basePath = computed(() => (objects.value ? '/objects' : '/objects/land-lords'));
const addLabel = computed(() => (objects.value ? 'Добавить объект' : 'Добавить арендодателя'));
const searchLabel = computed(() =>
  objects.value ? 'Название, адрес или арендодатель' : 'Название арендодателя',
);
const emptyMessage = computed(() =>
  auth.canManageRentalRegistry
    ? 'Записей пока нет. Добавьте первую запись или измените поиск.'
    : 'Записей пока нет или по вашему запросу ничего не найдено.',
);
const rows = computed<RegistryTableRow[]>(() =>
  items.value.map((item) => ({
    id: item.id,
    name: item.name,
    address: 'address' in item ? item.address : '',
    square: 'square' in item ? item.square : '',
    landLord: 'landLord' in item ? item.landLord.name : '',
    landLordId: 'landLord' in item ? item.landLord.id : null,
    landLordColor: colorStyle('landLord' in item ? item.landLord.color : item.color),
    landLordColorLabel:
      'Цвет: ' + (('landLord' in item ? item.landLord.color : item.color) ?? 'Indigo'),
    landLordColorClass: ('landLord' in item ? item.landLord.color : item.color)
      ? ''
      : 'bg-indigo text-indigo-fg',
    path: basePath.value + '/' + item.id,
  })),
);
const columns = computed<TableColumn<RegistryTableRow>[]>(() => {
  const name: TableColumn<RegistryTableRow> = { key: 'name', label: 'Наименование' };

  return objects.value
    ? [
        name,
        { key: 'landLord', label: 'Арендодатель' },
        { key: 'square', label: 'Площадь, м²', class: 'text-nowrap' },
      ]
    : [name, { key: 'landLordColor', label: 'Цвет' }];
});

function rowRoute(row: RegistryTableRow) {
  return row.path;
}

function rowLabel(row: RegistryTableRow) {
  return 'Открыть карточку: ' + row.name;
}

let controller: AbortController | undefined;
let timer: ReturnType<typeof setTimeout> | undefined;

async function load() {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;
  error.value = '';

  try {
    const result = await api<RegistryList<RentalObject | LandLord>>(
      '/api/' +
        props.kind +
        '?' +
        new URLSearchParams({ query: query.value, page: String(page.value) }),
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
      <div class="page-pretitle">
        Недвижимость
      </div>
      <h1>
        {{ title }}
      </h1>
      <p>
        {{ description }}
      </p>
    </div>
  </div>
  <div
    v-if="error"
    class="alert alert-danger"
    role="alert"
  >
    {{ error }}
    <button
      class="btn btn-sm ms-2"
      @click="load"
    >
      Повторить
    </button>
  </div>
  <section class="card">
    <DataTable
      :rows="rows"
      :columns="columns"
      :caption="title"
      :loading="loading"
      :error="!!error"
      :empty-text="emptyMessage"
      :row-route="rowRoute"
      :row-label="rowLabel"
      :total="total"
      :page="page"
      @page-change="changePage"
    >
      <template #header>
        <label
          class="visually-hidden"
          for="registry-search"
        >
          Поиск
        </label>
        <input
          id="registry-search"
          v-model="query"
          type="search"
          class="form-control"
          :placeholder="searchLabel"
          maxlength="120"
          @input="searchChanged"
        />
      </template>
      <template
        v-if="auth.canManageRentalRegistry"
        #actions
      >
        <RouterLink
          class="btn btn-primary list-add-button"
          :aria-label="addLabel"
          :title="addLabel"
          :to="basePath + '/new'"
        >
          <IconPlus :size="18" />
          <span class="list-add-label">
            {{ addLabel }}
          </span>
        </RouterLink>
      </template>
      <template #cell="{ row, column }">
        <template v-if="column.key === 'name'">
          <div class="fw-semibold">
            {{ row.name }}
          </div>
          <div
            v-if="row.address"
            class="text-secondary small mt-1"
          >
            {{ row.address }}
          </div>
        </template>
        <span
          v-else-if="column.key === 'landLord'"
          class="badge landlord-badge"
          :class="row.landLordColorClass"
          :style="row.landLordColor"
        >
          {{ row.landLord }}
        </span>
        <span
          v-else-if="column.key === 'landLordColor'"
          class="color-dot"
          :class="row.landLordColorClass"
          :style="row.landLordColor"
          role="img"
          :aria-label="row.landLordColorLabel"
        >
        </span>
        <template v-else-if="column.key === 'square'">
          {{ row.square }}
        </template>
      </template>
    </DataTable>
  </section>
</template>
