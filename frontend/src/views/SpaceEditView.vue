<script setup lang="ts">
import FormBackButton from '../components/ui/FormBackButton.vue';
import useCreateNavigation from '../use/createNavigation';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink, onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import TextInput from '../components/ui/TextInput.vue';
import MeterTable from '../components/MeterTable.vue';
import ContractTable from '../components/ContractTable.vue';
import { useAuthStore } from '../store/auth';
import useApi, { ApiError } from '../use/api';
import type { Space } from '../types/models/Space';
import type { Contract } from '../types/models/Contract';
import type { SpaceTab } from '../types/components/SpaceTabs';
import type { RegistryList } from '../types/api/Registry';

const { returnAfterSave } = useCreateNavigation();
const route = useRoute();
const router = useRouter();
const activeTab = ref<SpaceTab>(
  route.query.tab === 'meters' ? 'meters' : route.query.tab === 'contracts' ? 'contracts' : 'main',
);
const tabs = computed(() =>
  [
    { id: 'main' as const, label: 'Основное' },
    { id: 'meters' as const, label: 'Счётчики' },
    { id: 'contracts' as const, label: 'Договоры' },
  ].map((tab) => ({
    ...tab,
    active: activeTab.value === tab.id,
    tabIndex: activeTab.value === tab.id ? 0 : -1,
  })),
);
const mainTabActive = computed(() => activeTab.value === 'main');
const metersTabActive = computed(() => activeTab.value === 'meters');
const contractsTabActive = computed(() => activeTab.value === 'contracts');
const auth = useAuthStore();
const { api, errorText } = useApi();
const record = ref<Space>();
const name = ref('');
const original = ref('');
const loading = ref(true);
const saving = ref(false);
const loadError = ref('');
const error = ref('');
const nameError = ref('');
const notice = ref('');
const contracts = ref<Contract[]>([]);
const contractsLoading = ref(false);
const contractsError = ref('');
const total = ref(0);
const page = ref(1);
const title = computed(() => record.value?.name ?? 'Загрузка…');
const objectRoute = computed(() =>
  record.value ? '/objects/' + record.value.object.id : '/objects',
);
const validationError = computed(() => (name.value.trim() ? '' : 'Укажите название.'));
const dirty = computed(() => name.value.trim() !== original.value);
const disabled = computed(() => !auth.canManageRentalRegistry || saving.value);
const canSave = computed(
  () => !disabled.value && dirty.value && !validationError.value && !nameError.value,
);
const statusLabel = computed(() =>
  record.value?.active ? 'Занято / зарезервировано' : 'Освобождено',
);
let controller: AbortController | undefined;

async function selectTab(tab: SpaceTab) {
  activeTab.value = tab;

  await router.replace({ query: { ...route.query, tab } });
}

async function tabKeydown(event: KeyboardEvent) {
  if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;

  event.preventDefault();

  const current = tabs.value.findIndex((tab) => tab.id === activeTab.value);
  const index =
    event.key === 'Home'
      ? 0
      : event.key === 'End'
        ? tabs.value.length - 1
        : (current + (event.key === 'ArrowRight' ? 1 : -1) + tabs.value.length) % tabs.value.length;
  const list = event.currentTarget;

  await selectTab(tabs.value[index].id);
  await nextTick();

  if (list instanceof HTMLElement)
    list.querySelectorAll<HTMLButtonElement>('[role="tab"]')[index]?.focus();
}

function clearError() {
  nameError.value = '';
  notice.value = '';
}

async function loadContracts() {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  contractsLoading.value = true;
  contractsError.value = '';

  try {
    const result = await api<RegistryList<Contract>>(
      '/api/contracts?' +
        new URLSearchParams({ spaceId: String(route.params.id), page: String(page.value) }),
      { signal: current.signal },
    );

    if (current.signal.aborted) return;

    contracts.value = result.items;
    total.value = result.total;
  } catch (e) {
    if (!current.signal.aborted) contractsError.value = errorText(e);
  } finally {
    if (!current.signal.aborted) contractsLoading.value = false;
  }
}

async function load() {
  loading.value = true;
  loadError.value = '';

  try {
    record.value = await api<Space>('/api/spaces/' + route.params.id);
    name.value = record.value.name;
    original.value = record.value.name;
    await loadContracts();
  } catch (e) {
    loadError.value = errorText(e);
  } finally {
    loading.value = false;
  }
}

async function contractDeleted() {
  const space = await api<Space | null>('/api/spaces/' + route.params.id).catch((e) => {
    if (e instanceof ApiError && e.status === 404) return null;

    throw e;
  });

  if (!space) {
    await router.replace(objectRoute.value + '?tab=spaces');

    return;
  }

  record.value = space;

  if (contracts.value.length === 1 && page.value > 1) page.value--;

  await loadContracts();
}

