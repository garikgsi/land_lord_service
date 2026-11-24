<script setup lang="ts">
import { onMounted, onBeforeUnmount, onUpdated, ref } from 'vue';
import type { Datepicker } from '@tabler/core';
import useTablerDatepicker from '../../use/tablerDatepicker';
import type { DateRangeInputProps } from '../../types/components/ui/DateRangeInput';

const props = defineProps<DateRangeInputProps>();
const from = defineModel<string>('from', { default: '' });
const to = defineModel<string>('to', { default: '' });
const emit = defineEmits<{ change: [] }>();
const element = ref<HTMLInputElement | null>(null);
const error = ref('');
let picker: Datepicker | undefined;
let lastValue = '';
let disposed = false;

function sync() {
  const next = from.value + '/' + to.value;

  if (!picker || next === lastValue) return;

  lastValue = next;
  picker.setSelectedDates([from.value, to.value].filter(Boolean));
}

function apply(dates: string[]) {
  const sorted = [...dates].sort();

  from.value = sorted[0] ?? '';
  to.value = sorted.at(-1) ?? '';
  lastValue = from.value + '/' + to.value;
  emit('change');
}

function selected() {
  const dates = picker?.getSelectedDates() ?? [];

  if (dates.length === 1) return;

  apply(dates);
}

function closed() {
  const dates = picker?.getSelectedDates() ?? [];

  if (dates.length === 1) apply(dates);
}

function clear() {
  picker?.setSelectedDates([]);
  apply([]);
}

function show() {
  void picker?.show();
}

async function initialize() {
  error.value = '';

  try {
    const DatepickerClass = await useTablerDatepicker();

    if (disposed || !element.value) return;

    picker = new DatepickerClass(element.value, {
      selectionMode: 'multiple-ranged',
      locale: 'ru-RU',
      firstWeekday: 1,
      dateFormat: 'DD.MM.YYYY',
    });
    element.value.addEventListener('change.bs.datepicker', selected);
    element.value.addEventListener('hidden.bs.datepicker', closed);
    sync();

    if (document.activeElement === element.value) show();
  } catch {
    if (!disposed) error.value = 'Не удалось загрузить календарь.';
  }
}

onMounted(initialize);
// The Tabler SDK is imperative; external filter resets update it without watchers.
onUpdated(sync);
onBeforeUnmount(() => {
  disposed = true;
  element.value?.removeEventListener('change.bs.datepicker', selected);
  element.value?.removeEventListener('hidden.bs.datepicker', closed);
  picker?.dispose();
});
</script>

<template>
  <label
    :for="id"
    class="form-label"
  >
    {{ label }}
  </label>
  <div class="d-flex gap-2">
    <input
      :id="id"
      ref="element"
      class="form-control"
      placeholder="Выберите период"
      readonly
      @focus="show"
      @click="show"
    />
    <button
      v-if="from || to"
      type="button"
      class="btn"
      aria-label="Очистить период"
      @click="clear"
    >
      ×
    </button>
  </div>
  <div
    v-if="error"
    class="text-danger small mt-1"
    role="alert"
  >
    {{ error }}
    <button
      type="button"
      class="btn btn-sm ms-2"
      @click="initialize"
    >
      Повторить
    </button>
  </div>
</template>
