<script setup lang="ts">
import FormBackButton from '../components/ui/FormBackButton.vue';
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute, onBeforeRouteLeave } from 'vue-router';
import ReferenceSelect from '../components/ui/ReferenceSelect.vue';
import TextInput from '../components/ui/TextInput.vue';
import useApi, { ApiError } from '../use/api';
import useCreateNavigation, { creationReturnPath } from '../use/createNavigation';
import { dateError } from '../use/meterValidation';
import { useAuthStore } from '../store/auth';
import type { RegistryList } from '../types/api/Registry';
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
const unityIds = ref<number[]>([]);
const unities = ref<NamedRecord[]>([]);
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
  unityIds: unityIds.value,
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
    !disabled.value &&
    !!objectId.value &&
    !!providerId.value &&
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
  unityIds.value = value.unities.map((item) => item.id);
  original.value = JSON.stringify(payload.value);
}

async function load() {
  try {
    unities.value = (await api<RegistryList<NamedRecord>>('/api/unities')).items;

    if (editing.value)
      assign(await api<UnityProviderContract>('/api/unity-provider-contracts/' + route.params.id));
    else {
      if (objectId.value)
        initialObject.value = await api<NamedRecord>('/api/objects/' + objectId.value);

      if (providerId.value)
        initialProvider.value = await api<NamedRecord>('/api/unity-providers/' + providerId.value);
    }
  } catch (e) {
    error.value = errorText(e);
  } finally {
    loading.value = false;
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
            :disabled="disabled"
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
            :disabled="disabled"
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
        <fieldset class="col-12">
          <legend class="form-label">
            Коммуникации
          </legend>
          <div class="d-flex flex-wrap gap-3">
            <label
              v-for="unity in unities"
              :key="unity.id"
              class="form-check"
            >
              <input
                v-model="unityIds"
                :value="unity.id"
                type="checkbox"
                class="form-check-input"
                :disabled="disabled"
                @change="clearErrors"
              />
              <span class="form-check-label">
                {{ unity.name }}
              </span>
            </label>
          </div>
          <div
            v-if="errors.unityIds"
            class="invalid-feedback d-block"
          >
            {{ errors.unityIds }}
          </div>
        </fieldset>
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
