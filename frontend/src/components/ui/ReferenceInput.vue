<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, onUpdated, ref } from 'vue';
import TomSelect from 'tom-select';
import useApi from '../../use/api';
import type {
  ReferenceInputProps,
  ReferenceLoadCallback,
} from '../../types/components/ui/ReferenceInput';
import type { ReferenceOption } from '../../types/components/ui/ReferenceInput';
import type { RegistryList } from '../../types/api/Registry';

const props = defineProps<ReferenceInputProps>();
const model = defineModel<number | null>({ default: null });
const element = ref<HTMLSelectElement | null>(null);
const loadingError = ref('');
const touched = ref(false);
const message = computed(
  () =>
    props.error ||
    loadingError.value ||
    (touched.value && !model.value ? 'Выберите арендодателя.' : ''),
);
const errorId = computed(() => props.id + '-error');
const { api, errorText } = useApi();
let control: TomSelect | undefined;
let controller: AbortController | undefined;
let lastInitial: ReferenceOption | undefined;

function syncControl() {
  if (!control) return;

  if (props.initial && props.initial !== lastInitial) {
    controller?.abort();
    control.addOption(props.initial);
    control.updateOption(String(props.initial.id), props.initial);
    control.loadedSearches = {};
    lastInitial = props.initial;
  }

  const selected = model.value === null ? '' : String(model.value);

  if (control.getValue() !== selected) control.setValue(selected, true);

  if (props.disabled) control.disable();
  else control.enable();

  control.control_input.setAttribute('aria-label', props.label);
  control.control_input.setAttribute('aria-invalid', String(!!message.value));
  control.control_input.setAttribute('aria-describedby', errorId.value);
  control.wrapper.classList.toggle('is-invalid', !!message.value);
}

function retry() {
  if (!control) return;

  control.loadedSearches = {};
  control.load(control.getTextboxValue());
}

onMounted(() => {
  if (!element.value) return;

  control = new TomSelect(element.value, {
    valueField: 'id',
    labelField: 'name',
    searchField: ['name'],
    maxItems: 1,
    create: false,
    preload: 'focus',
    placeholder: 'Найдите и выберите арендодателя',
    options: props.initial ? [props.initial] : [],
    items: model.value ? [String(model.value)] : [],
    shouldLoad: () => true,
    render: {
      no_results: () => '<div class="no-results">Ничего не найдено</div>',
      loading: () =>
        '<div class="spinner-border spinner-border-sm m-2" role="status" aria-label="Загрузка"></div>',
    },
    async load(query: string, callback: ReferenceLoadCallback) {
      controller?.abort();
      const current = new AbortController();

      controller = current;
      loadingError.value = '';

      try {
        const result = await api<RegistryList<ReferenceOption>>(
          props.endpoint + '?' + new URLSearchParams({ query }),
          { signal: current.signal },
        );

        callback(current.signal.aborted ? [] : result.items);
      } catch (error) {
        if (!current.signal.aborted) loadingError.value = errorText(error);

        callback();
      }
    },
    onBlur() {
      touched.value = true;
    },
    onChange(value: string) {
      model.value = value ? Number(value) : null;
    },
  });
  syncControl();
});

// Synchronise the imperative SDK's disabled state and accessibility attributes.
onUpdated(syncControl);
onBeforeUnmount(() => {
  controller?.abort();
  control?.destroy();
});
</script>

<template>
  <label
    :for="id"
    class="form-label"
  >
    {{ label }}
    <span
      class="text-danger"
      aria-hidden="true"
    >
      *
    </span>
  </label>
  <select
    :id="id"
    ref="element"
    :disabled="disabled"
    class="form-select reference-select"
  >
  </select>
  <div
    :id="errorId"
    class="invalid-feedback"
    :class="{ 'd-block': message }"
  >
    {{ message }}
  </div>
  <button
    v-if="loadingError"
    type="button"
    class="btn btn-sm mt-2"
    @click="retry"
  >
    Повторить загрузку
  </button>
</template>
