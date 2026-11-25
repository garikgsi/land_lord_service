<script setup lang="ts">
import useCreateNavigation from '../use/createNavigation';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { IconPlus } from '@tabler/icons-vue';
import ContractTable from '../components/ContractTable.vue';
import DateRangeInput from '../components/ui/DateRangeInput.vue';
import ReferenceSelect from '../components/ui/ReferenceSelect.vue';
import { useAuthStore } from '../store/auth';
import useApi from '../use/api';
import type { Contract } from '../types/models/Contract';
import type { NamedRecord } from '../types/models/NamedRecord';
import type { RegistryList } from '../types/api/Registry';

const { fromTable } = useCreateNavigation();
const route = useRoute();
const auth = useAuthStore();
const { api, errorText } = useApi();
const items = ref<Contract[]>([]);
const query = ref('');
const objectId = ref<number | null>(Number(route.query.objectId) || null);
const initialObject = ref<NamedRecord>();
const expiryFrom = ref('');
const expiryTo = ref('');
const status = ref('all');
const page = ref(1);
const total = ref(0);
const loading = ref(false);
const error = ref('');
const filterError = computed(() =>
  expiryFrom.value && expiryTo.value && expiryFrom.value > expiryTo.value
    ? 'Начало периода не может быть позже окончания.'
    : '',
);
const createRoute = computed(() =>
  fromTable({
    path: '/contracts/new',
    query: objectId.value ? { objectId: String(objectId.value) } : {},
  }),
);
let controller: AbortController | undefined;
let timer: ReturnType<typeof setTimeout> | undefined;

async function load() {
  controller?.abort();

  if (filterError.value) {
    loading.value = false;

    return;
  }

  const current = new AbortController();
  const params = new URLSearchParams({
    query: query.value,
    page: String(page.value),
    status: status.value,
  });

  if (objectId.value) params.set('objectId', String(objectId.value));

  if (expiryFrom.value) params.set('expiryFrom', expiryFrom.value);

  if (expiryTo.value) params.set('expiryTo', expiryTo.value);

  controller = current;
  loading.value = true;
  error.value = '';

  try {
    const result = await api<RegistryList<Contract>>('/api/contracts?' + params, {
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

function searchChanged() {
  clearTimeout(timer);
  page.value = 1;
  timer = setTimeout(load, 300);
}

function filterChanged() {
  clearTimeout(timer);
  page.value = 1;
  void load();
}

function changePage(value: number) {
  page.value = value;
  void load();
}

function resetFilters() {
  query.value = '';
  status.value = 'all';
  objectId.value = null;
  expiryFrom.value = '';
  expiryTo.value = '';
  filterChanged();
}

onMounted(async () => {
  if (objectId.value) {
    try {
      initialObject.value = await api<NamedRecord>('/api/objects/' + objectId.value);
    } catch (e) {
      error.value = errorText(e);
    }
  }

  await load();
});
onBeforeUnmount(() => {
  controller?.abort();
  clearTimeout(timer);
});
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>
        Договоры
      </h1>
      <p>
        Реестр по объектам, статусам и периоду действия.
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
      class="btn ms-2"
      @click="load"
    >
      Повторить
    </button>
  </div>
  <section class="card">
    <ContractTable
      @deleted="load"
      :items="items"
      :total="total"
      :page="page"
      :loading="loading"
      :error="!!error"
      @page-change="changePage"
    >
      <template #header>
        <div class="w-100">
          <input
            v-model="query"
            type="search"
            class="form-control"
            aria-label="Поиск договоров"
            placeholder="Номер, арендатор или помещение"
            maxlength="120"
            @input="searchChanged"
          />
          <div class="row g-3 mt-0">
            <div class="col-12 col-lg-5">
              <ReferenceSelect
                id="contracts-object-filter"
                v-model="objectId"
                label="Объект"
                endpoint="/api/objects"
                :required="false"
                :allow-create="false"
                :validate-on-blur="false"
                :initial="initialObject"
                @update:model-value="filterChanged"
              />
            </div>
            <div class="col-12 col-lg-3">
              <label
                for="contracts-status"
                class="form-label"
              >
                Статус
              </label>
              <select
                id="contracts-status"
                v-model="status"
                class="form-select"
                @change="filterChanged"
              >
                <option value="all">
                  Все статусы
                </option>
                <option value="active">
                  Действует
                </option>
                <option value="upcoming">
                  Ожидается
                </option>
                <option value="finished">
                  Завершён
                </option>
                <option value="cancelled">
                  Отменён
                </option>
              </select>
            </div>
            <div class="col-12 col-lg-4">
              <DateRangeInput
                id="contracts-period"
                v-model:from="expiryFrom"
                v-model:to="expiryTo"
                label="Период действия"
                @change="filterChanged"
              />
            </div>
            <div class="col-12">
              <div
                v-if="filterError"
                class="text-danger mb-2"
                role="alert"
              >
                {{ filterError }}
              </div>
              <button
                type="button"
                class="btn btn-sm"
                @click="resetFilters"
              >
                Сбросить фильтры
              </button>
            </div>
          </div>
        </div>
      </template>
      <template
        v-if="auth.canManageRentalRegistry"
        #actions
      >
        <RouterLink
          :to="createRoute"
          class="btn btn-primary list-add-button"
          aria-label="Добавить договор"
          title="Добавить договор"
        >
          <IconPlus :size="18" />
          <span class="list-add-label">
            Добавить
          </span>
        </RouterLink>
      </template>
    </ContractTable>
  </section>
</template>
