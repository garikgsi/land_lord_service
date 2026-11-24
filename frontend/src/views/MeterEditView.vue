<script setup lang="ts">
import FormBackButton from '../components/ui/FormBackButton.vue';
import type { FormState } from '../types/components/FormState';
import Badge from '../components/ui/Badge.vue';
import useCreateNavigation, { creationReturnPath } from '../use/createNavigation';
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter, onBeforeRouteLeave } from 'vue-router';
import MeterFields from '../components/MeterFields.vue';
import MeterInstallation from '../components/MeterInstallation.vue';
import MeterRecords from '../components/MeterRecords.vue';
import MeterReadingCreateModal from '../components/MeterReadingCreateModal.vue';
import { useAuthStore } from '../store/auth';
import useApi, { ApiError } from '../use/api';
import { meterErrors } from '../use/meterValidation';
import type { Meter } from '../types/models/Meter';
import type { MeterDraft } from '../types/forms/Meter';

const { returnAfterCreate, returnAfterSave } = useCreateNavigation();
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { api, errorText } = useApi();
const record = ref<Meter>();
const readingOpen = ref(false);
const installation = ref<InstanceType<typeof MeterInstallation> | null>(null);
const readingModal = ref<Pick<FormState, 'saving'> | null>(null);
const draft = ref<MeterDraft>({
  utilityId: null,
  createDate: '',
  startRead: '',
  serialNumber: '',
  model: '',
  comment: '',
  verificationIntervalDays: '',
  verificationDate: '',
  readingDay: '',
});
const original = ref('');
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const errors = ref<Record<string, string>>({});
const notice = ref('');
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
const validation = computed(() => meterErrors(draft.value));
const payload = computed(() => ({
  utilityId: draft.value.utilityId,
  createDate: draft.value.createDate,
  startRead: Number(draft.value.startRead),
  serialNumber: draft.value.serialNumber.trim() || null,
  model: draft.value.model.trim() || null,
  comment: draft.value.comment.trim() || null,
  verificationIntervalDays: Number(draft.value.verificationIntervalDays),
  verificationDate: draft.value.verificationDate || null,
  readingDay: draft.value.readingDay ? Number(draft.value.readingDay) : null,
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
    model: recordValue.model ?? '',
    comment: recordValue.comment ?? '',
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
    }
  } catch (e) {
    error.value = errorText(e);
  } finally {
    loading.value = false;
  }
}

function utilityChanged() {
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
    <Badge
      v-if="!record.installation"
      color="green"
    >
      Не установлен
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
        <MeterFields
          v-model="draft"
          :initial-utility="record?.utility"
          :disabled="disabled"
          :readonly-verification="editing"
          :show-reading-day="showReadingDay"
          :reading-day-placeholder="readingDayPlaceholder"
          :errors="errors"
          :validation="validation"
          @utility-changed="utilityChanged"
          @changed="clearErrors"
        />
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
