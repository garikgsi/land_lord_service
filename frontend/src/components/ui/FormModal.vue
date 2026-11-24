<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, useId } from 'vue';
import type { FormModalProps } from '../../types/components/ui/FormModal';

const props = defineProps<FormModalProps>();
const emit = defineEmits<{ close: [] }>();
const dialog = ref<HTMLDialogElement | null>(null);
const titleId = useId() + '-title';

function close() {
  if (!props.busy) emit('close');
}

function escape(event: Event) {
  event.preventDefault();
  close();
}

onMounted(() => dialog.value?.showModal());
onBeforeUnmount(() => dialog.value?.close());
</script>

<template>
  <Teleport to="body">
    <dialog
      ref="dialog"
      class="ui-dialog"
      :class="{ 'ui-dialog-wide': wide }"
      :aria-labelledby="titleId"
      :aria-busy="!!busy"
      @cancel="escape"
      @click.self="close"
    >
      <div class="modal-content">
        <div class="modal-header">
          <h2
            :id="titleId"
            class="modal-title"
          >
            {{ title }}
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
        <slot>
        </slot>
      </div>
    </dialog>
  </Teleport>
</template>
