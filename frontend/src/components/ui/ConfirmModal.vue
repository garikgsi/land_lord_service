<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, useId } from 'vue';
import type { ConfirmModalProps } from '../../types/components/ui/ConfirmModal';

const props = defineProps<ConfirmModalProps>();
const emit = defineEmits<{ confirm: []; cancel: [] }>();
const dialog = ref<HTMLDialogElement | null>(null);
const cancelButton = ref<HTMLButtonElement | null>(null);
const id = useId();

function cancel() {
  if (!props.busy) emit('cancel');
}

function escape(event: Event) {
  event.preventDefault();
  cancel();
}

onMounted(() => {
  dialog.value?.showModal();
  cancelButton.value?.focus();
});

onBeforeUnmount(() => dialog.value?.close());
</script>

<template>
  <Teleport to="body">
    <dialog
      ref="dialog"
      class="ui-dialog delete-confirm-dialog"
      role="alertdialog"
      :aria-labelledby="id + '-title'"
      :aria-describedby="id + '-message'"
      :aria-busy="!!busy"
      @cancel="escape"
      @click.self="cancel"
    >
      <div class="modal-content">
        <div class="modal-status bg-primary">
        </div>
        <div class="modal-body py-4">
          <h2
            :id="id + '-title'"
            class="h3"
          >
            {{ title }}
          </h2>
          <p
            :id="id + '-message'"
            class="mb-0"
          >
            {{ message }}
          </p>
        </div>
        <div class="modal-footer">
          <button
            ref="cancelButton"
            type="button"
            class="btn btn-secondary"
            :disabled="busy"
            @click="cancel"
          >
            Нет
          </button>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="busy"
            @click="emit('confirm')"
          >
            Да
          </button>
        </div>
      </div>
    </dialog>
  </Teleport>
</template>
