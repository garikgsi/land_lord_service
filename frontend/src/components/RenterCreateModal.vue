<script setup lang="ts">
import { computed, ref } from 'vue';
import RenterForm from './RenterForm.vue';
import FormModal from './ui/FormModal.vue';
import type { Renter } from '../types/models/Renter';
import type { FormState } from '../types/components/FormState';

const emit = defineEmits<{ created: [record: Renter]; close: [] }>();
const form = ref<FormState | null>(null);
const busy = computed(() => form.value?.saving ?? false);

function close() {
  if (!busy.value) emit('close');
}

function created(record: Renter) {
  emit('created', record);
}
</script>

<template>
  <FormModal
    title="Добавить арендатора"
    compact
    :busy="busy"
    @close="close"
  >
    <RenterForm
      ref="form"
      modal
      @saved="created"
      @cancel="close"
    />
  </FormModal>
</template>
