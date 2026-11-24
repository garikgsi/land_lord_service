<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import DataTable from './ui/DataTable.vue';
import Badge from './ui/Badge.vue';
import ContractActions from './ContractActions.vue';
import { useAuthStore } from '../store/auth';
import { contractStatusColors, contractStatusLabels } from '../store/contracts';
import type { Contract } from '../types/models/Contract';
import type {
  LegalContractRow,
  RentalContractsTableProps,
} from '../types/components/LegalDashboard';
import type { TableColumn } from '../types/components/ui/DataTable';

const props = withDefaults(defineProps<RentalContractsTableProps>(), { showObject: true });
const emit = defineEmits<{
  pageChange: [page: number];
  updated: [contract: Contract];
  preview: [contract: Contract];
}>();
const previewRow = computed(() =>
  props.preview ? (row: LegalContractRow) => emit('preview', row) : undefined,
);
const recordRoute = computed(() => (props.preview ? undefined : rowRoute));
const auth = useAuthStore();
const route = useRoute();
const rows = computed<LegalContractRow[]>(() =>
  props.items.map((item, index) => ({
    ...item,
    showObject: index === 0 || props.items[index - 1].space.object.id !== item.space.object.id,
    objectLabel: item.space.object.name,
    renterLabel: item.renter.name,
    squareLabel: item.space.square,
    statusLabel: contractStatusLabels[item.status],
    statusColor: contractStatusColors[item.status],
    paymentLabel: item.monthlyPayment.replace('.', ',') + ' ₽',
    expiryLabel: item.effectiveEnd.split('-').reverse().join('.'),
    indexationLabel:
      (item.attentionDates?.indexation ?? item.increaseDate)?.split('-').reverse().join('.') ?? '—',
    expiryAttention: !!item.attentionDates?.expiry,
    indexationAttention: !!item.attentionDates?.indexation,
    percentLabel:
      item.increasePercent === null ? '—' : item.increasePercent.replace('.', ',') + ' %',
  })),
);
const columns = computed<TableColumn<LegalContractRow>[]>(() => [
  ...(props.showObject ? [{ key: 'objectLabel' as const, label: 'Объект' }] : []),
  { key: 'renterLabel', label: 'Арендатор' },
  { key: 'squareLabel', label: 'Площадь, м²' },
  { key: 'paymentLabel', label: 'Аренда в месяц' },
  { key: 'expiryLabel', label: 'Конец договора' },
  { key: 'indexationLabel', label: 'Индексация' },
  { key: 'percentLabel', label: '% индексации' },
  { key: 'statusLabel', label: 'Статус', class: 'rental-contract-status' },
  ...(props.showActions && auth.canManageRentalRegistry
    ? [{ key: 'actions' as const, label: 'Действия' }]
    : []),
]);

function rowRoute(row: LegalContractRow) {
  return '/contracts/' + row.id;
}
</script>

<template>
  <DataTable
    :rows="rows"
    :columns="columns"
    :caption="caption"
    :loading="loading"
    :error="error"
    :total="total"
    :page="page"
    :row-route="recordRoute"
    :row-action="previewRow"
    empty-text="Договоров пока нет."
    @page-change="emit('pageChange', $event)"
  >
    <template #header>
      <slot name="header">
        <h2 class="card-title">
          {{ caption }}
        </h2>
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
      <span
        v-if="column.key === 'objectLabel'"
        :class="{ 'd-md-none': !row.showObject }"
      >
        {{ row.objectLabel }}
      </span>
      <Badge
        v-else-if="column.key === 'expiryLabel' && row.expiryAttention"
        color="orange"
      >
        {{ row.expiryLabel }}
      </Badge>
      <Badge
        v-else-if="column.key === 'indexationLabel' && row.indexationAttention"
        color="orange"
      >
        {{ row.indexationLabel }}
      </Badge>
      <Badge
        v-else-if="column.key === 'statusLabel'"
        :color="row.statusColor"
        wrap
      >
        {{ row.statusLabel }}
      </Badge>
      <ContractActions
        v-else-if="column.key === 'actions' && row.isSigned"
        :contract="row"
        table
        @updated="emit('updated', $event)"
      />
      <RouterLink
        v-else-if="column.key === 'actions'"
        :to="{ path: '/contracts/' + row.id, query: { returnTo: route.fullPath } }"
        class="btn"
      >
        Изменить
      </RouterLink>
      <template v-else>
        {{ column.value?.(row) ?? row[column.key as keyof LegalContractRow] }}
      </template>
    </template>
  </DataTable>
</template>

<style>
.rental-contract-status {
  min-width: 9rem;
}
</style>
