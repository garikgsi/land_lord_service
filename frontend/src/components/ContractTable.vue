<script setup lang="ts">
import Badge from './ui/Badge.vue';
import { computed } from 'vue';
import { useAuthStore } from '../store/auth';
import DataTable from './ui/DataTable.vue';
import { contractStatusColors, contractStatusLabels } from '../store/contracts';
import type { ContractTableProps, ContractTableRow } from '../types/components/ContractTable';
import type { TableColumn } from '../types/components/ui/DataTable';

const auth = useAuthStore();
const deleteEndpoint = computed(() =>
  auth.canManageRentalRegistry ? '/api/contracts' : undefined,
);
const props = defineProps<ContractTableProps>();
const emit = defineEmits<{ pageChange: [page: number]; deleted: [] }>();
const rows = computed<ContractTableRow[]>(() =>
  props.items.map((record) => ({
    ...record,
    period: record.contractDate + ' — ' + record.effectiveEnd,
    payment: record.monthlyPayment + ' ₽',
    statusLabel: contractStatusLabels[record.status],
    statusColor: contractStatusColors[record.status],
  })),
);
const columns: TableColumn<ContractTableRow>[] = [
  { key: 'contractNum', label: 'Договор' },
  { key: 'space', label: 'Объект / помещение' },
  { key: 'renter', label: 'Арендатор' },
  { key: 'period', label: 'Период аренды' },
  { key: 'payment', label: 'В месяц' },
  { key: 'status', label: 'Статус' },
];

function deletable(row: ContractTableRow) {
  return !row.isSigned;
}

function rowRoute(row: ContractTableRow) {
  return '/contracts/' + row.id;
}

function rowLabel(row: ContractTableRow) {
  return 'Открыть договор ' + row.contractNum;
}
</script>

<template>
  <DataTable
    :delete-endpoint="deleteEndpoint"
    :delete-allowed="deletable"
    @deleted="emit('deleted')"
    :rows="rows"
    :columns="columns"
    caption="Договоры"
    :total="total"
    :page="page"
    :loading="loading"
    :error="error"
    :row-route="rowRoute"
    :row-label="rowLabel"
    empty-text="Договоров пока нет."
    @page-change="emit('pageChange', $event)"
  >
    <template
      v-if="$slots.header"
      #header
    >
      <slot name="header">
      </slot>
    </template>
    <template
      v-if="$slots.actions"
      #actions
    >
      <slot name="actions">
      </slot>
    </template>
    <template #cell="{ row, column }">
      <template v-if="column.key === 'contractNum'">
        <div class="fw-semibold">
          {{ row.contractNum }}
        </div>
        <div class="text-secondary small">
          {{ row.contractType.name }}
        </div>
      </template>
      <template v-else-if="column.key === 'space'">
        <div class="fw-semibold">
          {{ row.space.object.name }}
        </div>
        <div class="text-secondary small">
          {{ row.space.name }}
        </div>
      </template>
      <template v-else-if="column.key === 'renter'">
        {{ row.renter.name }}
      </template>
      <template v-else-if="column.key === 'period'">
        {{ row.period }}
      </template>
      <template v-else-if="column.key === 'payment'">
        {{ row.payment }}
      </template>
      <Badge
        v-else-if="column.key === 'status'"
        :color="row.statusColor"
      >
        {{ row.statusLabel }}
      </Badge>
    </template>
  </DataTable>
</template>
