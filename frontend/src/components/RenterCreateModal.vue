<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import RenterForm from './RenterForm.vue';
import type { Renter } from '../types/models/Renter';
import type { FormState } from '../types/components/FormState';

const emit = defineEmits<{ created: [record: Renter]; close: [] }>();
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

function created(record: Renter) {
  emit('created', record);
}

onMounted(() => dialog.value?.showModal());
onBeforeUnmount(() => dialog.value?.close());
</script>

<template>
  <Teleport to="body">
    <dialog
      ref="dialog"
      class="ui-dialog renter-dialog"
      aria-labelledby="renter-dialog-title"
      @cancel="cancel"
      @click.self="close"
    >
      <div class="modal-content">
        <div class="modal-header">
          <h2
            id="renter-dialog-title"
            class="modal-title"
          >
            Добавить арендатора
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
        <RenterForm
          ref="form"
          modal
          @saved="created"
          @cancel="close"
        />
      </div>
    </dialog>
  </Teleport>
</template>
