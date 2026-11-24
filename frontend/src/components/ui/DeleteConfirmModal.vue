<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useId } from 'vue';
import { IconAlertTriangle } from '@tabler/icons-vue';
import type { DeleteConfirmModalProps } from '../../types/components/ui/DeleteConfirmModal';

const props = withDefaults(defineProps<DeleteConfirmModalProps>(), {
  title: 'Подтверждение удаления',
  message: 'Удалить выбранный элемент?',
});
const emit = defineEmits<{ confirm: []; cancel: [] }>();
const dialog = ref<HTMLDialogElement | null>(null);
const cancelButton = ref<HTMLButtonElement | null>(null);
const id = useId();
const titleId = id + '-title';
const messageId = id + '-message';
const confirmLabel = computed(() => (props.busy ? 'Удаляем…' : 'Удалить'));

function cancel() {
  if (!props.busy) emit('cancel');
}

function confirm() {
  if (!props.busy) emit('confirm');
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
      :aria-labelledby="titleId"
      :aria-describedby="messageId"
      :aria-busy="!!busy"
      @cancel="escape"
      @click.self="cancel"
    >
      <div class="modal-content">
        <button
          type="button"
          class="btn-close position-absolute top-0 end-0 m-3"
          aria-label="Закрыть"
          :disabled="busy"
          @click="cancel"
        >
        </button>
        <div class="modal-status bg-danger">
        </div>
        <div class="modal-body text-center py-4">
          <IconAlertTriangle
            :size="48"
            class="text-danger mb-3"
            aria-hidden="true"
          />
          <h2
            :id="titleId"
            class="h3"
          >
            {{ title }}
          </h2>
          <div
            :id="messageId"
            class="text-secondary"
          >
            {{ message }}
            <div
              v-if="itemName"
              class="fw-semibold text-body mt-2 text-break"
            >
              {{ itemName }}
            </div>
          </div>
          <div
            v-if="error"
            class="alert alert-danger mt-3 mb-0 text-start"
            role="alert"
          >
            {{ error }}
          </div>
        </div>
        <div class="modal-footer">
          <div class="row g-2 w-100 m-0">
            <div class="col">
              <button
                ref="cancelButton"
                type="button"
                class="btn btn-secondary w-100"
                :disabled="busy"
                @click="cancel"
              >
                Не удалять
              </button>
            </div>
            <div class="col">
              <button
                type="button"
                class="btn btn-danger w-100"
                :disabled="busy"
                @click="confirm"
              >
                {{ confirmLabel }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </dialog>
  </Teleport>
</template>
