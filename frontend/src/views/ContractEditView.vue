<script setup lang="ts">
import FormBackButton from '../components/ui/FormBackButton.vue';
import Badge from '../components/ui/Badge.vue';
import useCreateNavigation from '../use/createNavigation';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { RouterLink, onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import TextInput from '../components/ui/TextInput.vue';
import ConfirmModal from '../components/ui/ConfirmModal.vue';
import type { ContractSigningPreview } from '../types/api/ContractSigningPreview';
import ReferenceSelect from '../components/ui/ReferenceSelect.vue';
import ContractFiles from '../components/ContractFiles.vue';
import ContractActions from '../components/ContractActions.vue';
import ContractHistory from '../components/ContractHistory.vue';
import ContractMeters from '../components/ContractMeters.vue';
import type { ContractTab } from '../types/components/ContractTabs';
import { useAuthStore } from '../store/auth';
import { contractStatusColors, contractStatusLabels } from '../store/contracts';
import useApi, { ApiError } from '../use/api';
import { compareDecimal } from '../use/decimal';
import type { SpaceAvailability } from '../types/api/SpaceAvailability';
import useContractValidation from '../use/contractValidation';
import type { Contract } from '../types/models/Contract';
import type { ContractDraft } from '../types/forms/Contract';
import type { Renter } from '../types/models/Renter';
import type { NamedRecord } from '../types/models/NamedRecord';
import type { RegistryList } from '../types/api/Registry';
import type { ApiErrors } from '../types/use/Api';

const { returnTarget, returnAfterCreate, returnAfterSave } = useCreateNavigation();
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { api, errorText } = useApi();
const validation = useContractValidation();
const creating = computed(() => !route.params.id);
const fixedObject = computed(() => creating.value && Number(route.query.objectId) > 0);
const partyColumnClass = computed(() =>
  fixedObject.value ? 'col-12 col-lg-6' : 'col-12 col-lg-4',
);
const backRoute = computed(() => returnTarget('/contracts'));
const record = ref<Contract>();
const previous = ref<Contract>();
const initialObject = ref<NamedRecord>();
const initialRenter = ref<Renter>();
const contractTypes = ref<NamedRecord[]>([]);
const loading = ref(true);
const loadError = ref('');
const error = ref('');
const notice = ref('');
const saving = ref(false);
const activeTab = computed<ContractTab>(() => {
  if (record.value && route.query.tab === 'meters') return 'meters';

  if (record.value?.isSigned && route.query.tab === 'history') return 'history';

  return 'main';
});
const meters = ref<InstanceType<typeof ContractMeters> | null>(null);
const actions = ref<InstanceType<typeof ContractActions> | null>(null);
const history = ref<InstanceType<typeof ContractHistory> | null>(null);
const errors = ref<ApiErrors>({});
const signedErrorId = computed(() => (errors.value.isSigned ? 'contract-signed-error' : undefined));
const attachments = ref<InstanceType<typeof ContractFiles> | null>(null);
const original = ref('');
const signingPreview = ref<ContractSigningPreview | null>(null);
const availability = ref<SpaceAvailability>();
const availabilityLoading = ref(false);
const availabilityError = ref('');
const form = reactive<ContractDraft>({
  objectId: Number(route.query.objectId) || null,
  renterId: null,
  contractTypeId: 1,
  isSigned: true,
  contractNum: '',
  contractDate: '',
  expiryDate: '',
  monthlyPayment: '',
  securityPayment: '0',
  square: '',
  spaceName: '',
  increaseDate: '',
  increasePercent: '',
});
const newSpace = computed(() => creating.value && !previous.value);
const lockedSpace = computed(() => !newSpace.value && record.value?.isSigned !== false);
const editable = computed(() => creating.value || record.value?.isSigned === false);
const disabled = computed(
  () => !editable.value || !auth.canManageRentalRegistry || saving.value || !!signingPreview.value,
);
const initialContractType = computed(() =>
  contractTypes.value.find((type) => type.id === form.contractTypeId),
);
const title = computed(() =>
  creating.value
    ? previous.value
      ? 'Новый договор на прежнее помещение'
      : 'Добавить договор'
    : record.value
      ? 'Договор № ' + record.value.contractNum
      : 'Загрузка…',
);
const generatedSpaceName = computed(() =>
  form.square && initialRenter.value
    ? form.square + ' кв.м ' + initialRenter.value.name
    : 'Будет создано из площади и названия арендатора',
);
const statusLabel = computed(() => (record.value ? contractStatusLabels[record.value.status] : ''));
const statusColor = computed(() => (record.value ? contractStatusColors[record.value.status] : ''));
const objectRoute = computed(() =>
  record.value ? '/objects/' + record.value.space.object.id : '/objects',
);
const objectCaption = computed(() => record.value?.space.object.name ?? '');
const spaceRoute = computed(() => (record.value ? '/spaces/' + record.value.space.id : '/objects'));
const spaceBadgeColor = computed(() => record.value?.landLord.color ?? 'indigo');
const reminderCaption = computed(() => {
  if (!record.value?.increaseIntervalMonths) return '';

  const next = record.value.nextIndexationReminderDate;

  return (
    'Напоминание каждые ' +
    record.value.increaseIntervalMonths +
    ' мес.' +
    (next ? ' · Следующее: ' + next : '')
  );
});
const spaceCaption = computed(() => record.value?.space.name ?? previous.value?.space.name ?? '');
const areaLimit = computed(() => {
  if (!availability.value || lockedSpace.value) return undefined;

  const end =
    record.value?.annulmentDate && record.value.annulmentDate < form.expiryDate
      ? record.value.annulmentDate
      : form.expiryDate;

  if (end < availability.value.today || end < form.contractDate) return undefined;

  const sameSpace =
    record.value?.space.active &&
    form.objectId === record.value.space.object.id &&
    validation.decimal(form.square.replace(',', '.')) &&
    compareDecimal(form.square, record.value.space.square) === 0;

  return sameSpace ? undefined : availability.value.availableSquare;
});
const validationErrors = computed(() =>
  validation.contract(form, !lockedSpace.value, areaLimit.value),
);
const payload = computed(() => ({
  isSigned: form.isSigned,
  objectId: form.objectId,
  renterId: form.renterId,
  contractTypeId: form.contractTypeId,
  contractNum: form.contractNum.trim(),
  contractDate: form.contractDate,
  expiryDate: form.expiryDate,
  monthlyPayment: form.monthlyPayment.trim().replace(',', '.'),
  securityPayment: form.securityPayment.trim().replace(',', '.'),
  square: !lockedSpace.value ? form.square.trim().replace(',', '.') : null,
  spaceName: newSpace.value ? form.spaceName.trim() : '',
  previousContractId: creating.value ? (previous.value?.id ?? null) : null,
  increaseDate: form.increaseDate || null,
  increasePercent: form.increasePercent.trim().replace(',', '.') || null,
}));
const dirty = computed(
  () => !loading.value && !loadError.value && JSON.stringify(payload.value) !== original.value,
);
const canSave = computed(
  () =>
    !disabled.value &&
    !loading.value &&
    !availabilityLoading.value &&
    !availabilityError.value &&
    dirty.value &&
    !Object.keys(validationErrors.value).length &&
    !Object.keys(errors.value).length,
);
const saveLabel = computed(() =>
  saving.value ? 'Сохраняем…' : creating.value ? 'Создать договор' : 'Сохранить',
);
const signingPreviewPath = computed(
  () => '/api/contracts' + (creating.value ? '' : '/' + route.params.id) + '/signing-preview',
);
const signingMessage = computed(() => {
  if (!signingPreview.value) return '';

  const [whole = '0', fraction = '00'] = signingPreview.value.totalAmount.split('.');
  const amount = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0') + ',' + fraction;

  return (
    'По договору будет начислена задолженность за ' +
    signingPreview.value.monthCount +
    ' мес. в размере ' +
    amount +
    ' руб. Продолжить?'
  );
});
let controller: AbortController | undefined;
let renterController: AbortController | undefined;
let availabilityController: AbortController | undefined;

async function selectTab(tab: ContractTab) {
  if (meters.value?.saving) return;

  await router.replace({ query: { ...route.query, tab } });
}

function clearErrors() {
  errors.value = {};
  notice.value = '';
}

function clearErrorsFromInput(event: Event) {
  if (event.target instanceof HTMLInputElement && event.target.type === 'checkbox') return;

  clearErrors();
}

async function loadAvailability(id: number | null) {
  availabilityController?.abort();
  availability.value = undefined;
  availabilityError.value = '';

  if (!id || lockedSpace.value) {
    availabilityLoading.value = false;

    return;
  }

  const current = new AbortController();

  availabilityController = current;
  availabilityLoading.value = true;

  try {
    const query = record.value ? '?contractId=' + record.value.id : '';

    const result = await api<SpaceAvailability>('/api/objects/' + id + '/availability' + query, {
      signal: current.signal,
    });

    if (!current.signal.aborted) availability.value = result;
  } catch (e) {
    if (!current.signal.aborted) availabilityError.value = errorText(e);
  } finally {
    if (!current.signal.aborted) availabilityLoading.value = false;
  }
}

function objectSelected(id: number | null) {
  clearErrors();
  void loadAvailability(id);
}

async function renterSelected(id: number | null) {
  renterController?.abort();
  clearErrors();
  initialRenter.value = undefined;

  if (!id) return;

  const current = new AbortController();

  renterController = current;

  try {
    const renter = await api<Renter>('/api/renters/' + id, { signal: current.signal });

    if (!current.signal.aborted) initialRenter.value = renter;
  } catch (e) {
    if (!current.signal.aborted) error.value = errorText(e);
  }
}

async function load() {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;
  loadError.value = '';

  try {
    contractTypes.value = (
      await api<RegistryList<NamedRecord>>('/api/contract-types', { signal: current.signal })
    ).items;
    let source: Contract | undefined;

    if (!creating.value) {
      source = await api<Contract>('/api/contracts/' + route.params.id, { signal: current.signal });
      record.value = source;
    } else if (route.query.previousContractId) {
      source = await api<Contract>('/api/contracts/' + Number(route.query.previousContractId), {
        signal: current.signal,
      });

      if (!source.canRecontract)
        throw new Error(
          'Этот договор уже завершён или имеет следующий договор. Создайте новый Space.',
        );

      previous.value = source;
    }

    if (source) {
      form.objectId = source.space.object.id;
      form.renterId = source.renter.id;
      form.contractTypeId = source.contractType.id;
      form.square = source.space.square;
      form.isSigned = creating.value ? true : source.isSigned;
      form.contractNum = creating.value ? '' : source.contractNum;
      form.contractDate = creating.value
        ? validation.nextDay(source.effectiveEnd)
        : source.contractDate;
      form.expiryDate = creating.value ? '' : source.expiryDate;
      form.monthlyPayment = source.monthlyPayment;
      form.securityPayment = source.securityPayment;
      form.increaseDate = creating.value ? '' : (source.increaseDate ?? '');
      form.increasePercent = creating.value ? '' : (source.increasePercent ?? '');
      initialObject.value = source.space.object;
      initialRenter.value = source.renter;
    } else if (form.objectId) {
      initialObject.value = await api<NamedRecord>('/api/objects/' + form.objectId, {
        signal: current.signal,
      });
    }

    if (current.signal.aborted) return;

    await loadAvailability(form.objectId);
    original.value = JSON.stringify(payload.value);
  } catch (e) {
    if (!current.signal.aborted)
      loadError.value = e instanceof Error && !(e instanceof ApiError) ? e.message : errorText(e);
  } finally {
    if (!current.signal.aborted) loading.value = false;
  }
}

function saveError(e: unknown) {
  signingPreview.value = null;
  error.value = errorText(e);

  if (e instanceof ApiError) {
    errors.value = { ...e.errors };
    delete errors.value.signingConfirmation;
  }
}

async function save() {
  if (!canSave.value) return;

  saving.value = true;
  error.value = '';

  try {
    if (form.isSigned && !record.value?.isSigned) {
      const preview = await api<ContractSigningPreview>(signingPreviewPath.value, {
        method: 'POST',
        body: JSON.stringify(payload.value),
      });

      if (preview.monthCount > 0) {
        signingPreview.value = preview;

        return;
      }
    }

    await persist();
  } catch (e) {
    saveError(e);
  } finally {
    saving.value = false;
  }
}

async function persist(confirmation?: string) {
  const result = await api<Contract>(
    '/api/contracts' + (creating.value ? '' : '/' + route.params.id),
    {
      method: creating.value ? 'POST' : 'PUT',
      body: JSON.stringify({ ...payload.value, signingConfirmation: confirmation ?? null }),
    },
  );

  signingPreview.value = null;
  saving.value = false;

  if (creating.value) {
    await returnAfterCreate('/contracts');

    return;
  }

  record.value = result;
  form.square = result.space.square;
  initialObject.value = result.space.object;
  original.value = JSON.stringify(payload.value);
  await loadAvailability(form.objectId);
  notice.value = 'Изменения сохранены.';
  await returnAfterSave();
}

async function confirmSigning() {
  if (!signingPreview.value || saving.value) return;

  saving.value = true;

  try {
    await persist(signingPreview.value.confirmation);
  } catch (e) {
    saveError(e);
  } finally {
    saving.value = false;
  }
}

function cancelSigning() {
  if (saving.value) return;

  signingPreview.value = null;

  if (record.value?.isSigned === false) form.isSigned = false;
}

async function updated(result: Contract) {
  record.value = result;
  form.renterId = result.renter.id;
  initialRenter.value = result.renter;
  form.expiryDate = result.expiryDate;
  form.monthlyPayment = result.monthlyPayment;
  form.increaseDate = result.increaseDate ?? '';
  form.increasePercent = result.increasePercent ?? '';
  notice.value = 'Изменения сохранены.';
  void history.value?.reload();
  await returnAfterSave();
}

onBeforeRouteLeave(
  () =>
    !auth.user ||
    (!saving.value && !actions.value?.busy && !attachments.value?.saving && !meters.value?.saving),
);
onMounted(load);
onBeforeUnmount(() => {
  controller?.abort();
  renterController?.abort();
  availabilityController?.abort();
});
</script>

<template>
  <div class="page-heading d-block d-md-flex">
    <div>
      <h1>
        {{ title }}
      </h1>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <ContractActions
        v-if="record?.isSigned && auth.canManageRentalRegistry"
        ref="actions"
        :contract="record"
        @updated="updated"
      />
      <RouterLink
        :to="backRoute"
        class="btn"
      >
        К списку
      </RouterLink>
    </div>
  </div>
  <p
    v-if="loading"
    role="status"
  >
    Загрузка…
  </p>
  <div
    v-else-if="loadError"
    class="alert alert-danger"
    role="alert"
  >
    {{ loadError }}
    <button
      class="btn ms-2"
      @click="load"
    >
      Повторить
    </button>
  </div>
  <template v-else>
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
    <section class="card">
      <div
        v-if="record"
        class="card-header border-bottom-0"
      >
        <div
          class="nav nav-tabs card-header-tabs"
          role="tablist"
          aria-label="Договор"
        >
          <button
            id="contract-main-tab"
            type="button"
            class="nav-link"
            :class="{ active: activeTab === 'main' }"
            role="tab"
            :aria-selected="activeTab === 'main'"
            aria-controls="contract-main-panel"
            @click="selectTab('main')"
          >
            Основное
          </button>
          <button
            id="contract-meters-tab"
            type="button"
            class="nav-link"
            :class="{ active: activeTab === 'meters' }"
            role="tab"
            :aria-selected="activeTab === 'meters'"
            aria-controls="contract-meters-panel"
            @click="selectTab('meters')"
          >
            Счетчики
          </button>
          <button
            v-if="record.isSigned"
            id="contract-history-tab"
            type="button"
            class="nav-link"
            :class="{ active: activeTab === 'history' }"
            role="tab"
            :aria-selected="activeTab === 'history'"
            aria-controls="contract-history-panel"
            @click="selectTab('history')"
          >
            История изменений
          </button>
        </div>
      </div>
      <div class="tab-content">
        <div
          v-show="activeTab === 'main'"
          id="contract-main-panel"
          role="tabpanel"
          aria-labelledby="contract-main-tab"
        >
          <form
            novalidate
            @submit.prevent="save"
            @input="clearErrorsFromInput"
            @change="clearErrors"
          >
            <div class="card-body">
              <div
                v-if="record"
                class="d-flex align-items-center flex-wrap gap-2 mb-3"
              >
                <Badge :color="statusColor">
                  {{ statusLabel }}
                </Badge>
                <Badge
                  :to="objectRoute"
                  :color="spaceBadgeColor"
                  wrap
                >
                  {{ objectCaption }}
                </Badge>
                <Badge
                  :to="spaceRoute"
                  :color="spaceBadgeColor"
                  wrap
                >
                  {{ spaceCaption }}
                </Badge>
              </div>
              <div
                v-if="previous"
                class="alert alert-info"
              >
                {{ spaceCaption }}
              </div>
              <p
                v-if="reminderCaption"
                class="text-secondary"
              >
                {{ reminderCaption }}
              </p>
              <div
                v-if="availabilityError"
                class="alert alert-danger"
                role="alert"
              >
                {{ availabilityError }}
                <button
                  type="button"
                  class="btn ms-2"
                  @click="loadAvailability(form.objectId)"
                >
                  Повторить
                </button>
              </div>
              <div class="row g-3">
                <div
                  v-if="!fixedObject"
                  class="col-12 col-lg-4"
                >
                  <ReferenceSelect
                    id="contract-object"
                    v-model="form.objectId"
                    label="Объект"
                    endpoint="/api/objects"
                    :initial="initialObject"
                    :disabled="disabled || lockedSpace"
                    :error="errors.objectId"
                    @update:model-value="objectSelected"
                  />
                </div>
                <div :class="partyColumnClass">
                  <ReferenceSelect
                    id="contract-renter"
                    v-model="form.renterId"
                    label="Арендатор"
                    endpoint="/api/renters"
                    :initial="initialRenter"
                    :disabled="disabled"
                    :error="errors.renterId"
                    @update:model-value="renterSelected"
                  />
                </div>
                <div :class="partyColumnClass">
                  <ReferenceSelect
                    id="contract-type"
                    v-model="form.contractTypeId"
                    label="Тип договора"
                    endpoint="/api/contract-types"
                    :initial="initialContractType"
                    :disabled="disabled"
                    :error="errors.contractTypeId"
                    @update:model-value="clearErrors"
                  />
                </div>
                <div class="col-12 col-md-4">
                  <TextInput
                    id="contract-num"
                    v-model="form.contractNum"
                    label="Номер договора"
                    required
                    :readonly="disabled"
                    :error="errors.contractNum"
                    :validation-error="validationErrors.contractNum"
                  />
                </div>
                <div class="col-12 col-md-4">
                  <TextInput
                    id="contract-start"
                    v-model="form.contractDate"
                    type="date"
                    label="Начало аренды"
                    required
                    :readonly="disabled"
                    :error="errors.contractDate"
                    :validation-error="validationErrors.contractDate"
                  />
                </div>
                <div class="col-12 col-md-4">
                  <TextInput
                    id="contract-end"
                    v-model="form.expiryDate"
                    type="date"
                    label="Окончание аренды"
                    required
                    :readonly="disabled"
                    :error="errors.expiryDate"
                    :validation-error="validationErrors.expiryDate"
                  />
                </div>
                <div class="col-12 col-md-4">
                  <TextInput
                    id="contract-monthly"
                    v-model="form.monthlyPayment"
                    inputmode="decimal"
                    label="Ежемесячный платёж, ₽"
                    required
                    :readonly="disabled"
                    :error="errors.monthlyPayment"
                    :validation-error="validationErrors.monthlyPayment"
                  />
                </div>
                <div class="col-12 col-md-4">
                  <TextInput
                    id="contract-security"
                    v-model="form.securityPayment"
                    inputmode="decimal"
                    label="Обеспечительный платёж, ₽"
                    required
                    :readonly="disabled"
                    :error="errors.securityPayment"
                    :validation-error="validationErrors.securityPayment"
                  />
                </div>
                <div class="col-12 col-md-4">
                  <TextInput
                    id="contract-square"
                    v-model="form.square"
                    inputmode="decimal"
                    label="Площадь помещения, м²"
                    required
                    :readonly="disabled || lockedSpace"
                    :error="errors.square"
                    :validation-error="validationErrors.square"
                  />
                </div>
                <div
                  v-if="newSpace"
                  class="col-12"
                >
                  <TextInput
                    id="contract-space-name"
                    v-model="form.spaceName"
                    label="Название помещения"
                    :placeholder="generatedSpaceName"
                    :readonly="disabled"
                    :error="errors.spaceName"
                  />
                </div>
                <div class="col-12 col-md-4">
                  <TextInput
                    id="contract-increase-date"
                    v-model="form.increaseDate"
                    type="date"
                    label="Дата индексации"
                    :readonly="disabled"
                    :error="errors.increaseDate"
                    :validation-error="validationErrors.increaseDate"
                  />
                </div>
                <div class="col-12 col-md-4">
                  <TextInput
                    id="contract-increase-percent"
                    v-model="form.increasePercent"
                    inputmode="decimal"
                    label="Индексация, %"
                    :readonly="disabled"
                    :error="errors.increasePercent"
                    :validation-error="validationErrors.increasePercent"
                  />
                </div>
                <div class="col-12 col-md-4 contract-signature-field">
                  <label class="d-flex align-items-center gap-2 mb-0">
                    <input
                      id="contract-signed"
                      v-model="form.isSigned"
                      type="checkbox"
                      class="form-check-input m-0"
                      :disabled="disabled || record?.isSigned"
                      :aria-invalid="!!errors.isSigned"
                      :aria-describedby="signedErrorId"
                    />
                    <span class="form-check-label">
                      Договор подписан
                    </span>
                  </label>
                  <div
                    v-if="errors.isSigned"
                    id="contract-signed-error"
                    class="text-danger mt-1"
                    role="alert"
                  >
                    {{ errors.isSigned }}
                  </div>
                </div>
              </div>
              <div class="d-flex flex-wrap gap-2 mt-3">
                <button
                  v-if="editable && auth.canManageRentalRegistry"
                  type="submit"
                  class="btn btn-primary"
                  :disabled="!canSave"
                >
                  {{ saveLabel }}
                </button>
                <FormBackButton :to="backRoute" />
              </div>
            </div>
          </form>
        </div>
        <div
          v-if="record && activeTab === 'meters'"
          id="contract-meters-panel"
          role="tabpanel"
          aria-labelledby="contract-meters-tab"
        >
          <ContractMeters
            :key="record.space.id"
            ref="meters"
            :object-id="record.space.object.id"
            :space-id="record.space.id"
          />
        </div>
        <div
          v-if="record?.isSigned"
          v-show="activeTab === 'history'"
          id="contract-history-panel"
          role="tabpanel"
          aria-labelledby="contract-history-tab"
        >
          <ContractHistory
            ref="history"
            :contract-id="record.id"
          />
        </div>
      </div>
    </section>
    <section
      v-if="record"
      class="card mt-3"
    >
      <ContractFiles
        ref="attachments"
        :contract-id="record.id"
      />
    </section>
  </template>
  <ConfirmModal
    v-if="signingPreview"
    title="Подтверждение начисления"
    :message="signingMessage"
    :busy="saving"
    @confirm="confirmSigning"
    @cancel="cancelSigning"
  />
</template>

<style scoped>
.contract-signature-field > label {
  min-height: 2.5rem;
}

@media (min-width: 768px) {
  .contract-signature-field {
    padding-top: 1.75rem;
  }
}
</style>
