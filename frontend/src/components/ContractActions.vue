<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import ActionMenu from './ui/ActionMenu.vue';
import { useAuthStore } from '../store/auth';
import FormModal from './ui/FormModal.vue';
import TextInput from './ui/TextInput.vue';
import IntInput from './ui/IntInput.vue';
import ReferenceSelect from './ui/ReferenceSelect.vue';
import useApi, { ApiError } from '../use/api';
import useContractValidation from '../use/contractValidation';
import { indexedAmount, indexationPercent } from '../use/indexation';
import type { Contract } from '../types/models/Contract';
import type {
  ContractAction,
  ContractActionDraft,
  ContractActionsProps,
} from '../types/components/ContractActions';
import { contractActionItems } from '../store/contracts';
import type { ApiErrors } from '../types/use/Api';

const props = defineProps<ContractActionsProps>();
const auth = useAuthStore();
const emit = defineEmits<{ updated: [contract: Contract] }>();
const { api, errorText } = useApi();
const validation = useContractValidation();
const action = ref<ContractAction>();
const busy = ref(false);
const error = ref('');
const errors = ref<ApiErrors>({});
const calculationError = ref('');
const calculationMode = ref<'percent' | 'amount'>('percent');
const form = reactive<ContractActionDraft>({
  renterId: null,
  expiryDate: '',
  monthlyPayment: '',
  annulmentDate: '',
  increaseDate: '',
  increasePercent: '',
  newMonthlyPayment: '',
  periodic: false,
  intervalMonths: '',
});
const titles = {
  amendment: 'Изменение договора',
  termination: 'Расторжение договора',
  indexation: 'Индексация договора',
};
const title = computed(() => (action.value ? titles[action.value] : ''));
const menuLabel = computed(() => (props.table ? 'Действия' : 'Действия с договором'));
const original = ref('');
const payload = computed(() => {
  if (action.value === 'amendment')
    return {
      renterId: form.renterId,
      expiryDate: form.expiryDate,
      monthlyPayment: normalize(form.monthlyPayment),
    };

  if (action.value === 'termination') return { annulmentDate: form.annulmentDate };

  return {
    increaseDate: form.increaseDate,
    increasePercent: normalize(form.increasePercent),
    currentMonthlyPayment: props.contract.monthlyPayment,
    newMonthlyPayment: normalize(form.newMonthlyPayment),
    calculationMode: calculationMode.value,
    periodic: form.periodic,
    intervalMonths: form.periodic ? Number(form.intervalMonths) || null : null,
  };
});
const validationErrors = computed<ApiErrors>(() => {
  const result: ApiErrors = {};

  if (action.value === 'amendment') {
    if (!form.renterId) result.renterId = 'Выберите арендатора.';

    if (!validation.date(form.expiryDate) || form.expiryDate < props.contract.contractDate)
      result.expiryDate = 'Укажите дату не раньше начала аренды.';

    if (!validation.decimal(normalize(form.monthlyPayment)))
      result.monthlyPayment = 'Введите неотрицательную сумму.';
  }

  if (action.value === 'termination' && !validation.date(form.annulmentDate))
    result.annulmentDate = 'Укажите корректную дату.';

  if (action.value === 'indexation') {
    if (
      !validation.date(form.increaseDate) ||
      form.increaseDate < props.contract.contractDate ||
      form.increaseDate > props.contract.effectiveEnd
    )
      result.increaseDate = 'Дата должна входить в период аренды.';

    if (!/^-?\d+(?:\.\d+)?$/.test(normalize(form.increasePercent)))
      result.increasePercent = 'Введите корректный процент.';

    if (!validation.decimal(normalize(form.newMonthlyPayment)))
      result.newMonthlyPayment = 'Введите неотрицательную сумму.';

    if (calculationError.value) result.newMonthlyPayment = calculationError.value;

    if (
      form.periodic &&
      (!/^\d+$/.test(form.intervalMonths) ||
        Number(form.intervalMonths) < 1 ||
        !Number.isSafeInteger(Number(form.intervalMonths)) ||
        Number(form.intervalMonths) > 2147483647)
    )
      result.intervalMonths = 'Укажите положительное количество месяцев.';
  }

  return result;
});
const canSave = computed(
  () =>
    !busy.value &&
    JSON.stringify(payload.value) !== original.value &&
    !Object.keys(validationErrors.value).length &&
    !Object.keys(errors.value).length,
);
const saveLabel = computed(() => (busy.value ? 'Сохраняем…' : 'Сохранить'));
const isAmendment = computed(() => action.value === 'amendment');
const isTermination = computed(() => action.value === 'termination');

function normalize(value: string) {
  return value.trim().replace(',', '.');
}

function clearErrors() {
  errors.value = {};
}

function open(value: ContractAction) {
  Object.assign(form, {
    renterId: props.contract.renter.id,
    expiryDate: props.contract.expiryDate,
    monthlyPayment: props.contract.monthlyPayment,
    annulmentDate: props.contract.annulmentDate ?? '',
    increaseDate: '',
    increasePercent: '',
    newMonthlyPayment: '',
    periodic: false,
    intervalMonths: '',
  });
  error.value = '';
  errors.value = {};
  calculationError.value = '';
  calculationMode.value = 'percent';
  action.value = value;
  original.value = JSON.stringify(payload.value);
}

