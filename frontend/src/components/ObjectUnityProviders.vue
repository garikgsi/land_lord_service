<script setup lang="ts">
import useCreateNavigation from '../use/createNavigation';
import Badge from './ui/Badge.vue';
import { computed, onMounted, ref } from 'vue';
import { onBeforeRouteLeave, RouterLink } from 'vue-router';
import { IconPencilPlus, IconPlus } from '@tabler/icons-vue';
import { objectUtilityRows } from '../use/objectUtilities';
import type { ObjectUtilityRow } from '../types/components/ObjectUtilities';
import type { UnityProviderContract } from '../types/models/UnityProviderContract';
import type { Meter } from '../types/models/Meter';
import type { FormState } from '../types/components/FormState';
import DataTable from './ui/DataTable.vue';
import MeterDetailsModal from './MeterDetailsModal.vue';
import MeterReadingCreateModal from './MeterReadingCreateModal.vue';
import ReferenceSelect from './ui/ReferenceSelect.vue';
import SelectInput from './ui/SelectInput.vue';
import { unityProviderContractStatusLabels } from '../store/unityProviderContracts';
import type { SelectOption } from '../types/components/ui/SelectInput';
import { useAuthStore } from '../store/auth';
import useApi from '../use/api';
import type { ObjectUnityProvidersProps } from '../types/forms/Meter';
import type { ObjectUnityProvider } from '../types/models/Unity';
import type { RegistryList } from '../types/api/Registry';
import type { TableColumn } from '../types/components/ui/DataTable';

const { fromTable } = useCreateNavigation();
const props = defineProps<ObjectUnityProvidersProps>();
const auth = useAuthStore();
const { api, errorText } = useApi();
const providers = ref<ObjectUnityProvider[]>([]);
const meters = ref<Meter[]>([]);
const contracts = ref<UnityProviderContract[]>([]);
const providerId = ref<number | null>(null);
const unityId = ref<number | null>(null);
const status = ref('all');
const withMeters = ref(false);
const statusOptions: SelectOption[] = [
  { value: 'all', label: 'Все статусы' },
  ...Object.entries(unityProviderContractStatusLabels).map(([value, label]) => ({ value, label })),
];
const page = ref(1);
const pageSize = 20;
const rows = computed(() =>
  objectUtilityRows(providers.value, meters.value, contracts.value).filter(
    (row) =>
      (!providerId.value || row.unityProvider.id === providerId.value) &&
      (!unityId.value || row.unity?.id === unityId.value) &&
      (status.value === 'all' || row.contract?.status === status.value) &&
      (!withMeters.value || row.meters.length > 0),
  ),
);
const visibleRows = computed(() =>
  rows.value.slice((page.value - 1) * pageSize, page.value * pageSize),
);
const filtered = computed(
  () => !!(providerId.value || unityId.value || status.value !== 'all' || withMeters.value),
);
const selectedMeterId = ref<number | null>(null);
const readingMeter = ref<Meter | null>(null);
const readingModal = ref<Pick<FormState, 'saving'> | null>(null);
const createRoute = computed(() =>
  fromTable({
    path: '/unity-provider-contracts/new',
    query: { objectId: String(props.objectId) },
  }),
);
const loading = ref(false);
const error = ref('');
const endpoint = computed(() => '/api/objects/' + props.objectId + '/unity-providers');
const columns: TableColumn<ObjectUtilityRow>[] = [
  { key: 'unity', label: 'Коммуникация', value: (row) => row.unity?.name ?? '—' },
  { key: 'unityProvider', label: 'Поставщик' },
  { key: 'contractNumLabel', label: 'Номер', value: (row) => row.contractNumLabel },
  { key: 'expiryLabel', label: 'Дата окончания', value: (row) => row.expiryLabel },
  { key: 'statusLabel', label: 'Статус' },
  { key: 'meters', label: 'Счетчик' },
];

function rowRoute(row: ObjectUtilityRow) {
  if (row.contract) return '/unity-provider-contracts/' + row.contract.id;

  return {
    path: '/unity-provider-contracts',
    query: { objectId: String(props.objectId), unityProviderId: String(row.unityProvider.id) },
  };
}

function resetFilters() {
  providerId.value = null;
  unityId.value = null;
  status.value = 'all';
  withMeters.value = false;
  page.value = 1;
}

async function readingCreated() {
  readingMeter.value = null;
  await load();
}

async function loadAll<T>(path: string, filters: Record<string, string> = {}): Promise<T[]> {
  const items: T[] = [];
  let nextPage = 1;
  let total = 0;

  do {
    const result = await api<RegistryList<T>>(
      path +
        '?' +
        new URLSearchParams({
          ...filters,
          page: String(nextPage),
        }),
    );

    items.push(...result.items);
    total = result.total;
    nextPage++;

    if (!result.items.length) break;
  } while (items.length < total);

  return items;
}

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const [links, installed, providerContracts] = await Promise.all([
      api<RegistryList<ObjectUnityProvider>>(endpoint.value),
      loadAll<Meter>('/api/meters', {
        objectId: String(props.objectId),
        objectOnly: '1',
        state: 'installed',
      }),
      loadAll<UnityProviderContract>(
        '/api/objects/' + props.objectId + '/unity-provider-contracts',
      ),
    ]);

    providers.value = links.items;
    meters.value = installed;
    contracts.value = providerContracts;
    page.value = Math.min(page.value, Math.max(1, Math.ceil(rows.value.length / pageSize)));
  } catch (e) {
    error.value = errorText(e);
  } finally {
    loading.value = false;
  }
}

