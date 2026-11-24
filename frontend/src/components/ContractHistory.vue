<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import DataTable from './ui/DataTable.vue';
import useApi from '../use/api';
import type { ContractHistory } from '../types/models/ContractHistory';
import type { ContractHistoryProps, ContractHistoryRow } from '../types/components/ContractHistory';
import type { RegistryList } from '../types/api/Registry';
import type { TableColumn } from '../types/components/ui/DataTable';

const props = defineProps<ContractHistoryProps>();
const { api, errorText } = useApi();
const entries = ref<ContractHistory[]>([]);
const page = ref(1);
const total = ref(0);
const loading = ref(false);
const error = ref('');
const titles = {
  created: 'Создание договора',
  amendment: 'Изменение договора',
  termination: 'Расторжение договора',
  indexation: 'Индексация договора',
};
const columns = computed<TableColumn<ContractHistoryRow>[]>(() => [
  { key: 'title', label: 'Событие' },
  { key: 'dateLabel', label: 'Дата' },
  ...(entries.value.some((entry) => entry.type !== 'created')
    ? [{ key: 'changes' as const, label: 'Изменения' }]
    : []),
]);
const labels = {
  contractNum: 'Номер',
  contractDate: 'Начало аренды',
  expiryDate: 'Окончание аренды',
  monthlyPayment: 'Ежемесячный платёж, ₽',
  securityPayment: 'Обеспечительный платёж, ₽',
  annulmentDate: 'Дата аннулирования',
  increaseDate: 'Дата индексации',
  increasePercent: 'Индексация, %',
  increaseIntervalMonths: 'Периодичность, месяцев',
  lastIndexationDate: 'Выполненная индексация',
  renter: 'Арендатор',
  landLord: 'Арендодатель',
  contractTypeId: 'Тип договора',
};
const rows = computed<ContractHistoryRow[]>(() =>
  entries.value.map((entry) => ({
    ...entry,
    title: titles[entry.type],
    dateLabel: entry.validFrom
      ? new Date(entry.validFrom).toLocaleDateString('ru-RU')
      : 'Дата неизвестна',
    changes:
      entry.type === 'created'
        ? []
        : Object.entries(labels).flatMap(([key, label]) => {
            const field = key as keyof typeof labels;
            const before = entry.previousData[field];
            const after = entry.nextData[field];

            return JSON.stringify(before) === JSON.stringify(after)
              ? []
              : [label + ': ' + display(before, field) + ' → ' + display(after, field)];
          }),
  })),
);
let controller: AbortController | undefined;

function display(
  value: ContractHistory['previousData'][keyof ContractHistory['previousData']],
  field: keyof typeof labels,
) {
  if (value === null || value === undefined) return '—';

  if (field.endsWith('Date') && typeof value === 'string')
    return value.split('-').reverse().join('.');

  return typeof value === 'object' ? value.name : String(value);
}

async function load(nextPage = page.value) {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;
  error.value = '';

  try {
    const result = await api<RegistryList<ContractHistory>>(
      '/api/contracts/' + props.contractId + '/history?page=' + nextPage,
      { signal: current.signal },
    );

    if (current.signal.aborted) return;

    entries.value = result.items;
    total.value = result.total;
    page.value = result.page;
  } catch (e) {
    if (!current.signal.aborted) error.value = errorText(e);
  } finally {
    if (!current.signal.aborted) loading.value = false;
  }
}

onMounted(() => load());
onBeforeUnmount(() => controller?.abort());
defineExpose({ reload: () => load(1) });
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
    :rows="rows"
    :columns="columns"
    caption="История изменений"
    :loading="loading"
    :error="!!error"
    :page="page"
    :total="total"
    @page-change="load"
  >
    <template #cell="{ row, column }">
      <template v-if="column.key === 'changes'">
        <div
          v-for="change in row.changes"
          :key="change"
        >
          {{ change }}
        </div>
      </template>
      <template v-else>
        {{ row[column.key as 'title' | 'dateLabel'] }}
      </template>
    </template>
  </DataTable>
</template>
