<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import LandLordForm from './LandLordForm.vue';
import type { LandLord } from '../types/models/LandLord';
import type { FormState } from '../types/components/FormState';

const emit = defineEmits<{ created: [record: LandLord]; close: [] }>();
const dialog = ref<HTMLDialogElement | null>(null);
const form = ref<FormState | null>(null);
const busy = computed(() => form.value?.saving ?? false);

function close() {
  if (!busy.value) emit('close');
}

function cancel(event: Event) {
  event.preventDefault();
  close();
}

function created(record: LandLord) {
  emit('created', record);
}

onMounted(() => dialog.value?.showModal());
onBeforeUnmount(() => dialog.value?.close());
</script>

<template>
  <Teleport to="body">
    <dialog
      ref="dialog"
      class="ui-dialog landlord-dialog"
      aria-labelledby="landlord-dialog-title"
      @cancel="cancel"
      @click.self="close"
    >
      <div class="modal-content">
        <div class="modal-header">
          <h2
            id="landlord-dialog-title"
            class="modal-title"
          >
            Добавить арендодателя
          </h2>
          <button
            type="button"
            class="btn-close"
            aria-label="Закрыть"
            :disabled="busy"
            @click="close"
          >
          </button>
        </div>
        <LandLordForm
          ref="form"
          modal
          @saved="created"
          @cancel="close"
        />
      </div>
    </dialog>
  </Teleport>
</template>
