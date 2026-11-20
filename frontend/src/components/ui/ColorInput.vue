<script setup lang="ts">
import { computed } from 'vue';
import { colorPalette } from '../../store/colors';
import useColor from '../../use/color';
import type { ColorInputProps } from '../../types/components/ui/ColorInput';

const props = withDefaults(defineProps<ColorInputProps>(), { defaultColor: '#4263eb' });
const model = defineModel<string | null>({ default: null });
const { colorStyle } = useColor();
const errorId = computed(() => props.id + '-error');
const describedBy = computed(() => (props.error ? errorId.value : undefined));
const resetStyle = computed(() => ({
  ...colorStyle(props.defaultColor),
  borderColor: props.defaultColor,
}));
const options = computed(() =>
  colorPalette.map((option) => {
    const style = colorStyle(option.value);

    return {
      ...option,
      id: props.id + '-' + option.name,
      selected: (model.value ?? props.defaultColor).toLowerCase() === option.value,
      light: style.color === '#000000',
      style,
    };
  }),
);

function reset() {
  model.value = null;
}
</script>

<template>
  <fieldset
    :disabled="disabled"
    :aria-describedby="describedBy"
    :aria-invalid="!!error"
    class="border-0 p-0 m-0"
  >
    <legend class="form-label">
      {{ label }}
    </legend>
    <div class="d-flex align-items-center flex-wrap gap-2">
      <label
        v-for="option in options"
        :key="option.name"
        :for="option.id"
        class="form-colorinput"
        :class="{ 'form-colorinput-light': option.light }"
      >
        <input
          :id="option.id"
          :name="id"
          type="radio"
          class="form-colorinput-input"
          :value="option.value"
          :checked="option.selected"
          :aria-label="option.label"
          @change="model = option.value"
        />
        <span
          class="form-colorinput-color"
          :style="option.style"
          aria-hidden="true"
        >
        </span>
      </label>
      <button
        type="button"
        class="btn btn-sm"
        :style="resetStyle"
        :disabled="model === null"
        @click="reset"
      >
        По умолчанию
      </button>
    </div>
    <div
      v-if="error"
      :id="errorId"
      class="invalid-feedback d-block"
    >
      {{ error }}
    </div>
  </fieldset>
</template>
