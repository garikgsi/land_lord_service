<script setup lang="ts">
import Badge from './ui/Badge.vue';
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { IconPlus } from '@tabler/icons-vue';
import DataTable from './ui/DataTable.vue';
import useApi from '../use/api';
import {
  meterReadingDate,
  meterReadingMonth,
  meterReadingBadge,
  pendingMeterReadingBadge,
} from '../use/meterReading';
import { useAuthStore } from '../store/auth';
import type { MeterReading, MeterVerification } from '../types/models/Meter';
import type { MeterRecordsProps, MeterRecordRow } from '../types/components/MeterRecords';
import type { RegistryList } from '../types/api/Registry';
import type { TableColumn } from '../types/components/ui/DataTable';

const props = defineProps<MeterRecordsProps>();
const emit = defineEmits<{ createReading: [] }>();
const { api, errorText } = useApi();
const auth = useAuthStore();
const rows = ref<MeterRecordRow[]>([]);
const total = ref(0);
const page = ref(1);
const loading = ref(false);
const error = ref('');
const isReadings = computed(() => props.kind === 'readings');
const canAdd = computed(() =>
  isReadings.value ? auth.canEditMeterReadings : auth.canManageMeters,
);
const title = computed(() => (isReadings.value ? 'Показания и потребление' : 'История поверок'));
const base = computed(() => '/meters/' + props.meterId + '/' + props.kind);
const columns = computed<TableColumn<MeterRecordRow>[]>(() =>
  isReadings.value
    ? [
        { key: 'date', label: 'Дата снятия' },
        { key: 'previousValue', label: 'Предыдущее' },
        { key: 'value', label: 'Текущее' },
        { key: 'consumption', label: 'Потребление' },
        { key: 'month', label: 'Месяц' },
        { key: 'statusLabel', label: 'Статус' },
      ]
    : [{ key: 'date', label: 'Дата поверки' }],
);
const rowNavigation = computed(() => (isReadings.value ? rowRoute : undefined));
const pageSize = 20;
const pendingBadge = computed(() =>
  isReadings.value ? pendingMeterReadingBadge(props.meter) : null,
);

function rowRoute(row: MeterRecordRow) {
  return base.value + '/' + row.id;
}

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const result = await api<RegistryList<MeterReading | MeterVerification>>(
      '/api' + base.value + '?page=' + page.value,
    );

    rows.value = result.items.map((row) => {
      const badge = meterReadingBadge('meterRead' in row ? row.submissionStatus : null);

      return {
        id: row.id,
        date: meterReadingDate(
          'meterReadingDate' in row ? row.meterReadingDate : row.verificationDate,
        ),
        previousValue: 'meterRead' in row ? (row.previousRead ?? '') : '',
        value: 'meterRead' in row ? row.meterRead : '',
        consumption: 'meterRead' in row ? (row.consumption ?? '') : '',
        month: 'meterReadingDate' in row ? meterReadingMonth(row.meterReadingDate) : '',
        statusLabel: badge.label,
        statusColor: badge.color,
        deadlineLabel:
          'meterRead' in row && row.readingDueDate ? meterReadingDate(row.readingDueDate) : '',
      };
    });
    total.value = result.total;
  } catch (e) {
    error.value = errorText(e);
  } finally {
    loading.value = false;
  }
}

function changePage(value: number) {
  page.value = value;
  void load();
}

onMounted(load);
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
    :rows="rows"
    :columns="columns"
    :caption="title"
    :total="total"
    :page="page"
    :page-size="pageSize"
    :client-pagination="!isReadings"
    :loading="loading"
    :error="!!error"
    :row-route="rowNavigation"
    @page-change="changePage"
  >
    <template #header>
      <div class="d-flex flex-wrap align-items-center gap-2">
        <h2 class="card-title">
          {{ title }}
        </h2>
        <Badge
          v-if="pendingBadge"
          :title="pendingBadge.title"
          role="status"
          :color="pendingBadge.color"
        >
          {{ pendingBadge.label }}
        </Badge>
      </div>
    </template>
    <template #cell="{ row, column }">
      <Badge
        v-if="column.key === 'statusLabel' && row.statusColor"
        :title="row.deadlineLabel"
        :color="row.statusColor"
      >
        {{ row.statusLabel }}
      </Badge>
      <template v-else>
        {{ row[column.key as keyof MeterRecordRow] }}
      </template>
    </template>
    <template
      v-if="canAdd"
      #actions
    >
      <button
        v-if="isReadings"
        type="button"
        class="btn btn-primary list-add-button"
        aria-label="Добавить запись"
        title="Добавить запись"
        @click="emit('createReading')"
      >
        <IconPlus :size="18" />
        <span class="list-add-label">
          Добавить
        </span>
      </button>
      <RouterLink
        v-else
        :to="base + '/new'"
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
</template>
