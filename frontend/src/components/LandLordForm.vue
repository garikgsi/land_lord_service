<script setup lang="ts">
import { computed, ref } from 'vue';
import TextInput from './ui/TextInput.vue';
import ColorInput from './ui/ColorInput.vue';
import useColor from '../use/color';
import useApi, { ApiError } from '../use/api';
import { useAuthStore } from '../store/auth';
import type { LandLord } from '../types/models/LandLord';
import type { LandLordFormProps } from '../types/components/LandLordForm';
import type { ApiErrors } from '../types/use/Api';

const props = defineProps<LandLordFormProps>();
const emit = defineEmits<{ saved: [record: LandLord]; cancel: [] }>();
const auth = useAuthStore();
const { api, errorText } = useApi();
const name = ref(props.initial?.name ?? '');
const color = ref<string | null>(props.initial?.color ?? null);
const original = ref(name.value.trim());
const originalColor = ref(color.value);
const { isColor } = useColor();
const saving = ref(false);
const error = ref('');
const notice = ref('');
const serverErrors = ref<ApiErrors>({});
const fieldId = computed(() => (props.modal ? 'modal-landlord-name' : 'landlord-name'));
const nameError = computed(() => (name.value.trim() ? '' : 'Укажите наименование.'));
const colorId = computed(() => fieldId.value + '-color');
const colorError = computed(() => (isColor(color.value) ? '' : 'Выберите корректный цвет.'));
const colorMessage = computed(() => serverErrors.value.color || colorError.value);
const dirty = computed(
  () => name.value.trim() !== original.value || color.value !== originalColor.value,
);
const canSave = computed(
  () =>
    auth.canManageRentalRegistry &&
    dirty.value &&
    !saving.value &&
    !nameError.value &&
    !colorError.value &&
    !Object.keys(serverErrors.value).length,
);
const saveLabel = computed(() =>
  saving.value ? 'Сохраняем…' : props.initial ? 'Сохранить' : 'Создать',
);

function clearErrors() {
  serverErrors.value = {};
  notice.value = '';
}

async function save() {
  if (!canSave.value) return;

  saving.value = true;
  error.value = '';

  try {
    const record = await api<LandLord>(
      '/api/land-lords' + (props.initial ? '/' + props.initial.id : ''),
      {
        method: props.initial ? 'PUT' : 'POST',
        body: JSON.stringify({ name: name.value.trim(), color: color.value }),
      },
    );

    name.value = record.name;
    original.value = record.name;
    color.value = record.color;
    originalColor.value = record.color;
    notice.value = 'Изменения сохранены.';
    saving.value = false;
    emit('saved', record);
  } catch (e) {
    error.value = errorText(e);

    if (e instanceof ApiError) serverErrors.value = e.errors;
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
        <div class="col-12">
          <TextInput
            :id="fieldId"
            v-model="name"
            label="Наименование"
            required
            :autofocus="modal"
            :readonly="!auth.canManageRentalRegistry || saving"
            :error="serverErrors.name"
            :validation-error="nameError"
            @input="clearErrors"
          />
        </div>
        <div class="col-12">
          <ColorInput
            :id="colorId"
            v-model="color"
            label="Цвет"
            :disabled="!auth.canManageRentalRegistry || saving"
            :error="colorMessage"
            @update:model-value="clearErrors"
          />
        </div>
      </div>
    </div>
    <div
      v-if="auth.canManageRentalRegistry"
      class="card-footer d-flex gap-2"
    >
      <button
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
    </div>
  </form>
</template>
