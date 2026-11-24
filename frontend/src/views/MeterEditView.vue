<script setup lang="ts">
import FormBackButton from '../components/ui/FormBackButton.vue';
import type { FormState } from '../types/components/FormState';
import Badge from '../components/ui/Badge.vue';
import useCreateNavigation, { creationReturnPath } from '../use/createNavigation';
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter, onBeforeRouteLeave } from 'vue-router';
import TextInput from '../components/ui/TextInput.vue';
import IntInput from '../components/ui/IntInput.vue';
import VerificationIntervalInput from '../components/ui/VerificationIntervalInput.vue';
import ReferenceSelect from '../components/ui/ReferenceSelect.vue';
import MeterLocationFields from '../components/MeterLocationFields.vue';
import MeterInstallation from '../components/MeterInstallation.vue';
import MeterRecords from '../components/MeterRecords.vue';
import MeterReadingCreateModal from '../components/MeterReadingCreateModal.vue';
import { useAuthStore } from '../store/auth';
import useApi, { ApiError } from '../use/api';
import { meterErrors, locationErrors } from '../use/meterValidation';
import type { Meter } from '../types/models/Meter';
import type { RentalObject } from '../types/models/RentalObject';
import type { Space } from '../types/models/Space';
import type { MeterDraft, MeterLocationDraft } from '../types/forms/Meter';

const { returnAfterCreate, returnAfterSave } = useCreateNavigation();
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { api, errorText } = useApi();
const record = ref<Meter>();
const readingOpen = ref(false);
const installation = ref<InstanceType<typeof MeterInstallation> | null>(null);
const readingModal = ref<Pick<FormState, 'saving'> | null>(null);
const initialObject = ref<RentalObject | Space['object']>();
const initialSpace = ref<Space>();
const draft = ref<MeterDraft>({
  utilityId: null,
  createDate: '',
  startRead: '',
  serialNumber: '',
  verificationIntervalDays: '',
  verificationDate: '',
  readingDay: '',
});
const location = ref<MeterLocationDraft>({
  type: 'object',
  objectId: null,
  spaceId: null,
  objectUnityProviderId: null,
  comment: '',
});
const original = ref('');
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const errors = ref<Record<string, string>>({});
const notice = ref('');
const fixedLocation = computed(
  () => !editing.value && !!(route.query.objectId || route.query.spaceId),
);
const editing = computed(() => !!route.params.id);
const backRoute = computed(() => creationReturnPath(route.query.returnTo, '/meters'));
const title = computed(() =>
  editing.value ? 'Счетчик ' + (record.value?.name ?? 'Загрузка…') : 'Добавить счетчик',
);
const tab = computed(() =>
  ['readings', 'verifications'].includes(String(route.query.tab))
    ? String(route.query.tab)
    : 'main',
);
const validation = computed(() => ({
  ...meterErrors(draft.value),
  ...(editing.value ? {} : locationErrors(location.value)),
}));
const payload = computed(() => ({
  utilityId: draft.value.utilityId,
  createDate: draft.value.createDate,
  startRead: Number(draft.value.startRead),
  serialNumber: draft.value.serialNumber.trim() || null,
  verificationIntervalDays: Number(draft.value.verificationIntervalDays),
  verificationDate: draft.value.verificationDate || null,
  readingDay: draft.value.readingDay ? Number(draft.value.readingDay) : null,
  ...(!editing.value
    ? {
        objectId: location.value.type === 'object' ? location.value.objectId : null,
        spaceId: location.value.type === 'space' ? location.value.spaceId : null,
        comment: location.value.comment.trim() || null,
        objectUnityProviderId:
          location.value.type === 'object' ? location.value.objectUnityProviderId : null,
      }
    : {}),
}));
const canEdit = computed(() => (editing.value ? auth.canManageMeters : auth.canInstallMeters));
const showReadingDay = computed(() => editing.value || auth.canManageMeters);
const disabled = computed(() => saving.value || !!installation.value?.saving || !canEdit.value);
const canSave = computed(
  () =>
    !disabled.value &&
    !Object.values(validation.value).some(Boolean) &&
    !Object.values(errors.value).some(Boolean) &&
    JSON.stringify(payload.value) !== original.value,
);
const saveLabel = computed(() =>
  saving.value ? 'Сохраняем…' : editing.value ? 'Сохранить' : 'Создать',
);
const readingDayPlaceholder = computed(() => {
  const day = record.value?.installation?.object.readingDay;

  return day ? 'День передачи: ' + day : 'По графику объекта';
});
const objectBadgeColor = computed(
  () => record.value?.installation?.object.landLord?.color ?? 'indigo',
);
const readingDateLabel = computed(
  () => record.value?.nextReadingDueDate?.split('-').reverse().join('.') ?? 'Не требуется',
);
const verificationDateLabel = computed(
  () => record.value?.nextVerificationDate.split('-').reverse().join('.') ?? '—',
);
const tabs = computed(() =>
  [
    { id: 'main', label: 'Основное' },
    { id: 'readings', label: 'Показания' },
    { id: 'verifications', label: 'Поверки' },
  ].map((item) => ({ ...item, current: tab.value === item.id ? ('page' as const) : undefined })),
);

