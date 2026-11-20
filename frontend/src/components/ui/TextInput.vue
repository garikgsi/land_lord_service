<script setup lang="ts">
import { computed, ref } from 'vue';
import type { TextInputProps } from '../../types/components/ui/TextInput';

defineOptions({ inheritAttrs: false });
const props = defineProps<TextInputProps>();
const model = defineModel<string>({ default: '' });
const touched = ref(false);
const message = computed(() => props.error || (touched.value ? props.validationError : ''));
const errorId = computed(() => props.id + '-error');
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
    v-model="model"
    v-bind="$attrs"
    class="form-control"
    :class="{ 'is-invalid': message }"
    :required="required"
    :aria-invalid="!!message"
    :aria-describedby="errorId"
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
