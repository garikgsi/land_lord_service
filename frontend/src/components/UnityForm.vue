<script setup lang="ts">
import ModalFormActions from './ui/ModalFormActions.vue';
import FormBackButton from './ui/FormBackButton.vue';
import { computed, ref } from 'vue';
import TextInput from './ui/TextInput.vue';
import { useAuthStore } from '../store/auth';
import useApi, { ApiError } from '../use/api';
import type { Unity } from '../types/models/Unity';
import type { UnityFormProps } from '../types/components/UnityForm';

const props = defineProps<UnityFormProps>();
const emit = defineEmits<{ saved: [record: Unity]; cancel: [] }>();
const auth = useAuthStore();
const { api, errorText } = useApi();
const name = ref(props.initial?.name ?? '');
const unit = ref(props.initial?.unit ?? '');
const useMeter = ref(props.initial?.useMeter ?? props.meterOnly ?? false);
const saving = ref(false);
const error = ref('');
const errors = ref<Record<string, string>>({});
const payload = computed(() => ({
  name: name.value.trim(),
  unit: unit.value.trim() || null,
  useMeter: useMeter.value,
}));
const original = ref(JSON.stringify(payload.value));
const dirty = computed(() => JSON.stringify(payload.value) !== original.value);
const nameValidation = computed(() => (name.value.trim() ? '' : 'Укажите наименование.'));
const disabled = computed(() => saving.value || !auth.canManageMeters);
const canSave = computed(
  () =>
    !disabled.value &&
    dirty.value &&
    !nameValidation.value &&
    !Object.values(errors.value).some(Boolean),
);
const saveLabel = computed(() =>
  saving.value ? 'Сохраняем…' : props.initial ? 'Сохранить' : 'Создать',
);
const bodyClass = computed(() => (props.modal ? 'modal-body' : 'card-body'));
const actionsClass = computed(() => (props.modal ? 'modal-footer' : 'd-flex flex-wrap gap-2 mt-3'));

function clearErrorsFromInput(event: Event) {
  if (event.target instanceof HTMLInputElement && event.target.type === 'checkbox') return;

  clearErrors();
}

function clearErrors() {
  errors.value = {};
}

async function save() {
  if (!canSave.value) return;

  saving.value = true;
  error.value = '';

  try {
    const record = await api<Unity>(
      '/api/unities' + (props.initial ? '/' + props.initial.id : ''),
      {
        method: props.initial ? 'PUT' : 'POST',
        body: JSON.stringify(payload.value),
      },
    );

    name.value = record.name;
    original.value = JSON.stringify(payload.value);
    saving.value = false;
    emit('saved', record);
  } catch (e) {
    error.value = errorText(e);

    if (e instanceof ApiError) errors.value = e.errors;
  } finally {
    saving.value = false;
  }
}

defineExpose({ saving, dirty });
</script>

<template>
  <form
    novalidate
    @submit.prevent="save"
    @input="clearErrorsFromInput"
    @change="clearErrors"
  >
    <div :class="bodyClass">
      <div
        v-if="error"
        class="alert alert-danger"
        role="alert"
      >
        {{ error }}
      </div>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <TextInput
            id="unity-name"
            v-model="name"
            label="Наименование"
            required
            :readonly="disabled"
            :error="errors.name"
            :validation-error="nameValidation"
          />
        </div>
        <div class="col-12 col-md-6">
          <TextInput
            id="unity-unit"
            v-model="unit"
            label="Единица измерения"
            :readonly="disabled"
            :error="errors.unit"
          />
        </div>
        <div class="col-12">
          <label class="form-check">
            <input
              v-model="useMeter"
              type="checkbox"
              class="form-check-input"
              :disabled="disabled || meterOnly"
            />
            <span class="form-check-label">
              Возможна установка счётчика
            </span>
          </label>
        </div>
      </div>
      <div
        v-if="!modal"
        :class="actionsClass"
      >
        <button
          v-if="auth.canManageMeters"
          type="submit"
          class="btn btn-primary"
          :disabled="!canSave"
        >
          {{ saveLabel }}
        </button>
        <FormBackButton to="/catalogues/unities" />
      </div>
    </div>
    <ModalFormActions
      v-if="modal"
      :saving="saving"
      :can-save="canSave"
      @close="emit('cancel')"
    />
  </form>
</template>
