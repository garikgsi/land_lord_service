<script setup lang="ts">
import Badge from './ui/Badge.vue';
import {
  unityProviderContractStatusLabels,
  unityProviderContractStatusColors,
} from '../store/unityProviderContracts';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink, useRoute, onBeforeRouteLeave } from 'vue-router';
import ReferenceSelect from './ui/ReferenceSelect.vue';
import SelectInput from './ui/SelectInput.vue';
import MeterDetailsModal from './MeterDetailsModal.vue';
import MeterReadingCreateModal from './MeterReadingCreateModal.vue';
import ContractDetailsModal from './ContractDetailsModal.vue';
import type { SelectOption } from '../types/components/ui/SelectInput';
import type { FormState } from '../types/components/FormState';
import type { Meter } from '../types/models/Meter';
import { IconPlus } from '@tabler/icons-vue';
import DataTable from './ui/DataTable.vue';
import useApi from '../use/api';
import { useAuthStore } from '../store/auth';
import type { RegistryList } from '../types/api/Registry';
import type { UnityProviderContract } from '../types/models/UnityProviderContract';
import type {
  UnityProviderContractRow,
  UnityProviderContractsTableProps,
} from '../types/components/LegalDashboard';
import type { TableColumn } from '../types/components/ui/DataTable';

const props = defineProps<UnityProviderContractsTableProps>();
const route = useRoute();
const auth = useAuthStore();
const deleteEndpoint = computed(() =>
  auth.canManageRentalRegistry && !props.dashboard ? '/api/unity-provider-contracts' : undefined,
);
const { api, errorText } = useApi();
const items = ref<UnityProviderContract[]>([]);
const page = ref(1);
const total = ref(0);
const loading = ref(false);
const error = ref('');
const providerId = ref<number | null>(null);
const unityId = ref<number | null>(null);
const status = ref('all');
const withMeters = ref(false);
const selectedContractId = ref<number | null>(null);
const selectedMeterId = ref<number | null>(null);
const readingMeter = ref<Meter | null>(null);
const readingModal = ref<Pick<FormState, 'saving'> | null>(null);
const filtered = computed(
  () => !!(providerId.value || unityId.value || status.value !== 'all' || withMeters.value),
);
const emptyText = computed(() =>
  props.dashboard && filtered.value
    ? 'Договоров по выбранным фильтрам не найдено.'
    : 'Договоры коммунальных поставщиков пока не внесены.',
);
const rowAction = computed(() =>
  props.dashboard
    ? (row: UnityProviderContractRow) => {
        selectedContractId.value = row.id;
      }
    : undefined,
);
const recordRoute = computed(() => (props.dashboard ? undefined : rowRoute));
const statusOptions: SelectOption[] = [
  { value: 'all', label: 'Все статусы' },
  ...Object.entries(unityProviderContractStatusLabels).map(([value, label]) => ({ value, label })),
];
let controller: AbortController | undefined;
const rows = computed<UnityProviderContractRow[]>(() =>
  items.value.map((item, index) => ({
    showObject: index === 0 || items.value[index - 1].object.id !== item.object.id,
    ...item,
    meters:
      props.dashboard && unityId.value
        ? item.meters.filter((meter) => meter.utility.id === unityId.value)
        : item.meters,
    communicationLabel: item.unities.map((unity) => unity.name).join(', ') || '—',
    meterColors: Object.fromEntries(
      item.meters.map((meter) => [
        meter.id,
        meter.verificationExpired || meter.readingAttention
          ? 'orange'
          : meter.readingSubmitted
            ? 'green'
            : 'secondary',
      ]),
    ),
    verificationLabels: Object.fromEntries(
      item.meters.map((meter) => [
        meter.id,
        meter.nextVerificationDate.split('-').reverse().join('.'),
      ]),
    ),
    statusLabel: unityProviderContractStatusLabels[item.status],
    statusColor: unityProviderContractStatusColors[item.status],
    expiryLabel: item.expiryDate.split('-').reverse().join('.'),
  })),
);
const createRoute = computed(() => ({
  path: '/unity-provider-contracts/new',
  query: {
    objectId: props.objectId ? String(props.objectId) : undefined,
    unityProviderId: props.unityProviderId ? String(props.unityProviderId) : undefined,
    returnTo: route.fullPath,
  },
}));
const columns = computed<TableColumn<UnityProviderContractRow>[]>(() =>
  props.dashboard
    ? [
        { key: 'object', label: 'Объект' },
        { key: 'communicationLabel', label: 'Коммуникация' },
        { key: 'unityProvider', label: 'Поставщик' },
        { key: 'contractNum', label: 'Номер' },
        { key: 'expiryLabel', label: 'Дата окончания' },
        { key: 'status', label: 'Статус' },
        { key: 'meters', label: 'Счетчик' },
      ]
    : [
        { key: 'object', label: 'Объект' },
        { key: 'unityProvider', label: 'Коммунальный поставщик' },
        { key: 'status', label: 'Статус договора' },
        { key: 'meters', label: 'Оборудование' },
        { key: 'unities', label: 'Следующая поверка' },
      ],
);

