<script setup lang="ts">
import { computed, ref } from 'vue';
import IntInput from './ui/IntInput.vue';
import { useAuthStore } from '../store/auth';
import useApi, { ApiError } from '../use/api';
import type { RentalObject } from '../types/models/RentalObject';
import type { RentalObjectMeterSettingsProps } from '../types/components/RentalObjectMeterSettings';

const props = defineProps<RentalObjectMeterSettingsProps>();
const emit = defineEmits<{ updated: [object: RentalObject] }>();
const auth = useAuthStore();
const { api, errorText } = useApi();
const day = ref(String(props.object.readingDay));
const original = ref(day.value);
const saving = ref(false);
const error = ref('');
const fieldError = ref('');
const notice = ref('');
const dirty = computed(() => day.value !== original.value);
const validationError = computed(() =>
  /^[0-9]+$/.test(day.value) && Number(day.value) >= 1 && Number(day.value) <= 31
    ? ''
    : 'Укажите день от 1 до 31.',
);
const canSave = computed(
  () =>
    auth.canManageRentalRegistry &&
    dirty.value &&
    !validationError.value &&
    !fieldError.value &&
    !saving.value &&
    !props.disabled,
);
const saveLabel = computed(() => (saving.value ? 'Сохраняем…' : 'Сохранить график'));

function clearErrors() {
  fieldError.value = '';
  notice.value = '';
}

async function save() {
  if (!canSave.value) return;

  saving.value = true;
  error.value = '';

  try {
    const object = await api<RentalObject>('/api/objects/' + props.object.id + '/meter-settings', {
      method: 'PUT',
      body: JSON.stringify({ readingDay: Number(day.value) }),
    });

    day.value = String(object.readingDay);
    original.value = day.value;
    notice.value = 'График передачи показаний сохранён.';
    saving.value = false;
    emit('updated', object);
  } catch (e) {
    error.value = errorText(e);

    if (e instanceof ApiError) fieldError.value = e.errors.readingDay ?? '';
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
        v-if="notice"
        class="alert alert-success"
        role="status"
      >
        {{ notice }}
      </div>
      <div class="row">
        <div class="col-12 col-md-6 col-xl-4">
          <IntInput
            id="object-meter-reading-day"
            v-model="day"
            label="День передачи показаний"
            required
            :readonly="!auth.canManageRentalRegistry || saving || disabled"
            :error="fieldError"
            :validation-error="validationError"
            @input="clearErrors"
          />
          <div class="form-hint">
            Общий график для счётчиков объекта и его помещений: число от 1 до 31.
          </div>
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
      </div>
    </div>
  </form>
</template>
