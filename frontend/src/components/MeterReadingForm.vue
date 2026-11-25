<script setup lang="ts">
import ModalFormActions from './ui/ModalFormActions.vue';
import FormBackButton from './ui/FormBackButton.vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Badge from './ui/Badge.vue';
import TextInput from './ui/TextInput.vue';
import IntInput from './ui/IntInput.vue';
import useApi, { ApiError } from '../use/api';
import { useAuthStore } from '../store/auth';
import { meterReadingDate, meterReadingMonth } from '../use/meterReading';
import { dateError, integerError } from '../use/meterValidation';
import type { MeterReadingFormProps } from '../types/components/MeterReadingForm';
import type { MeterReadingPrevious } from '../types/api/MeterReadingPrevious';
import type { MeterReading } from '../types/models/Meter';

const props = defineProps<MeterReadingFormProps>();
const emit = defineEmits<{
  saved: [reading: MeterReading];
  cancel: [];
  titleChange: [title: string];
}>();
const auth = useAuthStore();
const { api, errorText } = useApi();
const date = ref(
  new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Moscow' }).format(new Date()),
);
const value = ref('');
const previous = ref<MeterReadingPrevious>();
const loadingPrevious = ref(false);
const previousError = ref('');
const error = ref('');
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const dateValidation = computed(
  () =>
    dateError(date.value) ||
    (date.value < props.meter.createDate ? 'Дата не может быть раньше производства.' : ''),
);
const monthLabel = computed(() => (dateError(date.value) ? '' : meterReadingMonth(date.value)));
const readingTitle = computed(() =>
  monthLabel.value ? 'Новые показания за ' + monthLabel.value : 'Новые показания',
);
const monthWarning = computed(() =>
  previous.value?.monthReadingDate && monthLabel.value
    ? 'За ' +
      monthLabel.value +
      ' показания уже были переданы ' +
      meterReadingDate(previous.value.monthReadingDate)
    : '',
);
const valueValidation = computed(
  () =>
    integerError(value.value) ||
    (previous.value && Number(value.value) < previous.value.meterRead
      ? 'Показание не может быть меньше предыдущего.'
      : ''),
);
const consumption = computed(() => {
  if (!previous.value || loadingPrevious.value || valueValidation.value) return '—';

  return (
    (Number(value.value) - previous.value.meterRead).toLocaleString('ru-RU') +
    (props.meter.utility.unit ? ' ' + props.meter.utility.unit : '')
  );
});
const previousLabel = computed(() =>
  loadingPrevious.value ? 'Загрузка…' : previous.value ? String(previous.value.meterRead) : '—',
);
const disabled = computed(() => saving.value || !auth.canEditMeterReadings);
const canSave = computed(
  () =>
    !disabled.value &&
    !loadingPrevious.value &&
    !!previous.value &&
    !dateValidation.value &&
    !valueValidation.value &&
    !Object.values(errors.value).some(Boolean),
);
const dirty = computed(() => !!value.value);
const bodyClass = computed(() => (props.modal ? 'modal-body' : 'card-body'));
const footerClass = computed(() =>
  props.modal ? 'modal-footer' : 'card-body pt-0 d-flex flex-wrap gap-2',
);
const saveLabel = computed(() => (saving.value ? 'Сохраняем…' : 'Сохранить'));
let controller: AbortController | undefined;

function clearErrors() {
  errors.value = {};
  error.value = '';
}

async function loadPrevious() {
  controller?.abort();
  previous.value = undefined;
  previousError.value = '';
  loadingPrevious.value = false;
  clearErrors();
  emit('titleChange', readingTitle.value);

  if (dateValidation.value) return;

  const current = new AbortController();

  controller = current;
  loadingPrevious.value = true;

  try {
    const result = await api<MeterReadingPrevious>(
      '/api/meters/' +
        props.meter.id +
        '/readings/previous?' +
        new URLSearchParams({ date: date.value }),
      { signal: current.signal },
    );

    if (!current.signal.aborted) previous.value = result;
  } catch (e) {
    if (!current.signal.aborted) previousError.value = errorText(e);
  } finally {
    if (!current.signal.aborted) loadingPrevious.value = false;
  }
}

async function save() {
  if (!canSave.value) return;

  saving.value = true;
  clearErrors();

  try {
    const reading = await api<MeterReading>('/api/meters/' + props.meter.id + '/readings', {
      method: 'POST',
      body: JSON.stringify({
        meterReadingDate: date.value + 'T00:00:00',
        meterRead: Number(value.value),
      }),
    });

    saving.value = false;
    emit('saved', reading);
  } catch (e) {
    error.value = errorText(e);

    if (e instanceof ApiError) errors.value = e.errors;
  } finally {
    saving.value = false;
  }
}

onMounted(loadPrevious);
onBeforeUnmount(() => controller?.abort());
defineExpose({ saving, dirty });
</script>

<template>
  <form
    novalidate
    @submit.prevent="save"
  >
    <div :class="bodyClass">
      <div
        v-if="monthWarning"
        class="alert alert-warning"
        role="alert"
      >
        {{ monthWarning }}
      </div>
      <Badge
        class="mb-3"
        wrap
      >
        {{ meter.name }}
      </Badge>
      <div
        v-if="error"
        class="alert alert-danger"
        role="alert"
      >
        {{ error }}
      </div>
      <div
        v-if="previousError"
        class="alert alert-danger"
        role="alert"
      >
        {{ previousError }}
        <button
          type="button"
          class="btn btn-sm ms-2"
          @click="loadPrevious"
        >
          Повторить
        </button>
      </div>
      <div class="row g-3">
        <div class="col-12">
          <TextInput
            id="reading-create-date"
            v-model="date"
            type="date"
            label="Дата снятия"
            required
            :readonly="disabled"
            :min="meter.createDate"
            :error="errors.meterReadingDate"
            :validation-error="dateValidation"
            @update:model-value="loadPrevious"
          />
        </div>
        <div class="col-12">
          <TextInput
            id="reading-create-previous"
            :model-value="previousLabel"
            label="Предыдущие показания"
            readonly
          />
        </div>
        <div class="col-12">
          <IntInput
            id="reading-create-value"
            v-model="value"
            label="Текущее показание"
            autofocus
            required
            :readonly="disabled"
            :error="errors.meterRead"
            :validation-error="valueValidation"
            @input="clearErrors"
          />
        </div>
      </div>
      <div
        class="text-secondary mt-3"
        role="status"
      >
        Потребление:
        <span class="fw-semibold text-body">
          {{ consumption }}
        </span>
      </div>
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
      <FormBackButton :to="'/meters/' + meter.id + '?tab=readings'" />
    </div>
  </form>
</template>
