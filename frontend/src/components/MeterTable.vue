<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { IconPlus } from '@tabler/icons-vue';
import DataTable from './ui/DataTable.vue';
import ReferenceSelect from './ui/ReferenceSelect.vue';
import SelectInput from './ui/SelectInput.vue';
import type { SelectOption } from '../types/components/ui/SelectInput';
import { useAuthStore } from '../store/auth';
import useApi from '../use/api';
import type { Meter } from '../types/models/Meter';
import type { RegistryList } from '../types/api/Registry';
import type { TableColumn } from '../types/components/ui/DataTable';
import type { MeterTableProps } from '../types/forms/Meter';

const props = defineProps<MeterTableProps>();
const auth = useAuthStore();
const deleteEndpoint = computed(() => (auth.canManageMeters ? '/api/meters' : undefined));
const { api, errorText } = useApi();
const items = ref<Meter[]>([]);
const total = ref(0);
const page = ref(1);
const query = ref('');
const state = ref('all');
const objectId = ref<number | null>(null);
const spaceId = ref<number | null>(null);
const utilityId = ref<number | null>(null);
const effectiveObjectId = computed(() => props.objectId ?? objectId.value);
const objectOnlyOption = { id: -1, name: 'На объекте' };
const extraSpaceOptions = [objectOnlyOption];
const effectiveSpaceId = computed(
  () => props.spaceId ?? (spaceId.value === objectOnlyOption.id ? null : spaceId.value),
);
const installationOptions: SelectOption[] = [
  { value: 'all', label: 'Все приборы' },
  { value: 'installed', label: 'Установлены' },
  { value: 'uninstalled', label: 'Сняты' },
];
const showObjectFilter = computed(() => !props.objectId && !props.spaceId);
const spaceEndpoint = computed(() => '/api/objects/' + (effectiveObjectId.value ?? 0) + '/spaces');
const spacePlaceholder = computed(() =>
  effectiveObjectId.value ? 'Все места установки' : 'Сначала выберите объект',
);
const filtered = computed(
  () =>
    !!(objectId.value || spaceId.value || utilityId.value || query.value || state.value !== 'all'),
);
const loading = ref(false);
const error = ref('');
const createRoute = computed(() => ({
  path: '/meters/new',
  query: { objectId: props.objectId, spaceId: props.spaceId },
}));
const columns: TableColumn<Meter>[] = [
  { key: 'name', label: 'Прибор' },
  { key: 'installation', label: 'Место установки' },
  { key: 'lastReading', label: 'Показание' },
  { key: 'nextVerificationDate', label: 'Поверка' },
  { key: 'readingDueDate', label: 'Передача показаний' },
];
let controller: AbortController | undefined;
let timer: ReturnType<typeof setTimeout> | undefined;

function rowRoute(row: Meter) {
  return '/meters/' + row.id;
}

function readingLabel(row: Meter) {
  if (!row.installation) return 'Снят';

  if (row.readingSubmittedLate) return 'Переданы с просрочкой';

  return row.readingSubmitted ? 'Переданы' : 'До ' + row.readingDueDate;
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

  const params = new URLSearchParams({
    query: query.value,
    state: state.value,
    page: String(page.value),
  });

  if (effectiveObjectId.value) params.set('objectId', String(effectiveObjectId.value));

  if (effectiveSpaceId.value) params.set('spaceId', String(effectiveSpaceId.value));

  if (utilityId.value) params.set('utilityId', String(utilityId.value));

  if (!props.spaceId && spaceId.value === objectOnlyOption.id) params.set('objectOnly', '1');

  try {
    const result = await api<RegistryList<Meter>>('/api/meters?' + params, {
      signal: current.signal,
    });

    if (current.signal.aborted) return;

    items.value = result.items;
    total.value = result.total;
  } catch (e) {
    if (!current.signal.aborted) error.value = errorText(e);
  } finally {
    if (!current.signal.aborted) loading.value = false;
  }
}

function search() {
  page.value = 1;
  clearTimeout(timer);
  timer = setTimeout(load, 300);
}

function filterChanged() {
  clearTimeout(timer);
  page.value = 1;
  void load();
}

function objectChanged() {
  spaceId.value = null;
  filterChanged();
}

