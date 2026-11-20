<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { RouterLink, onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import IntInput from '../components/ui/IntInput.vue';
import LandLordForm from '../components/LandLordForm.vue';
import LandLordCreateModal from '../components/LandLordCreateModal.vue';
import RentalObjectMeterSettings from '../components/RentalObjectMeterSettings.vue';
import type { FormState } from '../types/components/FormState';
import TextInput from '../components/ui/TextInput.vue';
import ReferenceInput from '../components/ui/ReferenceInput.vue';
import { useAuthStore } from '../store/auth';
import useApi, { ApiError } from '../use/api';
import useRentalObjectValidation from '../use/rentalObjectValidation';
import type { ApiErrors } from '../types/use/Api';
import type { RentalObjectDraft } from '../types/forms/RentalObject';
import type { RentalObject } from '../types/models/RentalObject';
import type { LandLord } from '../types/models/LandLord';
import type { RegistryProps, RentalObjectTab } from '../types/components/Registry';

const props = defineProps<RegistryProps>();
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const { api, errorText } = useApi();
const validation = useRentalObjectValidation();
const objects = computed(() => props.kind === 'objects');
const creating = computed(() => !route.params.id);
const activeTab = ref<RentalObjectTab>('main');
const mainTabButton = ref<HTMLButtonElement | null>(null);
const metersTabButton = ref<HTMLButtonElement | null>(null);
const mainTabActive = computed(() => creating.value || activeTab.value === 'main');
const metersTabActive = computed(() => activeTab.value === 'meters');
const mainPanelRole = computed(() => (creating.value ? undefined : 'tabpanel'));
const mainPanelLabel = computed(() => (creating.value ? undefined : 'object-main-tab'));
const mainTabIndex = computed(() => (mainTabActive.value ? 0 : -1));
const metersTabIndex = computed(() => (metersTabActive.value ? 0 : -1));
const basePath = computed(() => (objects.value ? '/objects' : '/objects/land-lords'));
const title = computed(() =>
  creating.value
    ? objects.value
      ? 'Добавить объект'
      : 'Добавить арендодателя'
    : (record.value?.name ?? 'Загрузка…'),
);
const saveLabel = computed(() =>
  saving.value ? 'Сохраняем…' : creating.value ? 'Создать' : 'Сохранить',
);
const form = reactive<RentalObjectDraft>({
  name: '',
  address: '',
  square: '',
  landLordId: null,
  readingDay: '25',
});
const record = ref<RentalObject | LandLord | null>(null);
const loading = ref(true);
const loadError = ref('');
const error = ref('');
const notice = ref('');
const saving = ref(false);
const original = ref('');
const serverErrors = ref<ApiErrors>({});
const modalOpen = ref(false);
const createdLandLord = ref<LandLord | null>(null);
const landLordForm = ref<FormState | null>(null);
const meterSettings = ref<FormState | null>(null);
const landLordRecord = computed(() =>
  record.value && !('address' in record.value) ? record.value : undefined,
);
const rentalObjectRecord = computed(() =>
  record.value && 'address' in record.value ? record.value : undefined,
);
const initialLandLord = computed(
  () =>
    createdLandLord.value ??
    (record.value && 'landLord' in record.value ? record.value.landLord : undefined),
);
const payload = computed(() =>
  objects.value
    ? {
        name: form.name.trim(),
        address: form.address.trim(),
        square: form.square.trim().replace(',', '.'),
        landLordId: form.landLordId,
        ...(!creating.value ? { readingDay: Number(form.readingDay) } : {}),
      }
    : { name: form.name.trim() },
);
const validationErrors = computed(() => {
  if (objects.value) return validation.rentalObject(form);

  const message = validation.required(form.name, 'Укажите наименование.');

  return message ? { name: message } : {};
});
const dirty = computed(() => JSON.stringify(payload.value) !== original.value);
const canSave = computed(
  () =>
    auth.canManageRentalRegistry &&
    dirty.value &&
    !loading.value &&
    !saving.value &&
    !modalOpen.value &&
    !meterSettings.value?.saving &&
    !Object.keys(validationErrors.value).length &&
    !Object.keys(serverErrors.value).length,
);
let controller: AbortController | undefined;

async function tabKeydown(event: KeyboardEvent) {
  if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;

  event.preventDefault();
  activeTab.value =
    event.key === 'Home'
      ? 'main'
      : event.key === 'End'
        ? 'meters'
        : mainTabActive.value
          ? 'meters'
          : 'main';
  await nextTick();

  if (mainTabActive.value) mainTabButton.value?.focus();
  else metersTabButton.value?.focus();
}

function clearServerErrors() {
  serverErrors.value = {};
  notice.value = '';
}

async function load() {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;
  loadError.value = '';

  try {
    if (!creating.value) {
      const result = await api<RentalObject | LandLord>(
        '/api/' + props.kind + '/' + route.params.id,
        { signal: current.signal },
      );

      if (current.signal.aborted) return;

      record.value = result;
      form.name = result.name;

      if ('address' in result) {
        form.address = result.address;
        form.square = result.square;
        form.landLordId = result.landLord.id;
        form.readingDay = String(result.readingDay);
      }
    }

    original.value = JSON.stringify(payload.value);
  } catch (e) {
    if (!current.signal.aborted) loadError.value = errorText(e);
  } finally {
    if (!current.signal.aborted) loading.value = false;
  }
}

async function save() {
  if (!canSave.value) return;

  saving.value = true;
  error.value = '';
  serverErrors.value = {};

  try {
    const result = await api<RentalObject | LandLord>(
      '/api/' + props.kind + (creating.value ? '' : '/' + route.params.id),
      {
        method: creating.value ? 'POST' : 'PUT',
        body: JSON.stringify(payload.value),
      },
    );

    original.value = JSON.stringify(payload.value);

    if (creating.value) {
      saving.value = false;
      await router.replace(basePath.value + '/' + result.id);

      return;
    }

    record.value = result;
    notice.value = 'Изменения сохранены.';
  } catch (e) {
    error.value = errorText(e);

    if (e instanceof ApiError) serverErrors.value = e.errors;
  } finally {
    saving.value = false;
  }
}

function landLordCreated(value: LandLord) {
  createdLandLord.value = value;
  form.landLordId = value.id;
  modalOpen.value = false;
  clearServerErrors();
  notice.value = 'Арендодатель «' + value.name + '» создан и выбран.';
}

async function landLordSaved(value: LandLord) {
  if (creating.value) {
    await router.replace(basePath.value + '/' + value.id);

    return;
  }

  record.value = value;
}

function meterSettingsUpdated(value: RentalObject) {
  record.value = value;
  form.readingDay = String(value.readingDay);
  original.value = JSON.stringify({ ...JSON.parse(original.value), readingDay: value.readingDay });
}

onBeforeRouteLeave(() => {
  const busy = objects.value
    ? saving.value || meterSettings.value?.saving
    : landLordForm.value?.saving;
  const changed = objects.value
    ? dirty.value || meterSettings.value?.dirty
    : landLordForm.value?.dirty;

  return (
    !busy &&
    (!auth.user ||
      !auth.canManageRentalRegistry ||
      !changed ||
      window.confirm('Есть несохранённые изменения. Покинуть страницу?'))
  );
});
onMounted(load);
onBeforeUnmount(() => controller?.abort());
</script>

<template>
  <div class="page-heading">
    <div>
      <div class="page-pretitle">
        Недвижимость
      </div>
      <h1>
        {{ title }}
      </h1>
    </div>
    <RouterLink
      :to="basePath"
      class="btn"
    >
      К списку
    </RouterLink>
  </div>
  <div
    v-if="loadError"
    class="alert alert-danger"
    role="alert"
  >
    {{ loadError }}
    <button
      type="button"
      class="btn btn-sm ms-2"
      @click="load"
    >
      Повторить
    </button>
  </div>
  <div
    v-else-if="loading"
    class="card card-body text-secondary"
    role="status"
  >
    Загрузка…
  </div>
  <LandLordForm
    v-else-if="!objects"
    ref="landLordForm"
    class="card"
    :initial="landLordRecord"
    @saved="landLordSaved"
  />
  <section
    v-else
    class="card"
  >
    <div
      v-if="!creating"
      class="card-header border-bottom-0"
    >
      <div
        class="nav nav-tabs card-header-tabs"
        role="tablist"
        aria-label="Разделы карточки объекта"
        @keydown="tabKeydown"
      >
        <button
          id="object-main-tab"
          ref="mainTabButton"
          type="button"
          class="nav-link"
          :class="{ active: mainTabActive }"
          role="tab"
          :aria-selected="mainTabActive"
          aria-controls="object-main-panel"
          :tabindex="mainTabIndex"
          @click="activeTab = 'main'"
        >
          Основное
        </button>
        <button
          id="object-meters-tab"
          ref="metersTabButton"
          type="button"
          class="nav-link"
          :class="{ active: metersTabActive }"
          role="tab"
          :aria-selected="metersTabActive"
          aria-controls="object-meters-panel"
          :tabindex="metersTabIndex"
          @click="activeTab = 'meters'"
        >
          Приборы учёта
        </button>
      </div>
    </div>
    <div class="tab-content">
      <form
        id="object-main-panel"
        :role="mainPanelRole"
        :aria-labelledby="mainPanelLabel"
        class="tab-pane"
        :class="{ active: mainTabActive }"
        novalidate
        @submit.prevent="save"
        @input="clearServerErrors"
        @change="clearServerErrors"
      >
        <div class="card-body">
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
          <div
            v-if="!auth.canManageRentalRegistry"
            class="alert alert-info"
          >
            Просмотр. Изменять данные могут администратор и юрист.
          </div>
          <div class="row g-3">
            <div class="col-12 col-lg-4">
              <TextInput
                id="property-name"
                v-model="form.name"
                label="Наименование"
                required
                :readonly="!auth.canManageRentalRegistry || saving"
                :error="serverErrors.name"
                :validation-error="validationErrors.name"
              />
            </div>
            <div class="col-12 col-lg-5">
              <div class="reference-with-add">
                <div class="reference-field">
                  <ReferenceInput
                    id="property-landlord"
                    v-model="form.landLordId"
                    label="Арендодатель"
                    endpoint="/api/land-lords"
                    :initial="initialLandLord"
                    :error="serverErrors.landLordId"
                    :disabled="!auth.canManageRentalRegistry || saving"
                    @update:model-value="clearServerErrors"
                  />
                </div>
                <button
                  v-if="auth.canManageRentalRegistry"
                  type="button"
                  class="btn reference-add"
                  :disabled="saving"
                  @click="modalOpen = true"
                >
                  Добавить
                </button>
              </div>
            </div>
            <div class="col-12 col-lg-3">
              <IntInput
                id="property-square"
                v-model="form.square"
                label="Сдаваемая площадь, м²"
                required
                :readonly="!auth.canManageRentalRegistry || saving"
                :error="serverErrors.square"
                :validation-error="validationErrors.square"
              />
            </div>
            <div class="col-12">
              <TextInput
                id="property-address"
                v-model="form.address"
                label="Адрес"
                required
                :readonly="!auth.canManageRentalRegistry || saving"
                :error="serverErrors.address"
                :validation-error="validationErrors.address"
              />
            </div>
          </div>
          <div
            v-if="auth.canManageRentalRegistry"
            class="d-flex flex-wrap gap-2 mt-3"
          >
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="!canSave"
            >
              {{ saveLabel }}
            </button>
            <RouterLink
              :to="basePath"
              class="btn"
            >
              Отмена
            </RouterLink>
          </div>
        </div>
      </form>
      <div
        v-if="rentalObjectRecord"
        id="object-meters-panel"
        class="tab-pane"
        :class="{ active: metersTabActive }"
        role="tabpanel"
        aria-labelledby="object-meters-tab"
      >
        <RentalObjectMeterSettings
          ref="meterSettings"
          :object="rentalObjectRecord"
          :disabled="saving"
          @updated="meterSettingsUpdated"
        />
      </div>
    </div>
  </section>
  <LandLordCreateModal
    v-if="modalOpen"
    @created="landLordCreated"
    @close="modalOpen = false"
  />
</template>