function rowRoute(row: UnityProviderContractRow) {
  return '/unity-provider-contracts/' + row.id;
}

function deleted() {
  if (items.value.length === 1 && page.value > 1) page.value--;

  void load();
}

function resetFilters() {
  providerId.value = null;
  unityId.value = null;
  status.value = 'all';
  withMeters.value = false;
  void load(1);
}

async function readingCreated() {
  readingMeter.value = null;
  await load();
}

async function load(value = page.value) {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  page.value = value;
  loading.value = true;
  error.value = '';

  try {
    const endpoint = props.objectId
      ? '/api/objects/' + props.objectId + '/unity-provider-contracts'
      : '/api/unity-provider-contracts';
    const params = new URLSearchParams({ page: String(page.value) });

    if (props.unityProviderId || providerId.value)
      params.set('unityProviderId', String(props.unityProviderId ?? providerId.value));

    if (props.dashboard) {
      params.set('status', status.value);

      if (unityId.value) params.set('unityId', String(unityId.value));

      if (withMeters.value) params.set('withMeters', '1');
    }

    const result = await api<RegistryList<UnityProviderContract>>(endpoint + '?' + params, {
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

onMounted(() => load());
onBeforeUnmount(() => controller?.abort());
onBeforeRouteLeave(() => !auth.user || !readingModal.value?.saving);
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
      @click="load()"
    >
      Повторить
    </button>
  </div>
  <DataTable
    :delete-endpoint="deleteEndpoint"
    @deleted="deleted"
    :rows="rows"
    :columns="columns"
    caption="Договоры коммунальных поставщиков"
    :loading="loading"
    :error="!!error"
    :page="page"
    :total="total"
    :row-route="recordRoute"
    :row-action="rowAction"
    :empty-text="emptyText"
    @page-change="load"
  >
    <template #header>
      <div class="w-100">
        <h2
          class="card-title"
          :class="{ 'mb-3': dashboard }"
        >
          Договоры коммунальных поставщиков
        </h2>
        <div
          v-if="dashboard"
          class="row g-2"
        >
          <div class="col-12 col-md-6 col-xl-3">
            <ReferenceSelect
              id="dashboard-provider-filter"
              v-model="providerId"
              label="Поставщик"
              endpoint="/api/unity-providers"
              placeholder="Все поставщики"
              :allow-create="false"
              :required="false"
              :validate-on-blur="false"
              @update:model-value="load(1)"
            />
          </div>
          <div class="col-12 col-md-6 col-xl-3">
            <ReferenceSelect
              id="dashboard-unity-filter"
              v-model="unityId"
              label="Коммуникация"
              endpoint="/api/unities"
              placeholder="Все коммуникации"
              :allow-create="false"
              :required="false"
              :validate-on-blur="false"
              @update:model-value="load(1)"
            />
          </div>
          <div class="col-12 col-md-6 col-xl-3">
            <label
              for="dashboard-status-filter"
              class="form-label"
            >
              Статус
            </label>
            <SelectInput
              id="dashboard-status-filter"
              v-model="status"
              label="Статус договора"
              :options="statusOptions"
              @update:model-value="load(1)"
            />
          </div>
          <div class="col-12 col-md-6 col-xl-3 supplier-contract-meter-filter">
            <label class="form-check form-switch mb-0">
              <input
                v-model="withMeters"
                type="checkbox"
                role="switch"
                class="form-check-input"
                @change="load(1)"
              />
              <span class="form-check-label">
                Со счетчиком
              </span>
            </label>
          </div>
        </div>
        <button
          v-if="dashboard && filtered"
          type="button"
          class="btn btn-sm mt-2"
          @click="resetFilters"
        >
          Сбросить фильтры
        </button>
      </div>
    </template>
    <template
      v-if="auth.canManageRentalRegistry && !dashboard"
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
      <span
        v-if="column.key === 'object'"
        :class="{ 'd-md-none': !row.showObject }"
      >
        {{ row.object.name }}
      </span>
      <template v-else-if="column.key === 'communicationLabel'">
        {{ row.communicationLabel }}
      </template>
      <template v-else-if="column.key === 'contractNum'">
        {{ row.contractNum ?? '—' }}
      </template>
      <template v-else-if="column.key === 'expiryLabel'">
        {{ row.expiryLabel }}
      </template>
      <template v-else-if="column.key === 'unityProvider'">
        <strong>
          {{ row.unityProvider.name }}
        </strong>
        <div
          v-if="!dashboard"
          class="text-secondary"
        >
          {{ row.contractNum }} · до {{ row.expiryLabel }}
        </div>
      </template>
      <Badge
        v-else-if="column.key === 'status'"
        :color="row.statusColor"
      >
        {{ row.statusLabel }}
      </Badge>
      <div
        v-else-if="column.key === 'meters'"
        class="d-flex flex-column align-items-start gap-2"
      >
        <div
          v-for="meter in row.meters"
          :key="meter.id"
          class="d-flex align-items-center flex-wrap gap-1"
        >
          <Badge
            v-if="dashboard"
            action
            :color="row.meterColors[meter.id]"
            wrap
            @click.stop="selectedMeterId = meter.id"
          >
            {{ meter.name }}
          </Badge>
          <Badge
            v-else
            :to="'/meters/' + meter.id"
            wrap
          >
            {{ meter.name }}
          </Badge>
          <button
            v-if="dashboard && auth.canEditMeterReadings"
            type="button"
            class="btn btn-sm"
            @click.stop="readingMeter = meter"
          >
            Показания
          </button>
        </div>
        <span
          v-if="!row.meters.length"
          class="text-secondary"
        >
          Нет счетчиков
        </span>
      </div>
      <div
        v-else-if="column.key === 'unities'"
        class="d-flex flex-column gap-2"
      >
        <span
          v-for="meter in row.meters"
          :key="meter.id"
          :class="{ 'text-danger': meter.verificationExpired }"
        >
          {{ row.verificationLabels[meter.id] }}
        </span>
        <span
          v-if="!row.meters.length"
          class="text-secondary"
        >
          —
        </span>
      </div>
    </template>
  </DataTable>
  <ContractDetailsModal
    v-if="selectedContractId !== null"
    kind="utility"
    :contract-id="selectedContractId"
    @close="selectedContractId = null"
  />
  <MeterDetailsModal
    v-if="selectedMeterId !== null"
    :meter-id="selectedMeterId"
    @close="selectedMeterId = null"
  />
  <MeterReadingCreateModal
    v-if="readingMeter"
    ref="readingModal"
    :meter="readingMeter"
    @close="readingMeter = null"
    @saved="readingCreated"
  />
</template>

<style scoped>
.supplier-contract-meter-filter {
  display: flex;
  align-items: center;
  min-height: 2.5rem;
}

@media (min-width: 768px) {
  .supplier-contract-meter-filter {
    padding-top: 1.75rem;
  }
}
</style>
