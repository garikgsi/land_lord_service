<script setup lang="ts">
import ModalFormActions from './ui/ModalFormActions.vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import MeterFields from './MeterFields.vue';
import useApi, { ApiError } from '../use/api';
import { useAuthStore } from '../store/auth';
import { meterErrors } from '../use/meterValidation';
import type { MeterDraft } from '../types/forms/Meter';
import type { Meter } from '../types/models/Meter';
import type { NamedRecord } from '../types/models/NamedRecord';
import type { MeterCreateFormProps } from '../types/components/MeterFields';

const props = defineProps<MeterCreateFormProps>();
const emit = defineEmits<{ saved: [record: Meter]; cancel: [] }>();
const auth = useAuthStore();
const { api, errorText } = useApi();
const draft = ref<MeterDraft>({
  utilityId: props.utilityId ?? null,
  createDate: '',
  startRead: '',
  serialNumber: '',
  model: '',
  comment: '',
  verificationIntervalDays: '',
  verificationDate: '',
  readingDay: '',
});
const fixedUtility = computed(() => !!props.utilityId);
const initialUtility = ref<NamedRecord>();
const title = computed(() =>
  initialUtility.value ? 'Новый счетчик · ' + initialUtility.value.name : 'Новый счетчик',
);
const saving = ref(false);
const loading = ref(!!props.utilityId);
const error = ref('');
const errors = ref<Record<string, string>>({});
const validation = computed(() => meterErrors(draft.value));
const dirty = computed(
  () =>
    !!(
      draft.value.createDate ||
      draft.value.serialNumber ||
      draft.value.startRead ||
      draft.value.model ||
      draft.value.comment
    ),
);
const disabled = computed(() => saving.value || loading.value || !auth.canInstallMeters);
const canSave = computed(
  () =>
    !disabled.value &&
    !Object.values(validation.value).some(Boolean) &&
    !Object.values(errors.value).some(Boolean),
);
const bodyClass = computed(() => (props.modal ? 'modal-body' : 'card-body'));
const footerClass = computed(() => (props.modal ? 'modal-footer' : 'd-flex gap-2 mt-3'));
const saveLabel = computed(() => (saving.value ? 'Сохраняем…' : 'Создать'));
const controller = new AbortController();

function clearErrors() {
  errors.value = {};
}

async function save() {
  if (!canSave.value) return;

  saving.value = true;
  error.value = '';

  try {
    const record = await api<Meter>('/api/meters', {
      method: 'POST',
      body: JSON.stringify({
        utilityId: draft.value.utilityId,
        createDate: draft.value.createDate,
        startRead: Number(draft.value.startRead),
        verificationIntervalDays: Number(draft.value.verificationIntervalDays),
        serialNumber: draft.value.serialNumber.trim() || null,
        model: draft.value.model.trim() || null,
        comment: draft.value.comment.trim() || null,
        verificationDate: draft.value.verificationDate || null,
        readingDay: draft.value.readingDay ? Number(draft.value.readingDay) : null,
      }),
    });

    saving.value = false;
    emit('saved', record);
  } catch (e) {
    error.value = errorText(e);

    if (e instanceof ApiError) errors.value = e.errors;
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  if (!props.utilityId) return;

  try {
    initialUtility.value = await api<NamedRecord>('/api/unities/' + props.utilityId, {
      signal: controller.signal,
    });
  } catch (e) {
    if (!controller.signal.aborted) error.value = errorText(e);
  } finally {
    if (!controller.signal.aborted) loading.value = false;
  }
});
onBeforeUnmount(() => controller.abort());
defineExpose({ saving, dirty, title });
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
      <MeterFields
        v-model="draft"
        id-prefix="meter-create"
        :initial-utility="initialUtility"
        :fixed-utility="fixedUtility"
        :disabled="disabled"
        :show-reading-day="auth.canManageMeters"
        :errors="errors"
        :validation="validation"
        @utility-changed="clearErrors"
        @changed="clearErrors"
      />
    </div>
    <ModalFormActions
      v-if="modal"
      :saving="saving"
      :can-save="canSave"
      @close="emit('cancel')"
    />
    <div
      v-else
      :class="footerClass"
    >
      <button
        type="submit"
        class="btn btn-primary"
        :disabled="!canSave"
      >
        {{ saveLabel }}
      </button>
      <button
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
