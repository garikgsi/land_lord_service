<script setup lang="ts">
import { computed, ref } from 'vue';
import FormModal from './ui/FormModal.vue';
import UnityProviderForm from './UnityProviderForm.vue';
import type { FormState } from '../types/components/FormState';
import type { UnityProvider } from '../types/models/Unity';

const emit = defineEmits<{ created: [record: UnityProvider]; close: [] }>();
const form = ref<FormState | null>(null);
const busy = computed(() => form.value?.saving ?? false);

function close() {
  if (!busy.value) emit('close');
}
</script>

<template>
  <FormModal
    title="Добавить поставщика коммунальных услуг"
    wide
    :busy="busy"
    @close="close"
  >
    <UnityProviderForm
      ref="form"
      modal
      @saved="emit('created', $event)"
      @cancel="close"
    />
  </FormModal>
</template>
