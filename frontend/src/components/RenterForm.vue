<script setup lang="ts">
import FormBackButton from './ui/FormBackButton.vue';
import { computed, ref } from 'vue';
import TextInput from './ui/TextInput.vue';
import IntInput from './ui/IntInput.vue';
import ReferenceSelect from './ui/ReferenceSelect.vue';
import useApi, { ApiError } from '../use/api';
import { useAuthStore } from '../store/auth';
import type { Renter } from '../types/models/Renter';
import type { RenterFormProps } from '../types/components/RenterForm';
import type { ApiErrors } from '../types/use/Api';

const props = defineProps<RenterFormProps>();
const emit = defineEmits<{ saved: [record: Renter]; cancel: [] }>();
const auth = useAuthStore();
const { api, errorText } = useApi();
const name = ref(props.initial?.name ?? '');
const inn = ref(props.initial?.inn ?? '');
const ownershipId = ref<number | null>(props.initial?.ownership.id ?? null);
const saving = ref(false);
const error = ref('');
const notice = ref('');
const errors = ref<ApiErrors>({});
const nameColumnClass = computed(() => (props.modal ? 'col-12 col-md-5' : 'col-12 col-lg-6'));
const ownershipColumnClass = computed(() =>
  props.modal ? 'col-12 col-md-4' : 'col-12 col-md-6 col-lg-3',
);
const innColumnClass = computed(() =>
  props.modal ? 'col-12 col-md-3' : 'col-12 col-md-6 col-lg-3',
);
const prefix = computed(() => (props.modal ? 'modal-renter' : 'renter'));
const nameId = computed(() => prefix.value + '-name');
const innId = computed(() => prefix.value + '-inn');
const ownershipIdField = computed(() => prefix.value + '-ownership');
const payload = computed(() => ({
  name: name.value.trim(),
  inn: inn.value.trim() || null,
  ownershipId: ownershipId.value ?? 0,
}));
const original = ref(JSON.stringify(payload.value));
const nameError = computed(() => (name.value.trim() ? '' : 'Укажите наименование.'));
const innError = computed(() =>
  !inn.value || /^(?:[0-9]{10}|[0-9]{12})$/.test(inn.value)
    ? ''
    : 'ИНН должен содержать 10 или 12 цифр.',
);
const dirty = computed(() => JSON.stringify(payload.value) !== original.value);
const ownershipError = computed(
  () =>
    errors.value.ownershipId ||
    (dirty.value && !ownershipId.value ? 'Выберите форму собственности.' : ''),
);
const innMessage = computed(() => errors.value.inn || innError.value);
const disabled = computed(() => !auth.canManageRentalRegistry || saving.value);
const canSave = computed(
  () =>
    !disabled.value &&
    dirty.value &&
    !nameError.value &&
    !innError.value &&
    (ownershipId.value ?? 0) > 0 &&
    !Object.keys(errors.value).length,
);
const saveLabel = computed(() =>
  saving.value ? 'Сохраняем…' : props.initial ? 'Сохранить' : 'Создать',
);

function clearErrors() {
  errors.value = {};
  notice.value = '';
}

async function save() {
  if (!canSave.value) return;

  saving.value = true;
  error.value = '';

  try {
    const record = await api<Renter>(
      '/api/renters' + (props.initial ? '/' + props.initial.id : ''),
      {
        method: props.initial ? 'PUT' : 'POST',
        body: JSON.stringify(payload.value),
      },
    );

    original.value = JSON.stringify(payload.value);
    notice.value = 'Изменения сохранены.';
    saving.value = false;
    emit('saved', record);
  } catch (e) {
    error.value = errorText(e);

    if (e instanceof ApiError) errors.value = e.errors;
  } finally {
    saving.value = false;
  }
}

defineExpose({ dirty, saving });
</script>

<template>
  <form
    novalidate
    @submit.prevent="save"
    @input="clearErrors"
    @change="clearErrors"
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
        v-if="notice && !modal"
        class="alert alert-success"
        role="status"
      >
        {{ notice }}
      </div>
      <div class="row g-3">
        <div :class="nameColumnClass">
          <TextInput
            :id="nameId"
            v-model="name"
            label="Наименование"
            required
            :readonly="disabled"
            :error="errors.name"
            :validation-error="nameError"
          />
        </div>
        <div :class="ownershipColumnClass">
          <ReferenceSelect
            :id="ownershipIdField"
            v-model="ownershipId"
            label="Форма собственности"
            placeholder="Выберите форму собственности"
            endpoint="/api/ownerships"
            :initial="initial?.ownership"
            :disabled="disabled"
            :error="ownershipError"
            @update:model-value="clearErrors"
          />
        </div>
        <div :class="innColumnClass">
          <IntInput
            :id="innId"
            v-model="inn"
            label="ИНН"
            maxlength="12"
            :readonly="disabled"
            :error="innMessage"
            :validation-error="innError"
          />
        </div>
      </div>
      <div
        v-if="auth.canManageRentalRegistry || !modal"
        class="d-flex flex-wrap gap-2 mt-3"
      >
        <button
          v-if="auth.canManageRentalRegistry"
          type="submit"
          class="btn btn-primary"
          :disabled="!canSave"
        >
          {{ saveLabel }}
        </button>
        <button
          v-if="modal"
          type="button"
          class="btn"
          :disabled="saving"
          @click="emit('cancel')"
        >
          Отмена
        </button>
        <FormBackButton
          v-else
          to="/renters"
        />
      </div>
    </div>
  </form>
</template>
