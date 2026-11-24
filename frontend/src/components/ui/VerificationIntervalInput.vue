<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import IntInput from './IntInput.vue';
import {
  verificationIntervalPreset,
  verificationIntervalPresets,
} from '../../use/verificationInterval';
import type { VerificationIntervalInputProps } from '../../types/components/ui/VerificationIntervalInput';

const props = defineProps<VerificationIntervalInputProps>();
const model = defineModel<string>({ default: '' });
const custom = ref(false);
const touched = ref(false);
const root = ref<HTMLElement | null>(null);
const labelId = computed(() => props.id + '-label');
const errorId = computed(() => props.id + '-error');
const disabled = computed(() => props.disabled || props.readonly);
const preset = computed(() => verificationIntervalPreset(model.value));
const showCustom = computed(() => custom.value || (!!model.value && !preset.value));
const describedBy = computed(() => (showCustom.value ? props.id + '-days-error' : errorId.value));
const message = computed(() => props.error || (touched.value ? props.validationError : ''));
const buttons = computed(() => [
  ...verificationIntervalPresets.map((item) => ({
    value: item.days,
    label: item.label,
    active: !custom.value && preset.value?.days === item.days,
  })),
  { value: 'custom', label: 'Другое', active: showCustom.value },
]);

async function select(value: string) {
  if (disabled.value) return;

  touched.value = true;
  custom.value = value === 'custom';

  if (custom.value) {
    await nextTick();
    root.value?.querySelector<HTMLInputElement>('input')?.focus();
  } else {
    model.value = value;
  }
}

function blur() {
  touched.value = true;

  if (preset.value) custom.value = false;
}
</script>

<template>
  <div ref="root">
    <div
      :id="labelId"
      class="form-label"
    >
      {{ label }}
      <span
        v-if="required"
        class="text-danger"
        aria-hidden="true"
      >
        *
      </span>
    </div>
    <div
      :id="id"
      class="btn-group btn-group-sm w-100"
      role="group"
      :aria-labelledby="labelId"
      :aria-describedby="describedBy"
      :aria-invalid="!!message"
    >
      <button
        v-for="button in buttons"
        :key="button.value"
        type="button"
        class="btn"
        :class="{ 'btn-primary': button.active }"
        :aria-pressed="button.active"
        :disabled="disabled"
        @click="select(button.value)"
      >
        {{ button.label }}
      </button>
    </div>
    <div
      v-if="showCustom"
      class="mt-2"
      @focusout="blur"
    >
      <IntInput
        :id="id + '-days'"
        v-model="model"
        label="Количество дней"
        :required="required"
        :readonly="readonly"
        :disabled="disabled"
        :error="error"
        :validation-error="validationError"
      />
    </div>
    <div
      v-if="!showCustom"
      :id="errorId"
      class="invalid-feedback"
      :class="{ 'd-block': message }"
    >
      {{ message }}
    </div>
  </div>
</template>