function resetFilters() {
  objectId.value = null;
  spaceId.value = null;
  utilityId.value = null;
  query.value = '';
  state.value = 'all';
  filterChanged();
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
defineExpose({ reload: load });
</script>

<template>
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
  <DataTable
    :delete-endpoint="deleteEndpoint"
    @deleted="deleted"
    :rows="items"
    :columns="columns"
    caption="Приборы учёта"
    :loading="loading"
    :error="!!error"
    :total="total"
    :page="page"
    :row-route="rowRoute"
    @page-change="changePage"
  >
    <template #header>
      <div class="w-100">
        <div class="row g-2">
          <div
            v-if="showObjectFilter"
            class="col-12 col-md-6 col-xl-4"
          >
            <ReferenceSelect
              id="meters-object-filter"
              v-model="objectId"
              label="Объект"
              endpoint="/api/objects"
              placeholder="Все объекты"
              :required="false"
              :allow-create="false"
              :validate-on-blur="false"
              @update:model-value="objectChanged"
            />
          </div>
          <div
            v-if="!props.spaceId"
            class="col-12 col-md-6 col-xl-4"
          >
            <ReferenceSelect
              :key="spaceEndpoint"
              id="meters-space-filter"
              v-model="spaceId"
              label="Помещение"
              :endpoint="spaceEndpoint"
              :extra-options="extraSpaceOptions"
              :placeholder="spacePlaceholder"
              :disabled="!effectiveObjectId"
              :required="false"
              :allow-create="false"
              :validate-on-blur="false"
              @update:model-value="filterChanged"
            />
          </div>
          <div class="col-12 col-md-6 col-xl-4">
            <ReferenceSelect
              id="meters-utility-filter"
              v-model="utilityId"
              label="Коммуникация"
              endpoint="/api/unities"
              placeholder="Все коммуникации"
              :required="false"
              :allow-create="false"
              :validate-on-blur="false"
              @update:model-value="filterChanged"
            />
          </div>
        </div>
        <div class="row g-2 mt-1">
          <div class="col-12 col-md-8">
            <input
              v-model="query"
              type="search"
              class="form-control"
              placeholder="Коммуникация, номер, место установки"
              aria-label="Поиск приборов"
              @input="search"
            />
          </div>
          <div class="col-12 col-md-4">
            <SelectInput
              id="meters-installation-filter"
              v-model="state"
              label="Установка прибора"
              :options="installationOptions"
              @update:model-value="filterChanged"
            />
          </div>
        </div>
        <button
          v-if="filtered"
          type="button"
          class="btn btn-sm mt-2"
          @click="resetFilters"
        >
          Сбросить фильтры
        </button>
      </div>
    </template>
    <template
      v-if="auth.canInstallMeters"
      #actions
    >
      <RouterLink
        :to="createRoute"
        class="btn btn-primary list-add-button"
        title="Добавить прибор"
        aria-label="Добавить прибор"
      >
        <IconPlus :size="18" />
        <span class="list-add-label">
          Добавить
        </span>
      </RouterLink>
    </template>
    <template #cell="{ row, column }">
      <template v-if="column.key === 'name'">
        <strong>
          {{ row.name }}
        </strong>
        <div class="text-secondary">
          {{ row.utility.unit }}
        </div>
      </template>
      <template v-else-if="column.key === 'installation'">
        {{ row.installation?.name ?? 'Снят' }}
        <div
          v-if="row.installation?.provider"
          class="text-secondary"
        >
          {{ row.installation.provider.unityProvider.name }}
        </div>
      </template>
      <template v-else-if="column.key === 'lastReading'">
        {{ row.lastReading?.meterRead ?? row.startRead }}
      </template>
      <template v-else-if="column.key === 'nextVerificationDate'">
        <span :class="{ 'text-danger': row.verificationExpired }">
          {{ row.nextVerificationDate }}
        </span>
        <div
          v-if="row.verificationExpired"
          class="text-danger"
        >
          Требуется поверка
        </div>
      </template>
      <template v-else-if="column.key === 'readingDueDate'">
        <span
          :class="{
            'text-danger': row.readingOverdue || row.readingSubmittedLate,
            'text-success': row.readingSubmitted && !row.readingSubmittedLate,
          }"
        >
          {{ readingLabel(row) }}
        </span>
      </template>
    </template>
  </DataTable>
</template>
