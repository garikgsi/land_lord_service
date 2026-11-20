<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useAttrs, watch } from 'vue';
import useMask from '../../use/mask';
import useValidation from '../../use/validation';
import type { InputValidationProps } from '../../types/components/ui/Input';

defineOptions({ inheritAttrs: false });
const props = defineProps<InputValidationProps>();
const attrs = useAttrs();
const touched = ref(false);
const validation = useValidation();

const model = defineModel<string>({ default: '' });
const input = ref<HTMLInputElement | null>(null);
const { phone, phoneModel } = useMask();
let mask: ReturnType<typeof phone> | undefined;
let synchronizing = false;

const message = computed(
  () => props.error || (touched.value || !!model.value ? validation.phone(model.value) : ''),
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

function validate() {
  input.value?.setCustomValidity(validation.phone(model.value));
}

function synchronize() {
  if (!mask) return;

  const value = phoneModel(model.value).unmaskedValue;

  if (mask.unmaskedValue !== value) {
    synchronizing = true;
    mask.unmaskedValue = value;
    synchronizing = false;
  }

  validate();
}

onMounted(() => {
  if (!input.value) return;

  mask = phone(input.value);
  synchronize();
  mask.on('accept', () => {
    if (!synchronizing && mask)
      model.value = mask.unmaskedValue.length > 1 ? '+' + mask.unmaskedValue : '';

    validate();
  });
});
// Exception: synchronize external v-model changes with the imperative IMask input instance.
watch(model, synchronize);

function blur() {
  touched.value = true;

  if (mask && mask.unmaskedValue.length <= 1) mask.value = '';
}

onBeforeUnmount(() => mask?.destroy());
</script>

<template>
  <div :class="attrs.class">
    <input
      ref="input"
      v-bind="inputAttributes()"
      type="text"
      inputmode="tel"
      autocomplete="tel"
      class="form-control"
      :class="{ 'is-invalid': !!message }"
      :aria-invalid="!!message"
      :aria-describedby="describedBy"
      placeholder="+7 (___) ___-__-__"
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
