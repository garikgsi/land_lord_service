<script setup lang="ts">
import useCreateNavigation from '../use/createNavigation';
import { computed, nextTick, onMounted, ref } from 'vue';
import { RouterLink, onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import RenterForm from '../components/RenterForm.vue';
import RenterContacts from '../components/RenterContacts.vue';
import { useAuthStore } from '../store/auth';
import useApi from '../use/api';
import type { Renter } from '../types/models/Renter';
import type { FormState } from '../types/components/FormState';
import type { RenterTab } from '../types/components/RenterForm';

const { returnAfterCreate, returnAfterSave } = useCreateNavigation();
const route = useRoute();
const auth = useAuthStore();
const router = useRouter();
const { api, errorText } = useApi();
const creating = computed(() => !route.params.id);
const record = ref<Renter>();
const loading = ref(true);
const error = ref('');
const form = ref<FormState | null>(null);
const activeTab = computed<RenterTab>(() =>
  !creating.value && route.query.tab === 'contacts' ? 'contacts' : 'main',
);
const mainTabButton = ref<HTMLButtonElement | null>(null);
const contactsTabButton = ref<HTMLButtonElement | null>(null);
const contacts = ref<InstanceType<typeof RenterContacts> | null>(null);
const mainTabActive = computed(() => creating.value || activeTab.value === 'main');
const contactsTabActive = computed(() => !creating.value && activeTab.value === 'contacts');
const mainTabIndex = computed(() => (mainTabActive.value ? 0 : -1));
const contactsTabIndex = computed(() => (contactsTabActive.value ? 0 : -1));
const mainPanelRole = computed(() => (record.value ? 'tabpanel' : undefined));
const mainPanelLabel = computed(() => (record.value ? 'renter-main-tab' : undefined));
const title = computed(() =>
  creating.value ? 'Добавить арендатора' : (record.value?.name ?? 'Загрузка…'),
);

async function selectTab(tab: RenterTab) {
  await router.replace({ query: { ...route.query, tab } });

  if (tab === 'contacts') await contacts.value?.reload();
}

async function tabKeydown(event: KeyboardEvent) {
  if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;

  event.preventDefault();
  const tab: RenterTab =
    event.key === 'Home'
      ? 'main'
      : event.key === 'End'
        ? 'contacts'
        : mainTabActive.value
          ? 'contacts'
          : 'main';

  await selectTab(tab);
  await nextTick();

  if (tab === 'main') mainTabButton.value?.focus();
  else contactsTabButton.value?.focus();
}

async function load() {
  loading.value = true;
  error.value = '';

  try {
    if (!creating.value) record.value = await api<Renter>('/api/renters/' + route.params.id);
  } catch (e) {
    error.value = errorText(e);
  } finally {
    loading.value = false;
  }
}

async function saved(value: Renter) {
  record.value = value;

  if (creating.value) await returnAfterCreate('/renters');
  else await returnAfterSave();
}

onBeforeRouteLeave(() => !auth.user || !form.value?.saving);
onMounted(load);
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>
        {{ title }}
      </h1>
    </div>
    <RouterLink
      to="/renters"
      class="btn"
    >
      К списку
    </RouterLink>
  </div>
  <p
    v-if="loading"
    role="status"
  >
    Загрузка…
  </p>
  <div
    v-else-if="error"
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
  <section
    v-else
    class="card"
  >
    <div
      v-if="record"
      class="card-header border-bottom-0"
    >
      <div
        class="nav nav-tabs card-header-tabs"
        role="tablist"
        aria-label="Разделы карточки арендатора"
        @keydown="tabKeydown"
      >
        <button
          id="renter-main-tab"
          ref="mainTabButton"
          type="button"
          class="nav-link"
          :class="{ active: mainTabActive }"
          role="tab"
          :aria-selected="mainTabActive"
          aria-controls="renter-main-panel"
          :tabindex="mainTabIndex"
          @click="selectTab('main')"
        >
          Основное
        </button>
        <button
          id="renter-contacts-tab"
          ref="contactsTabButton"
          type="button"
          class="nav-link"
          :class="{ active: contactsTabActive }"
          role="tab"
          :aria-selected="contactsTabActive"
          aria-controls="renter-contacts-panel"
          :tabindex="contactsTabIndex"
          @click="selectTab('contacts')"
        >
          Контакты
        </button>
      </div>
    </div>
    <div class="tab-content">
      <div
        class="tab-pane"
        :class="{ active: mainTabActive }"
        id="renter-main-panel"
        :role="mainPanelRole"
        :aria-labelledby="mainPanelLabel"
      >
        <RenterForm
          ref="form"
          :initial="record"
          @saved="saved"
        />
      </div>
      <div
        v-if="record"
        class="tab-pane"
        :class="{ active: contactsTabActive }"
        id="renter-contacts-panel"
        role="tabpanel"
        aria-labelledby="renter-contacts-tab"
      >
        <RenterContacts
          ref="contacts"
          :renter-id="record.id"
        />
      </div>
    </div>
  </section>
</template>
