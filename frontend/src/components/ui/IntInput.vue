<script setup lang="ts">
import { computed, ref } from 'vue';
import type { TextInputProps } from '../../types/components/ui/TextInput';

defineOptions({ inheritAttrs: false });
const props = defineProps<TextInputProps>();
const model = defineModel<string>({ default: '' });
const touched = ref(false);
const message = computed(() => props.error || (touched.value ? props.validationError : ''));
const errorId = computed(() => props.id + '-error');

function beforeInput(event: InputEvent) {
  if (event.data && !/^[0-9]*$/.test(event.data)) event.preventDefault();
}

function input(event: Event) {
  const element = event.target as HTMLInputElement;

  if (/^[0-9]*$/.test(element.value)) {
    model.value = element.value;
  } else {
    element.value = model.value;
  }
}
</script>

<template>
  <label
    :for="id"
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
  </label>
  <input
    :id="id"
    v-bind="$attrs"
    :value="model"
    type="text"
    inputmode="numeric"
    pattern="[0-9]*"
    class="form-control"
    :class="{ 'is-invalid': message }"
    :required="required"
    :aria-invalid="!!message"
    :aria-describedby="errorId"
    @beforeinput="beforeInput"
    @input="input"
    @blur="touched = true"
  />
  <div
    :id="errorId"
    class="invalid-feedback"
    :class="{ 'd-block': message }"
  >
    {{ message }}
  </div>
</template>
