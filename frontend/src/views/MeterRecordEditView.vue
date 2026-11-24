<script setup lang="ts">
import FormBackButton from '../components/ui/FormBackButton.vue';
import useCreateNavigation from '../use/createNavigation';
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter, onBeforeRouteLeave } from 'vue-router';
import TextInput from '../components/ui/TextInput.vue';
import MeterReadingForm from '../components/MeterReadingForm.vue';
import type { FormState } from '../types/components/FormState';
import IntInput from '../components/ui/IntInput.vue';
import useApi, { ApiError } from '../use/api';
import { useAuthStore } from '../store/auth';
import { integerError, dateError } from '../use/meterValidation';
import type { Meter, MeterReading } from '../types/models/Meter';
import type { MeterRecordsProps } from '../types/components/MeterRecords';

const { returnAfterCreate, returnAfterSave } = useCreateNavigation();
const props = defineProps<Pick<MeterRecordsProps, 'kind'>>();
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { api, errorText } = useApi();
const meter = ref<Meter>();
const readingForm = ref<FormState | null>(null);
const newReadingTitle = ref('Новые показания');
const date = ref('');
const readingTime = ref('00:00:00');
const value = ref('');
const original = ref('');
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const errors = ref<Record<string, string>>({});
const isReadings = computed(() => props.kind === 'readings');
const editing = computed(() => !!route.params.id);
const meterPath = computed(() => '/meters/' + route.params.meterId);
const recordListRoute = computed(() => ({ path: meterPath.value, query: { tab: props.kind } }));
const endpoint = computed(() => '/api' + meterPath.value + '/' + props.kind);
const title = computed(() =>
  isReadings.value
    ? editing.value
      ? 'Исправить показание'
      : newReadingTitle.value
    : 'Добавить поверку',
);
const dateLabel = computed(() => (isReadings.value ? 'Дата снятия' : 'Дата поверки'));
const dateField = computed(() => (isReadings.value ? 'meterReadingDate' : 'verificationDate'));
const normalizedDate = computed(() => date.value + 'T' + readingTime.value);
const payload = computed(() =>
  isReadings.value
    ? { meterReadingDate: normalizedDate.value, meterRead: Number(value.value) }
    : { verificationDate: date.value },
);
const dateValidation = computed(
  () =>
    dateError(date.value.slice(0, 10)) ||
    (meter.value && date.value.slice(0, 10) < meter.value.createDate
      ? 'Дата не может быть раньше производства.'
      : ''),
);
const valueValidation = computed(() => (isReadings.value ? integerError(value.value) : ''));
const canEdit = computed(() =>
  isReadings.value ? auth.canEditMeterReadings : auth.canManageMeters,
);
const disabled = computed(() => saving.value || !canEdit.value);
const canSave = computed(
  () =>
    !disabled.value &&
    !loading.value &&
    !dateValidation.value &&
    !valueValidation.value &&
    !Object.values(errors.value).some(Boolean) &&
    JSON.stringify(payload.value) !== original.value,
);
const saveLabel = computed(() => (saving.value ? 'Сохраняем…' : 'Сохранить'));

async function load() {
  loading.value = true;
  error.value = '';

  try {
    meter.value = await api<Meter>('/api' + meterPath.value);

    if (editing.value) {
      const record = await api<MeterReading>(endpoint.value + '/' + route.params.id);

      date.value = record.meterReadingDate.slice(0, 10);
      readingTime.value = record.meterReadingDate.slice(11) || '00:00:00';
      value.value = String(record.meterRead);
      original.value = JSON.stringify(payload.value);
    }
  } catch (e) {
    error.value = errorText(e);
  } finally {
    loading.value = false;
  }
}

function clearErrors() {
  errors.value = {};
}

async function save() {
  if (!canSave.value) return;

  saving.value = true;
  error.value = '';

  try {
    await api(endpoint.value + (editing.value ? '/' + route.params.id : ''), {
      method: editing.value ? 'PUT' : 'POST',
      body: JSON.stringify(payload.value),
    });

    saving.value = false;

    if (editing.value) {
      if (!(await returnAfterSave()))
        await router.push({ path: meterPath.value, query: { tab: props.kind } });
    } else await returnAfterCreate(meterPath.value + '?tab=' + props.kind);
  } catch (e) {
    error.value = errorText(e);

    if (e instanceof ApiError) errors.value = e.errors;
  } finally {
    saving.value = false;
  }
}

async function readingCreated() {
  await returnAfterCreate(meterPath.value + '?tab=readings');
}

onBeforeRouteLeave(() => !auth.user || (!saving.value && !readingForm.value?.saving));
onMounted(load);
</script>

<template>
  <div class="page-heading">
    <h1>
      {{ title }}
    </h1>
    <RouterLink
      :to="meterPath"
      class="btn"
    >
      К прибору
    </RouterLink>
  </div>
  <div
    v-if="error"
    class="alert alert-danger"
    role="alert"
  >
    {{ error }}
  </div>
  <p
    v-if="loading"
    role="status"
  >
    Загрузка…
  </p>
  <section
    v-else-if="meter"
    class="card"
  >
    <MeterReadingForm
      @title-change="newReadingTitle = $event"
      v-if="isReadings && !editing"
      ref="readingForm"
      :meter="meter"
      @saved="readingCreated"
      @cancel="router.push(meterPath)"
    />
    <form
      v-else
      class="card-body"
      novalidate
      @submit.prevent="save"
      @input="clearErrors"
    >
      <p>
        {{ meter.name }}
      </p>
      <div class="row g-3">
        <div class="col-12 col-md-6 col-xl-4">
          <TextInput
            id="meter-record-date"
            v-model="date"
            :label="dateLabel"
            type="date"
            required
            :readonly="disabled"
            :error="errors[dateField]"
            :validation-error="dateValidation"
          />
        </div>
        <div
          v-if="isReadings"
          class="col-12 col-md-6 col-xl-4"
        >
          <IntInput
            id="meter-record-value"
            v-model="value"
            label="Показание"
            required
            :readonly="disabled"
            :error="errors.meterRead"
            :validation-error="valueValidation"
          />
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-3">
        <button
          v-if="canEdit"
          type="submit"
          class="btn btn-primary"
          :disabled="!canSave"
        >
          {{ saveLabel }}
        </button>
        <FormBackButton :to="recordListRoute" />
      </div>
    </form>
  </section>
</template>
