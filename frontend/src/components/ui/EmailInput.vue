<script setup lang="ts">
import { computed, ref, useAttrs } from 'vue';
import useValidation from '../../use/validation';
import type { InputValidationProps } from '../../types/components/ui/Input';

defineOptions({ inheritAttrs: false });
const props = defineProps<InputValidationProps>();
const model = defineModel<string>({ default: '' });
const input = ref<HTMLInputElement | null>(null);
const touched = ref(false);
const attrs = useAttrs();
const { email } = useValidation();
const message = computed(
  () => props.error || (touched.value || !!model.value ? email(model.value) : ''),
);

const errorId = computed(() => (attrs.id ? String(attrs.id) + '-error' : undefined));
const describedBy = computed(() =>
  message.value && errorId.value
    ? errorId.value
    : String(attrs['aria-describedby'] ?? '') || undefined,
);

function inputAttributes() {
  const { class: _class, ...rest } = attrs;

  return rest;
}

function blur() {
  model.value = model.value.trim().toLowerCase();
  touched.value = true;
}
</script>

<template>
  <div :class="attrs.class">
    <input
      ref="input"
      v-model="model"
      v-bind="inputAttributes()"
      type="email"
      pattern="[^\s@]+@[^\s@]+\.[^\s@]+"
      inputmode="email"
      autocomplete="email"
      autocapitalize="off"
      :spellcheck="false"
      class="form-control"
      :class="{ 'is-invalid': !!message }"
      :aria-invalid="!!message"
      :aria-describedby="describedBy"
      @blur="blur"
      @invalid="touched = true"
    />
    <div
      v-if="message"
      :id="errorId"
      class="invalid-feedback d-block"
    >
      {{ message }}
    </div>
  </div>
</template>
