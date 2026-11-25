<script setup lang="ts">
import useCreateNavigation from '../use/createNavigation';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { IconPlus } from '@tabler/icons-vue';
import Badge from './ui/Badge.vue';
import DataTable from './ui/DataTable.vue';
import ActionMenu from './ui/ActionMenu.vue';
import MeterDetachModal from './MeterDetachModal.vue';
import MeterDetailsModal from './MeterDetailsModal.vue';
import MeterReadingCreateModal from './MeterReadingCreateModal.vue';
import { useAuthStore } from '../store/auth';
import useApi from '../use/api';
import { meterReadingDate } from '../use/meterReading';
import type {
  ContractMetersProps,
  ContractMeterRow,
  ContractMeterAction,
} from '../types/components/ContractMeters';
import type { FormState } from '../types/components/FormState';
import type { Meter } from '../types/models/Meter';
import type { RegistryList } from '../types/api/Registry';
import type { ActionMenuItem } from '../types/components/ui/ActionMenu';
import type { TableColumn } from '../types/components/ui/DataTable';

const { fromTable } = useCreateNavigation();
const props = defineProps<ContractMetersProps>();
const auth = useAuthStore();
const { api, errorText } = useApi();
const items = ref<Meter[]>([]);
const total = ref(0);
const page = ref(1);
const loading = ref(false);
const error = ref('');
const selectedMeterId = ref<number | null>(null);
const readingMeter = ref<Meter | null>(null);
const readingModal = ref<Pick<FormState, 'saving'> | null>(null);
const detachMeter = ref<Meter | null>(null);
const detachModal = ref<Pick<FormState, 'saving'> | null>(null);
const saving = computed(() => !!(readingModal.value?.saving || detachModal.value?.saving));
const actions = computed<ActionMenuItem<ContractMeterAction>[]>(() => [
  ...(auth.canEditMeterReadings ? [{ value: 'readings' as const, label: 'Показания' }] : []),
  ...(auth.canInstallMeters ? [{ value: 'detach' as const, label: 'Снять' }] : []),
]);
const rows = computed<ContractMeterRow[]>(() =>
  items.value.map((meter) => ({
    ...meter,
    verificationLabel: meterReadingDate(meter.nextVerificationDate),
    readingLabel: meter.nextReadingDueDate ? meterReadingDate(meter.nextReadingDueDate) : '—',
    badgeColor:
      meter.verificationExpired || meter.readingAttention
        ? 'orange'
        : meter.readingSubmitted
          ? 'green'
          : 'secondary',
  })),
);
const createRoute = computed(() =>
  fromTable({
    path: '/meters/new',
    query: {
      objectId: String(props.objectId),
      spaceId: String(props.spaceId),
    },
  }),
);
const columns: TableColumn<ContractMeterRow>[] = [
  { key: 'utility', label: 'Коммуникация', value: (row) => row.utility.name },
  { key: 'serialNumber', label: 'Номер', value: (row) => row.serialNumber ?? '—' },
  { key: 'verificationLabel', label: 'Дата поверки', value: (row) => row.verificationLabel },
  {
    key: 'readingLabel',
    label: 'Ближайшая дата снятия показаний',
    value: (row) => row.readingLabel,
  },
  { key: 'name', label: 'Счетчик' },
];
let controller: AbortController | undefined;

async function load() {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;
  error.value = '';

  try {
    const result = await api<RegistryList<Meter>>(
      '/api/meters?' +
        new URLSearchParams({
          objectId: String(props.objectId),
          spaceId: String(props.spaceId),
          state: 'installed',
          page: String(page.value),
        }),
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

function changePage(value: number) {
  page.value = value;
  void load();
}

function act(action: ContractMeterAction, meter: Meter) {
  if (saving.value || loading.value) return;

  if (action === 'readings' && auth.canEditMeterReadings) readingMeter.value = meter;
  else if (action === 'detach' && auth.canInstallMeters) detachMeter.value = meter;
}

async function detached() {
  detachMeter.value = null;

  if (items.value.length === 1 && page.value > 1) page.value--;

  await load();
}

async function readingCreated() {
  readingMeter.value = null;
  await load();
}

onMounted(load);
onBeforeUnmount(() => controller?.abort());
defineExpose({ saving });
</script>

<template>
  <div
    v-if="error"
    class="alert alert-danger m-3"
    role="alert"
  >
    {{ error }}
    <button
      type="button"
      class="btn ms-2"
      @click="load"
    >
      Повторить
    </button>
  </div>
  <DataTable
    :rows="rows"
    :columns="columns"
    caption="Счетчики"
    empty-text="Установленных счетчиков нет."
    :loading="loading"
    :error="!!error"
    :total="total"
    :page="page"
    @page-change="changePage"
  >
    <template
      v-if="auth.canInstallMeters"
      #actions
    >
      <RouterLink
        :to="createRoute"
        class="btn btn-primary list-add-button"
        title="Добавить"
        aria-label="Добавить счетчик"
      >
        <IconPlus :size="18" />
        <span class="list-add-label">
          Добавить
        </span>
      </RouterLink>
    </template>
    <template
      v-if="actions.length"
      #rowActions="{ row }"
    >
      <ActionMenu
        label="Действия"
        :items="actions"
        floating
        :disabled="saving || loading"
        @select="act($event, row)"
      />
    </template>
    <template #cell="{ row, column }">
      <div
        v-if="column.key === 'name'"
        class="d-flex align-items-center flex-wrap gap-1"
      >
        <Badge
          action
          :color="row.badgeColor"
          wrap
          @click="selectedMeterId = row.id"
        >
          {{ row.name }}
        </Badge>
      </div>
      <template v-else>
        {{ column.value?.(row) }}
      </template>
    </template>
  </DataTable>
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
  <MeterDetachModal
    v-if="detachMeter"
    ref="detachModal"
    :meter="detachMeter"
    @close="detachMeter = null"
    @updated="detached"
  />
</template>
