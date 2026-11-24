<script setup lang="ts">
import { computed } from 'vue';
import TextInput from './ui/TextInput.vue';
import IntInput from './ui/IntInput.vue';
import VerificationIntervalInput from './ui/VerificationIntervalInput.vue';
import ReferenceSelect from './ui/ReferenceSelect.vue';
import type { MeterDraft } from '../types/forms/Meter';
import type { MeterFieldsProps } from '../types/components/MeterFields';

const props = withDefaults(defineProps<MeterFieldsProps>(), { idPrefix: 'meter' });
const model = defineModel<MeterDraft>({ required: true });
const emit = defineEmits<{ utilityChanged: []; changed: [] }>();
const utilityDisabled = computed(() => props.disabled || props.fixedUtility);
const ids = computed(() => ({
  utility: props.idPrefix + '-utility',
  serial: props.idPrefix + '-serial',
  model: props.idPrefix + '-model',
  comment: props.idPrefix + '-comment',
  commentError: props.idPrefix + '-comment-error',
  production: props.idPrefix + '-production',
  initial: props.idPrefix + '-initial',
  interval: props.idPrefix + '-interval',
  verification: props.idPrefix + '-last-verification',
  readingDay: props.idPrefix + '-reading-day',
}));
</script>

<template>
  <div class="row g-3">
    <div class="col-12 col-md-6 col-xl-4">
      <ReferenceSelect
        :id="ids.utility"
        v-model="model.utilityId"
        label="Коммуникация"
        endpoint="/api/unities?meterOnly=1"
        :initial="initialUtility"
        :disabled="utilityDisabled"
        :error="errors.utilityId"
        @update:model-value="emit('utilityChanged')"
      />
    </div>
    <div class="col-12 col-md-6 col-xl-4">
      <TextInput
        :id="ids.model"
        v-model="model.model"
        label="Модель"
        :readonly="disabled"
        :error="errors.model"
      />
    </div>
    <div class="col-12 col-md-6 col-xl-4">
      <TextInput
        :id="ids.serial"
        v-model="model.serialNumber"
        label="Заводской номер"
        :readonly="disabled"
        :error="errors.serialNumber"
      />
    </div>
    <div class="col-12 col-md-6 col-xl-4">
      <TextInput
        :id="ids.production"
        v-model="model.createDate"
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
        :id="ids.initial"
        v-model="model.startRead"
        label="Начальные показания"
        required
        :readonly="disabled"
        :error="errors.startRead"
        :validation-error="validation.startRead"
      />
    </div>
    <div class="col-12 col-md-6 col-xl-4">
      <VerificationIntervalInput
        :id="ids.interval"
        v-model="model.verificationIntervalDays"
        label="Межповерочный интервал"
        required
        :readonly="disabled"
        :error="errors.verificationIntervalDays"
        :validation-error="validation.verificationIntervalDays"
        @update:model-value="emit('changed')"
      />
    </div>
    <div class="col-12 col-md-6 col-xl-4">
      <TextInput
        :id="ids.verification"
        v-model="model.verificationDate"
        type="date"
        label="Последняя поверка"
        :readonly="disabled || readonlyVerification"
        :error="errors.verificationDate"
        :validation-error="validation.verificationDate"
      />
    </div>
    <div
      v-if="showReadingDay"
      class="col-12 col-md-6 col-xl-4"
    >
      <IntInput
        :id="ids.readingDay"
        v-model="model.readingDay"
        label="Собственный день передачи показаний"
        :placeholder="readingDayPlaceholder"
        :readonly="disabled"
        :error="errors.readingDay"
        :validation-error="validation.readingDay"
      />
    </div>
    <div class="col-12">
      <label
        :for="ids.comment"
        class="form-label"
      >
        Комментарий
      </label>
      <textarea
        :id="ids.comment"
        v-model="model.comment"
        class="form-control"
        :class="{ 'is-invalid': errors.comment }"
        rows="3"
        :readonly="disabled"
        :aria-invalid="!!errors.comment"
        :aria-describedby="ids.commentError"
      />
      <div
        :id="ids.commentError"
        class="invalid-feedback"
        :class="{ 'd-block': errors.comment }"
      >
        {{ errors.comment }}
      </div>
    </div>
  </div>
</template>
