<script setup lang="ts">
import useCreateNavigation from '../use/createNavigation';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import SelectInput from './ui/SelectInput.vue';
import ContractDetailsModal from './ContractDetailsModal.vue';
import ContractInterventionActions from './ContractInterventionActions.vue';
import { useAuthStore } from '../store/auth';
import type { SelectOption } from '../types/components/ui/SelectInput';
import DataTable from './ui/DataTable.vue';
import useApi from '../use/api';
import type {
  ContractIntervention,
  ContractInterventionReason,
} from '../types/models/ContractIntervention';
import type { ContractInterventionRow } from '../types/components/ContractInterventionsTable';
import type { RegistryList } from '../types/api/Registry';
import type { TableColumn } from '../types/components/ui/DataTable';

const { fromTable } = useCreateNavigation();
const { api, errorText } = useApi();
const auth = useAuthStore();
const selected = ref<ContractIntervention>();
const action = ref('all');
const contractType = ref('all');
const actionOptions: SelectOption[] = [
  { value: 'all', label: 'Все действия' },
  { value: 'indexation', label: 'Индексация' },
  { value: 'expiry', label: 'Продление договора' },
];
const typeOptions: SelectOption[] = [
  { value: 'all', label: 'Все типы' },
  { value: 'rental', label: 'Аренда' },
  { value: 'utility', label: 'Коммуналка' },
];
const filtered = computed(
  () => !!(query.value || action.value !== 'all' || contractType.value !== 'all'),
);
const items = ref<ContractIntervention[]>([]);
const page = ref(1);
const total = ref(0);
const query = ref('');
const loading = ref(false);
const error = ref('');
const reasonLabels: Record<ContractInterventionReason, string> = {
  expiry: 'Продление договора',
  indexation: 'Индексация',
};
const rows = computed<ContractInterventionRow[]>(() =>
  items.value.map((item) => ({
    ...item,
    dateLabel: item.interventionDate.split('-').reverse().join('.'),
    reasonLabel: reasonLabels[item.reason],
    partyLabel: item.renter?.name ?? item.unityProvider?.name ?? '—',
    contractTypeLabel: item.contractType === 'rental' ? 'Аренда' : 'Коммуналка',
    editPath:
      (item.contractType === 'rental' ? '/contracts/' : '/unity-provider-contracts/') +
      item.contractId,
    dateClass: item.overdue ? 'text-danger fw-bold' : 'fw-bold',
  })),
);
const columns = computed<TableColumn<ContractInterventionRow>[]>(() => [
  { key: 'interventionDate', label: 'Дата вмешательства' },
  { key: 'reason', label: 'Причина' },
  { key: 'contractNum', label: 'Договор' },
  { key: 'object', label: 'Объект / помещение' },
  { key: 'partyLabel', label: 'Арендатор / поставщик' },
  ...(auth.canManageRentalRegistry ? [{ key: 'actions' as const, label: 'Действия' }] : []),
]);
let controller: AbortController | undefined;
let timer: ReturnType<typeof setTimeout> | undefined;

function preview(row: ContractInterventionRow) {
  selected.value = row;
}

function applyFilters() {
  clearTimeout(timer);
  page.value = 1;
  void load();
}

function resetFilters() {
  query.value = '';
  action.value = 'all';
  contractType.value = 'all';
  applyFilters();
}

async function load() {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;
  error.value = '';

  try {
    const result = await api<RegistryList<ContractIntervention>>(
      '/api/contracts/interventions?' +
        new URLSearchParams({
          page: String(page.value),
          query: query.value,
          action: action.value,
          contractType: contractType.value,
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

function search() {
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
    caption="Договоры: требуют внимания"
    :loading="loading"
    :error="!!error"
    :total="total"
    :page="page"
    :row-action="preview"
    empty-text="Договоров для вмешательства нет."
    @page-change="changePage"
  >
    <template #header>
      <div class="w-100">
        <h2 class="card-title mb-3">
          Договоры: требуют внимания
        </h2>
        <div class="row g-2">
          <div class="col-12 col-md-6 col-xl-3">
            <label
              for="intervention-action"
              class="form-label"
            >
              Действие
            </label>
            <SelectInput
              id="intervention-action"
              v-model="action"
              label="Действие"
              :options="actionOptions"
              @update:model-value="applyFilters"
            />
          </div>
          <div class="col-12 col-md-6 col-xl-3">
            <label
              for="intervention-type"
              class="form-label"
            >
              Тип договора
            </label>
            <SelectInput
              id="intervention-type"
              v-model="contractType"
              label="Тип договора"
              :options="typeOptions"
              @update:model-value="applyFilters"
            />
          </div>
          <div class="col-12 col-xl-6">
            <label
              for="intervention-search"
              class="form-label"
            >
              Поиск
            </label>
            <input
              id="intervention-search"
              v-model="query"
              type="search"
              class="form-control"
              placeholder="Договор, объект, арендатор или поставщик"
              @input="search"
            />
          </div>
          <div
            v-if="filtered"
            class="col-12"
          >
            <button
              type="button"
              class="btn"
              @click="resetFilters"
            >
              Сбросить фильтры
            </button>
          </div>
        </div>
      </div>
    </template>
    <template #cell="{ row, column }">
      <template v-if="column.key === 'interventionDate'">
        <span :class="row.dateClass">
          {{ row.dateLabel }}
        </span>
        <div
          v-if="row.overdue"
          class="text-danger"
        >
          Просрочено
        </div>
      </template>
      <template v-else-if="column.key === 'reason'">
        {{ row.reasonLabel }}
      </template>
      <template v-else-if="column.key === 'contractNum'">
        {{ row.contractNum }}
        <div class="text-secondary">
          {{ row.contractTypeLabel }}
        </div>
      </template>
      <template v-else-if="column.key === 'object'">
        {{ row.object.name }}
        <div
          v-if="row.space"
          class="text-secondary"
        >
          {{ row.space.name }}
        </div>
      </template>
      <template v-else-if="column.key === 'partyLabel'">
        {{ row.partyLabel }}
      </template>
      <ContractInterventionActions
        v-else-if="column.key === 'actions' && row.isSigned"
        :key="row.id"
        :contract-id="row.contractId"
        @updated="load"
      />
      <RouterLink
        v-else-if="column.key === 'actions'"
        :to="fromTable(row.editPath)"
        class="btn"
      >
        Изменить
      </RouterLink>
    </template>
  </DataTable>
  <ContractDetailsModal
    v-if="selected"
    :key="selected.id"
    :kind="selected.contractType"
    :contract-id="selected.contractId"
    @close="selected = undefined"
  />
</template>
