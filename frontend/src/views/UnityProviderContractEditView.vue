<script setup lang="ts">
import MeterSelect from '../components/MeterSelect.vue';
import FormBackButton from '../components/ui/FormBackButton.vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink, useRoute, onBeforeRouteLeave } from 'vue-router';
import ReferenceSelect from '../components/ui/ReferenceSelect.vue';
import TextInput from '../components/ui/TextInput.vue';
import useApi, { ApiError } from '../use/api';
import useCreateNavigation, { creationReturnPath } from '../use/createNavigation';
import { dateError } from '../use/meterValidation';
import { useAuthStore } from '../store/auth';
import type { Unity } from '../types/models/Unity';
import type { NamedRecord } from '../types/models/NamedRecord';
import type { UnityProviderContract } from '../types/models/UnityProviderContract';

const route = useRoute();
const auth = useAuthStore();
const { api, errorText } = useApi();
const { returnAfterCreate, returnAfterSave } = useCreateNavigation();
const record = ref<UnityProviderContract>();
const objectRoute = computed(() =>
  creationReturnPath(
    route.query.returnTo,
    objectId.value ? '/objects/' + objectId.value + '?tab=utilities' : '/objects',
  ),
);
const editing = computed(() => !!route.params.id);
const objectId = ref<number | null>(Number(route.query.objectId) || null);
const providerId = ref<number | null>(Number(route.query.unityProviderId) || null);
const initialProvider = ref<NamedRecord>();
const contractNum = ref('');
const contractDate = ref('');
const expiryDate = ref('');
const isSuspended = ref(false);
const unityId = ref<number | null>(null);
const meterId = ref<number | null>(null);
const initialUnity = ref<NamedRecord>();
const selectedUnity = ref<Unity>();
const unityLoading = ref(false);
const showMeter = computed(() => !!(selectedUnity.value?.useMeter || meterId.value));
let unityController: AbortController | undefined;
const initialMeter = computed(() =>
  record.value?.meters.find(
    (meter) => meter.id === meterId.value && meter.utility.id === unityId.value,
  ),
);
const meterEndpointKey = computed(() => String(unityId.value));
const meterDisabled = computed(
  () => disabled.value || unityLoading.value || !auth.canInstallMeters,
);
const relationDisabled = computed(
  () => disabled.value || (!!record.value?.meterId && !auth.canInstallMeters),
);
const initialObject = ref<NamedRecord>();
const original = ref('');
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const errors = ref<Record<string, string>>({});
const notice = ref('');
const payload = computed(() => ({
  objectId: objectId.value,
  unityProviderId: providerId.value,
  contractNum: contractNum.value.trim() || null,
  contractDate: contractDate.value || null,
  expiryDate: expiryDate.value,
  isSuspended: isSuspended.value,
  unityIds: unityId.value ? [unityId.value] : [],
  meterId: meterId.value,
}));
const validation = computed(
  () =>
    dateError(expiryDate.value) ||
    (contractDate.value
      ? dateError(contractDate.value) ||
        (contractDate.value > expiryDate.value ? 'Дата договора позже окончания.' : '')
      : ''),
);
const disabled = computed(() => !auth.canManageRentalRegistry || saving.value);
const canSave = computed(
  () =>
    !loading.value &&
    !unityLoading.value &&
    !disabled.value &&
    !!objectId.value &&
    !!providerId.value &&
    !!unityId.value &&
    !validation.value &&
    !Object.values(errors.value).some(Boolean) &&
    JSON.stringify(payload.value) !== original.value,
);
const title = computed(() =>
  editing.value
    ? 'Договор поставщика ' + (record.value?.contractNum ?? '')
    : 'Добавить договор поставщика',
);
const saveLabel = computed(() =>
  saving.value ? 'Сохраняем…' : editing.value ? 'Сохранить' : 'Создать',
);

function clearErrorsFromInput(event: Event) {
  if (event.target instanceof HTMLInputElement && event.target.type === 'checkbox') return;

  clearErrors();
}

function clearErrors() {
  errors.value = {};
}

function assign(value: UnityProviderContract) {
  record.value = value;
  objectId.value = value.object.id;
  providerId.value = value.unityProvider.id;
  initialObject.value = value.object;
  initialProvider.value = value.unityProvider;
  contractNum.value = value.contractNum ?? '';
  contractDate.value = value.contractDate ?? '';
  expiryDate.value = value.expiryDate;
  isSuspended.value = value.isSuspended;
  unityId.value = value.unities.length === 1 ? value.unities[0].id : null;
  initialUnity.value = value.unities.length === 1 ? value.unities[0] : undefined;
  meterId.value = value.meterId;
  original.value = JSON.stringify(payload.value);
}

async function load() {
  try {
    if (editing.value)
      assign(await api<UnityProviderContract>('/api/unity-provider-contracts/' + route.params.id));
    else {
      if (objectId.value)
        initialObject.value = await api<NamedRecord>('/api/objects/' + objectId.value);

      if (providerId.value)
        initialProvider.value = await api<NamedRecord>('/api/unity-providers/' + providerId.value);
    }

    await loadUnity();
  } catch (e) {
    error.value = errorText(e);
  } finally {
    loading.value = false;
  }
}

