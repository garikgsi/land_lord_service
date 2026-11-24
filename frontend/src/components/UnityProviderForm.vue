<script setup lang="ts">
import FormBackButton from './ui/FormBackButton.vue';
import { computed, ref } from 'vue';
import TextInput from './ui/TextInput.vue';
import { useAuthStore } from '../store/auth';
import useApi, { ApiError } from '../use/api';
import type { UnityProvider } from '../types/models/Unity';
import type { UnityProviderFormProps } from '../types/components/UnityProviderForm';

const props = defineProps<UnityProviderFormProps>();
const emit = defineEmits<{ saved: [record: UnityProvider]; cancel: [] }>();
const auth = useAuthStore();
const { api, errorText } = useApi();
const name = ref(props.initial?.name ?? '');
const saving = ref(false);
const error = ref('');
const errors = ref<Record<string, string>>({});
const payload = computed(() => ({ name: name.value.trim() }));
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

function clearErrors() {
  errors.value = {};
}

async function save() {
  if (!canSave.value) return;

  saving.value = true;
  error.value = '';

  try {
    const record = await api<UnityProvider>(
      '/api/unity-providers' + (props.initial ? '/' + props.initial.id : ''),
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
    @input="clearErrors"
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
            id="provider-name"
            v-model="name"
            label="Наименование"
            required
            :readonly="disabled"
            :error="errors.name"
            :validation-error="nameValidation"
          />
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
        <FormBackButton to="/catalogues/unity-providers" />
      </div>
    </div>
    <div
      v-if="modal"
      :class="actionsClass"
    >
      <button
        type="button"
        class="btn"
        :disabled="saving"
        @click="emit('cancel')"
      >
        Отмена
      </button>
      <button
        type="submit"
        class="btn btn-primary"
        :disabled="!canSave"
      >
        {{ saveLabel }}
      </button>
    </div>
  </form>
</template>