function assign(recordValue: Meter) {
  record.value = recordValue;
  draft.value = {
    utilityId: recordValue.utility.id,
    createDate: recordValue.createDate,
    startRead: String(recordValue.startRead),
    serialNumber: recordValue.serialNumber ?? '',
    verificationIntervalDays: String(recordValue.verificationIntervalDays),
    verificationDate: recordValue.verificationDate ?? '',
    readingDay: recordValue.readingDay === null ? '' : String(recordValue.readingDay),
  };
  original.value = JSON.stringify(payload.value);
}

async function load() {
  loading.value = true;
  error.value = '';

  try {
    if (editing.value) {
      assign(await api<Meter>('/api/meters/' + route.params.id));
    } else if (route.query.spaceId) {
      initialSpace.value = await api<Space>('/api/spaces/' + route.query.spaceId);
      initialObject.value = initialSpace.value.object;
      location.value = {
        type: 'space',
        objectId: initialSpace.value.object.id,
        spaceId: initialSpace.value.id,
        objectUnityProviderId: null,
        comment: '',
      };
    } else if (route.query.objectId) {
      initialObject.value = await api<RentalObject>('/api/objects/' + route.query.objectId);
      location.value.objectId = initialObject.value.id;
    }
  } catch (e) {
    error.value = errorText(e);
  } finally {
    loading.value = false;
  }
}

function utilityChanged() {
  location.value.objectUnityProviderId = null;
  clearErrors();
}

function clearErrors() {
  errors.value = {};
  notice.value = '';
}

async function save() {
  if (!canSave.value) return;

  saving.value = true;
  error.value = '';

  try {
    const meter = await api<Meter>('/api/meters' + (editing.value ? '/' + route.params.id : ''), {
      method: editing.value ? 'PUT' : 'POST',
      body: JSON.stringify(payload.value),
    });

    saving.value = false;

    if (!editing.value) {
      await returnAfterCreate('/meters');

      return;
    } else {
      assign(meter);
    }

    notice.value = 'Прибор сохранён.';
    saving.value = false;
    await returnAfterSave();
  } catch (e) {
    error.value = errorText(e);

    if (e instanceof ApiError) errors.value = e.errors;
  } finally {
    saving.value = false;
  }
}

function selectTab(id: string) {
  void router.replace({ query: { ...route.query, tab: id } });
}

async function readingCreated() {
  readingOpen.value = false;
  await load();
}

onBeforeRouteLeave(
  () => !auth.user || (!saving.value && !readingModal.value?.saving && !installation.value?.saving),
);
onMounted(load);
</script>

