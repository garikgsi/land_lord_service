<script setup lang="ts">
import { onMounted, onBeforeUnmount, onUpdated, ref } from 'vue';
import TomSelect from 'tom-select';
import type { SelectInputProps } from '../../types/components/ui/SelectInput';

const props = defineProps<SelectInputProps>();
const model = defineModel<string>({ required: true });
const element = ref<HTMLSelectElement | null>(null);
let control: TomSelect | undefined;

function sync() {
  if (!control) return;

  if (control.getValue() !== model.value) control.setValue(model.value, true);

  if (props.disabled) control.disable();
  else control.enable();

  control.control_input.setAttribute('aria-label', props.label);
}

onMounted(() => {
  if (!element.value) return;

  control = new TomSelect(element.value, {
    valueField: 'value',
    labelField: 'label',
    searchField: ['label'],
    maxItems: 1,
    create: false,
    options: props.options.map((option) => ({ ...option })),
    items: [model.value],
    onChange(value: string) {
      model.value = value;
    },
  });
  sync();
});

onUpdated(sync);
onBeforeUnmount(() => control?.destroy());
</script>

<template>
  <select
    :id="id"
    ref="element"
    class="form-select reference-select"
    :aria-label="label"
    :disabled="disabled"
  >
  </select>
</template>