function unityChanged() {
  meterId.value = null;
  clearErrors();
  void loadUnity();
}

async function loadUnity() {
  unityController?.abort();
  selectedUnity.value = undefined;

  if (!unityId.value) {
    unityLoading.value = false;

    return;
  }

  const current = new AbortController();

  unityController = current;
  unityLoading.value = true;

  try {
    const unity = await api<Unity>('/api/unities/' + unityId.value, { signal: current.signal });

    if (!current.signal.aborted) selectedUnity.value = unity;
  } catch (e) {
    if (!current.signal.aborted) {
      error.value = errorText(e);
      errors.value.unityIds = 'Не удалось загрузить коммуникацию.';
    }
  } finally {
    if (!current.signal.aborted) unityLoading.value = false;
  }
}

async function save() {
  if (!canSave.value) return;

  saving.value = true;
  error.value = '';

  try {
    const value = await api<UnityProviderContract>(
      '/api/unity-provider-contracts' + (editing.value ? '/' + route.params.id : ''),
      { method: editing.value ? 'PUT' : 'POST', body: JSON.stringify(payload.value) },
    );

    saving.value = false;

    if (!editing.value) {
      await returnAfterCreate(objectRoute.value);

      return;
    }

    assign(value);
    notice.value = 'Изменения сохранены.';
    await returnAfterSave();
  } catch (e) {
    error.value = errorText(e);

    if (e instanceof ApiError) errors.value = e.errors;
  } finally {
    saving.value = false;
  }
}

onBeforeRouteLeave(() => !auth.user || !saving.value);
onMounted(load);
onBeforeUnmount(() => unityController?.abort());
</script>

<template>
  <div class="page-heading">
    <h1>
      {{ title }}
    </h1>
    <RouterLink
      :to="objectRoute"
      class="btn"
    >
      К списку
    </RouterLink>
  </div>
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
  <p
    v-if="loading"
    role="status"
  >
    Загрузка…
  </p>
  <section
    v-else
    class="card"
  >
    <form
      class="card-body"
      novalidate
      @submit.prevent="save"
      @input="clearErrorsFromInput"
      @change="clearErrors"
    >
      <div class="row g-3 align-items-start">
        <div class="col-12 col-md-6">
          <ReferenceSelect
            id="supplier-contract-object"
            v-model="objectId"
            label="Объект"
            endpoint="/api/objects"
            :initial="initialObject"
            :disabled="relationDisabled"
            :error="errors.objectId"
            @update:model-value="clearErrors"
          />
        </div>
        <div class="col-12 col-md-6">
          <ReferenceSelect
            id="supplier-contract-provider"
            v-model="providerId"
            label="Поставщик"
            endpoint="/api/unity-providers"
            :initial="initialProvider"
            :disabled="relationDisabled"
            :error="errors.unityProviderId"
            @update:model-value="clearErrors"
          />
        </div>
        <div class="col-12 col-md-4">
          <TextInput
            id="supplier-contract-number"
            v-model="contractNum"
            label="Номер"
            :readonly="disabled"
            :error="errors.contractNum"
            maxlength="255"
          />
        </div>
        <div class="col-12 col-md-4">
          <TextInput
            id="supplier-contract-date"
            v-model="contractDate"
            label="Дата договора"
            type="date"
            :readonly="disabled"
            :error="errors.contractDate"
          />
        </div>
        <div class="col-12 col-md-4">
          <TextInput
            id="supplier-contract-expiry"
            v-model="expiryDate"
            label="Действует до"
            type="date"
            required
            :readonly="disabled"
            :error="errors.expiryDate"
            :validation-error="validation"
          />
        </div>
        <div class="col-12 col-md-6">
          <ReferenceSelect
            id="supplier-contract-unity"
            v-model="unityId"
            label="Коммуникация"
            endpoint="/api/unities"
            :initial="initialUnity"
            :disabled="relationDisabled"
            :error="errors.unityIds"
            @update:model-value="unityChanged"
          />
        </div>
        <div
          v-if="showMeter"
          class="col-12 col-md-6"
        >
          <MeterSelect
            :key="meterEndpointKey"
            id="supplier-contract-meter"
            v-model="meterId"
            :utility-id="unityId"
            :initial="initialMeter"
            :disabled="meterDisabled"
            :error="errors.meterId"
            @update:model-value="clearErrors"
          />
        </div>
        <div class="col-12">
          <label class="form-check">
            <input
              v-model="isSuspended"
              type="checkbox"
              class="form-check-input"
              :disabled="disabled"
            />
            <span class="form-check-label">
              Договор приостановлен
            </span>
          </label>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-4">
        <button
          v-if="auth.canManageRentalRegistry"
          class="btn btn-primary"
          type="submit"
          :disabled="!canSave"
        >
          {{ saveLabel }}
        </button>
        <FormBackButton :to="objectRoute" />
      </div>
    </form>
  </section>
</template>
