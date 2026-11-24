<script setup lang="ts">
import { computed, ref } from 'vue';
import FormModal from './ui/FormModal.vue';
import MeterReadingForm from './MeterReadingForm.vue';
import type { MeterReadingFormProps } from '../types/components/MeterReadingForm';
import type { FormState } from '../types/components/FormState';

defineProps<Pick<MeterReadingFormProps, 'meter'>>();
const title = ref('Новые показания');
const emit = defineEmits<{ close: []; saved: [] }>();
const form = ref<FormState | null>(null);
const busy = computed(() => form.value?.saving ?? false);

defineExpose({ saving: busy });

function close() {
  if (!busy.value) emit('close');
}
</script>

<template>
  <FormModal
    :title="title"
    :busy="busy"
    @close="close"
  >
    <MeterReadingForm
      ref="form"
      :meter="meter"
      modal
      @title-change="title = $event"
      @saved="emit('saved')"
      @cancel="close"
    />
  </FormModal>
</template>