function changePage(value: number) {
  page.value = value;
  void loadContracts();
}

async function save() {
  if (!canSave.value) return;

  saving.value = true;
  error.value = '';

  try {
    record.value = await api<Space>('/api/spaces/' + route.params.id, {
      method: 'PUT',
      body: JSON.stringify({ name: name.value.trim() }),
    });
    original.value = record.value.name;
    notice.value = 'Название сохранено.';
    saving.value = false;
    await returnAfterSave();
  } catch (e) {
    error.value = errorText(e);

    if (e instanceof ApiError) nameError.value = e.errors.name ?? '';
  } finally {
    saving.value = false;
  }
}

onBeforeRouteLeave(() => !auth.user || !saving.value);
onMounted(load);
onBeforeUnmount(() => controller?.abort());
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>
        {{ title }}
      </h1>
    </div>
    <RouterLink
      :to="objectRoute"
      class="btn"
    >
      К объекту
    </RouterLink>
  </div>
  <p
    v-if="loading"
    role="status"
  >
    Загрузка…
  </p>
  <div
    v-else-if="loadError"
    class="alert alert-danger"
    role="alert"
  >
    {{ loadError }}
    <button
      class="btn ms-2"
      @click="load"
    >
      Повторить
    </button>
  </div>
  <template v-else-if="record">
    <div
      v-if="error"
      class="alert alert-danger"
      role="alert"
    >
      {{ error }}
    </div>
    <div
      v-if="notice"
      class="alert alert-success"
      role="status"
    >
      {{ notice }}
    </div>
    <section class="card">
      <div class="card-header border-bottom-0">
        <div
          class="nav nav-tabs card-header-tabs"
          role="tablist"
          aria-label="Разделы карточки помещения"
          @keydown="tabKeydown"
        >
          <button
            v-for="tab in tabs"
            :id="'space-' + tab.id + '-tab'"
            :key="tab.id"
            type="button"
            class="nav-link"
            :class="{ active: tab.active }"
            role="tab"
            :aria-selected="tab.active"
            :aria-controls="'space-' + tab.id + '-panel'"
            :tabindex="tab.tabIndex"
            @click="selectTab(tab.id)"
          >
            {{ tab.label }}
          </button>
        </div>
      </div>
      <div class="tab-content">
        <form
          id="space-main-panel"
          class="tab-pane"
          :class="{ active: mainTabActive }"
          role="tabpanel"
          aria-labelledby="space-main-tab"
          novalidate
          @submit.prevent="save"
        >
          <div class="card-body">
            <div class="d-flex flex-wrap gap-3 mb-3">
              <span>
                {{ record.object.name }}
              </span>
              <span>
                {{ record.square }} м²
              </span>
              <span>
                {{ statusLabel }}
              </span>
            </div>
            <TextInput
              id="space-name"
              v-model="name"
              label="Наименование"
              required
              :readonly="disabled"
              :error="nameError"
              :validation-error="validationError"
              @input="clearError"
            />
            <div class="d-flex flex-wrap gap-2 mt-3">
              <button
                v-if="auth.canManageRentalRegistry"
                type="submit"
                class="btn btn-primary"
                :disabled="!canSave"
              >
                Сохранить
              </button>
              <FormBackButton :to="objectRoute + '?tab=spaces'" />
            </div>
          </div>
        </form>
        <div
          id="space-meters-panel"
          class="tab-pane"
          :class="{ active: metersTabActive }"
          role="tabpanel"
          aria-labelledby="space-meters-tab"
        >
          <MeterTable
            :object-id="record.object.id"
            :space-id="record.id"
          />
        </div>
        <div
          id="space-contracts-panel"
          class="tab-pane"
          :class="{ active: contractsTabActive }"
          role="tabpanel"
          aria-labelledby="space-contracts-tab"
        >
          <div
            v-if="contractsError"
            class="alert alert-danger mt-3"
            role="alert"
          >
            {{ contractsError }}
            <button
              class="btn ms-2"
              @click="loadContracts"
            >
              Повторить
            </button>
          </div>
          <ContractTable
            :items="contracts"
            :total="total"
            :page="page"
            :loading="contractsLoading"
            :error="!!contractsError"
            @page-change="changePage"
            @deleted="contractDeleted"
          >
            <template #header>
              <h2 class="card-title">
                История договоров помещения
              </h2>
            </template>
          </ContractTable>
        </div>
      </div>
    </section>
  </template>
</template>