function recalculate(mode: 'percent' | 'amount', value: string) {
  calculationMode.value = mode;
  calculationError.value = '';
  clearErrors();

  if (mode === 'percent') form.increasePercent = value;
  else form.newMonthlyPayment = value;

  try {
    if (mode === 'percent')
      form.newMonthlyPayment = indexedAmount(props.contract.monthlyPayment, value);
    else form.increasePercent = indexationPercent(props.contract.monthlyPayment, value);
  } catch (e) {
    calculationError.value = e instanceof Error ? e.message : 'Проверьте сумму и процент.';
  }
}

function close() {
  if (!busy.value) action.value = undefined;
}

async function save() {
  if (!canSave.value || !action.value) return;

  busy.value = true;
  error.value = '';

  try {
    const suffix = action.value === 'termination' ? 'annulment' : action.value;
    const result = await api<Contract>('/api/contracts/' + props.contract.id + '/' + suffix, {
      method: action.value === 'indexation' ? 'POST' : 'PUT',
      body: JSON.stringify(payload.value),
    });

    action.value = undefined;
    busy.value = false;
    emit('updated', result);
  } catch (e) {
    error.value = errorText(e);

    if (e instanceof ApiError) errors.value = e.errors;
  } finally {
    busy.value = false;
  }
}

onBeforeRouteLeave(() => !auth.user || !busy.value);

defineExpose({ busy, open });
</script>

<template>
  <ActionMenu
    v-if="!hideMenu"
    :label="menuLabel"
    :floating="table"
    :items="contractActionItems"
    :disabled="busy"
    @select="open"
  />
  <FormModal
    v-if="action"
    :title="title"
    :busy="busy"
    :wide="!isTermination"
    @close="close"
  >
    <form
      novalidate
      @submit.prevent="save"
      @input="clearErrors"
      @change="clearErrors"
    >
      <div class="modal-body">
        <div
          v-if="error"
          class="alert alert-danger"
          role="alert"
        >
          {{ error }}
        </div>
        <div
          v-if="isAmendment"
          class="row g-3"
        >
          <div class="col-12 col-md-6">
            <ReferenceSelect
              id="amend-renter"
              v-model="form.renterId"
              label="Арендатор"
              endpoint="/api/renters"
              :initial="contract.renter"
              :disabled="busy"
              :error="errors.renterId || validationErrors.renterId"
            />
          </div>
          <div class="col-12 col-md-3">
            <TextInput
              id="amend-end"
              v-model="form.expiryDate"
              type="date"
              label="Окончание аренды"
              required
              :readonly="busy"
              :error="errors.expiryDate"
              :validation-error="validationErrors.expiryDate"
            />
          </div>
          <div class="col-12 col-md-3">
            <TextInput
              id="amend-payment"
              v-model="form.monthlyPayment"
              inputmode="decimal"
              label="Ежемесячный платёж, ₽"
              required
              :readonly="busy"
              :error="errors.monthlyPayment"
              :validation-error="validationErrors.monthlyPayment"
            />
          </div>
        </div>
        <TextInput
          v-else-if="isTermination"
          id="terminate-date"
          v-model="form.annulmentDate"
          type="date"
          label="Дата аннулирования"
          required
          :readonly="busy"
          :error="errors.annulmentDate"
          :validation-error="validationErrors.annulmentDate"
        />
        <div
          v-else
          class="row g-3"
        >
          <div class="col-12 col-md-6">
            <TextInput
              id="index-date"
              v-model="form.increaseDate"
              type="date"
              label="Дата индексации"
              required
              :readonly="busy"
              :error="errors.increaseDate"
              :validation-error="validationErrors.increaseDate"
            />
            <div class="form-hint">
              Сумма изменится при сохранении. Дата используется в реквизитах и напоминаниях.
            </div>
          </div>
          <div class="col-12 col-md-6">
            <TextInput
              id="index-percent"
              :model-value="form.increasePercent"
              inputmode="decimal"
              label="Индексация, %"
              required
              :readonly="busy"
              :error="errors.increasePercent"
              :validation-error="validationErrors.increasePercent"
              @update:model-value="recalculate('percent', $event)"
            />
          </div>
          <div class="col-12 col-md-6">
            <TextInput
              id="index-current"
              :model-value="contract.monthlyPayment"
              label="Текущий ежемесячный платёж, ₽"
              readonly
            />
          </div>
          <div class="col-12 col-md-6">
            <TextInput
              id="index-new"
              :model-value="form.newMonthlyPayment"
              inputmode="decimal"
              label="Новый ежемесячный платёж, ₽"
              required
              :readonly="busy"
              :error="errors.newMonthlyPayment"
              :validation-error="validationErrors.newMonthlyPayment"
              @update:model-value="recalculate('amount', $event)"
            />
          </div>
          <div class="col-12">
            <label class="form-check">
              <input
                v-model="form.periodic"
                type="checkbox"
                class="form-check-input"
                :disabled="busy"
              />
              <span class="form-check-label">
                Периодическая индексация
              </span>
            </label>
          </div>
          <div
            v-if="form.periodic"
            class="col-12 col-md-6"
          >
            <IntInput
              id="index-interval"
              v-model="form.intervalMonths"
              label="Периодичность, месяцев"
              required
              :readonly="busy"
              :error="errors.intervalMonths"
              :validation-error="validationErrors.intervalMonths"
            />
            <div class="form-hint">
              Только напоминания от даты индексации, без автоматического повышения.
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
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
          :disabled="busy"
          @click="close"
        >
          Отмена
        </button>
      </div>
    </form>
  </FormModal>
</template>