onBeforeRouteLeave(() => !auth.user || !readingModal.value?.saving);
onMounted(load);
</script>

<template>
  <div class="object-utilities">
    <div
      v-if="error"
      class="alert alert-danger m-3"
      role="alert"
    >
      {{ error }}
    </div>
    <DataTable
      :rows="visibleRows"
      :columns="columns"
      caption="Поставщики и счетчики"
      :row-route="rowRoute"
      :loading="loading"
      :error="!!error"
      :total="rows.length"
      :page="page"
      :page-size="pageSize"
      @page-change="page = $event"
    >
      <template #header>
        <div class="w-100">
          <div class="row g-2">
            <div class="col-12 col-md-6 col-xl-3">
              <ReferenceSelect
                id="object-provider-filter"
                v-model="providerId"
                label="Поставщик"
                endpoint="/api/unity-providers"
                placeholder="Все поставщики"
                :allow-create="false"
                :required="false"
                :validate-on-blur="false"
                @update:model-value="page = 1"
              />
            </div>
            <div class="col-12 col-md-6 col-xl-3">
              <ReferenceSelect
                id="object-unity-filter"
                v-model="unityId"
                label="Коммуникация"
                endpoint="/api/unities"
                placeholder="Все коммуникации"
                :allow-create="false"
                :required="false"
                :validate-on-blur="false"
                @update:model-value="page = 1"
              />
            </div>
            <div class="col-12 col-md-6 col-xl-3">
              <label
                for="object-contract-status-filter"
                class="form-label"
              >
                Статус
              </label>
              <SelectInput
                id="object-contract-status-filter"
                v-model="status"
                label="Статус договора"
                :options="statusOptions"
                @update:model-value="page = 1"
              />
            </div>
            <div class="col-12 col-md-6 col-xl-3">
              <div class="object-utilities-meter-filter">
                <label class="form-check form-switch mb-0">
                  <input
                    v-model="withMeters"
                    type="checkbox"
                    role="switch"
                    class="form-check-input"
                    @change="page = 1"
                  />
                  <span class="form-check-label">
                    Со счетчиком
                  </span>
                </label>
              </div>
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
        v-if="auth.canManageRentalRegistry"
        #actions
      >
        <RouterLink
          :to="createRoute"
          class="btn btn-primary list-add-button"
          aria-label="Добавить договор поставщика"
        >
          <IconPlus :size="18" />
          <span class="list-add-label">
            Добавить
          </span>
        </RouterLink>
      </template>
      <template #cell="{ row, column }">
        <div
          v-if="column.key === 'meters'"
          class="d-flex flex-wrap gap-2"
        >
          <div
            v-for="meter in row.meters"
            :key="meter.id"
            class="d-inline-flex align-items-center gap-1 mw-100"
          >
            <Badge
              action
              @click.stop="selectedMeterId = meter.id"
              :color="meter.badgeColor"
              wrap
            >
              {{ meter.name }}
            </Badge>
            <button
              v-if="auth.canEditMeterReadings"
              type="button"
              class="btn btn-sm flex-shrink-0"
              title="Внести показания"
              aria-label="Внести показания"
              @click.stop="readingMeter = meter"
            >
              <IconPencilPlus
                :size="16"
                class="d-md-none"
                aria-hidden="true"
              />
              <span class="d-none d-md-inline">
                Показания
              </span>
            </button>
          </div>
          <span
            v-if="!row.meters.length"
            class="text-secondary"
          >
            Нет счетчиков
          </span>
        </div>
        <template v-else-if="column.key === 'statusLabel'">
          <Badge
            v-if="row.contract"
            :color="row.statusColor"
          >
            {{ row.statusLabel }}
          </Badge>
          <span v-else>
            —
          </span>
        </template>
        <div
          v-else-if="column.key === 'unityProvider'"
          class="d-flex align-items-center gap-2"
        >
          <span>
            {{ row.unityProvider.name }}
          </span>
        </div>
        <template v-else>
          {{ column.value?.(row) }}
        </template>
      </template>
    </DataTable>
  </div>
  <MeterReadingCreateModal
    v-if="readingMeter"
    ref="readingModal"
    :meter="readingMeter"
    @close="readingMeter = null"
    @saved="readingCreated"
  />
  <MeterDetailsModal
    v-if="selectedMeterId !== null"
    :meter-id="selectedMeterId"
    @close="selectedMeterId = null"
  />
</template>

<style scoped>
.object-utilities-meter-filter {
  display: flex;
  align-items: center;
  min-height: 2.5rem;
}

@media (min-width: 768px) {
  .object-utilities-meter-filter {
    margin-top: 1.75rem;
  }
}
</style>