<template>
  <div class="page-heading">
    <h1>
      {{ title }}
    </h1>
    <RouterLink
      :to="backRoute"
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
  <div
    v-if="record?.verificationExpired"
    class="alert alert-warning"
    role="alert"
  >
    Требуется поверка: срок истёк {{ record.nextVerificationDate }}. Прибор можно использовать с
    предупреждением до проведения поверки.
  </div>
  <div
    v-if="record?.readingSubmittedLate"
    class="alert alert-warning"
    role="status"
  >
    Показания за текущий месяц переданы с просрочкой: дата снятия позже установленного срока.
  </div>
  <p
    v-if="loading"
    role="status"
  >
    Загрузка…
  </p>
  <div
    v-if="record"
    class="d-flex flex-wrap align-items-center gap-2 mb-3"
  >
    <Badge
      v-if="record.installation"
      :to="'/objects/' + record.installation.object.id"
      :color="objectBadgeColor"
      wrap
    >
      Объект: {{ record.installation.object.name }}
    </Badge>
    <Badge
      v-if="record.installation?.type === 'space'"
      :to="'/spaces/' + record.installation.id"
      wrap
    >
      Помещение: {{ record.installation.name }}
    </Badge>
    <Badge>
      Показания: {{ readingDateLabel }}
    </Badge>
    <Badge>
      Поверка: {{ verificationDateLabel }}
    </Badge>
  </div>
  <section
    v-if="!loading && (!editing || record)"
    class="card"
  >
    <div
      v-if="editing"
      class="card-header"
    >
      <nav
        class="nav nav-tabs card-header-tabs"
        aria-label="Карточка прибора"
      >
        <button
          v-for="item in tabs"
          :key="item.id"
          type="button"
          class="nav-link"
          :class="{ active: tab === item.id }"
          :aria-current="item.current"
          @click="selectTab(item.id)"
        >
          {{ item.label }}
        </button>
      </nav>
    </div>
    <div
      v-if="tab === 'main' || !editing"
      class="card-body"
    >
      <form
        novalidate
        @submit.prevent="save"
        @input="clearErrors"
        @change="clearErrors"
      >
        <div class="row g-3">
          <div class="col-12 col-md-6 col-xl-4">
            <ReferenceSelect
              id="meter-utility"
              v-model="draft.utilityId"
              label="Коммуникация"
              endpoint="/api/unities?meterOnly=1"
              :initial="record?.utility"
              :disabled="disabled"
              :error="errors.utilityId"
              @update:model-value="utilityChanged"
            />
          </div>
          <div class="col-12 col-md-6 col-xl-4">
            <TextInput
              id="meter-serial"
              v-model="draft.serialNumber"
              label="Заводской номер"
              :readonly="disabled"
              :error="errors.serialNumber"
            />
          </div>
          <div class="col-12 col-md-6 col-xl-4">
            <TextInput
              id="meter-production"
              v-model="draft.createDate"
              type="date"
              label="Дата производства"
              required
              :readonly="disabled"
              :error="errors.createDate"
              :validation-error="validation.createDate"
            />
          </div>
          <div class="col-12 col-md-6 col-xl-4">
            <IntInput
              id="meter-initial"
              v-model="draft.startRead"
              label="Начальные показания"
              required
              :readonly="disabled"
              :error="errors.startRead"
              :validation-error="validation.startRead"
            />
          </div>
          <div class="col-12 col-md-6 col-xl-4">
            <VerificationIntervalInput
              id="meter-interval"
              v-model="draft.verificationIntervalDays"
              label="Межповерочный интервал"
              required
              :readonly="disabled"
              :error="errors.verificationIntervalDays"
              :validation-error="validation.verificationIntervalDays"
              @update:model-value="clearErrors"
            />
          </div>
          <div class="col-12 col-md-6 col-xl-4">
            <TextInput
              id="meter-last-verification"
              v-model="draft.verificationDate"
              type="date"
              label="Последняя поверка"
              :readonly="disabled || editing"
              :error="errors.verificationDate"
              :validation-error="validation.verificationDate"
            />
          </div>
          <div
            v-if="showReadingDay"
            class="col-12 col-md-6 col-xl-4"
          >
            <IntInput
              id="meter-reading-day"
              v-model="draft.readingDay"
              label="Собственный день передачи показаний"
              :placeholder="readingDayPlaceholder"
              :readonly="disabled"
              :error="errors.readingDay"
              :validation-error="validation.readingDay"
            />
          </div>
        </div>
        <div
          v-if="!editing"
          class="mt-4"
        >
          <h2 class="card-title">
            Место установки
          </h2>
          <MeterLocationFields
            :fixed-location="fixedLocation"
            v-model="location"
            :initial-object="initialObject"
            :initial-space="initialSpace"
            :utility-id="draft.utilityId"
            :disabled="disabled"
            :errors="errors"
          />
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
          <MeterInstallation
            v-if="record"
            ref="installation"
            :meter="record"
            :disabled="saving"
            @updated="assign"
            @create-reading="readingOpen = true"
          />
          <FormBackButton :to="backRoute" />
        </div>
      </form>
    </div>
    <MeterRecords
      v-else-if="record && tab === 'readings'"
      :meter-id="record.id"
      :meter="record"
      kind="readings"
      @create-reading="readingOpen = true"
    />
    <MeterRecords
      v-else-if="record && tab === 'verifications'"
      :meter-id="record.id"
      :meter="record"
      kind="verifications"
    />
  </section>
  <MeterReadingCreateModal
    ref="readingModal"
    v-if="readingOpen && record"
    :meter="record"
    @close="readingOpen = false"
    @saved="readingCreated"
  />
</template>
