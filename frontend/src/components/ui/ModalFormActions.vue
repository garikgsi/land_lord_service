<script setup lang="ts">
import { computed } from 'vue';
import type { ModalFormActionsProps } from '../../types/components/ui/ModalFormActions';

const props = defineProps<ModalFormActionsProps>();
const emit = defineEmits<{ close: [] }>();
const saveLabel = computed(() => (props.saving ? 'Сохраняем…' : 'Сохранить'));
</script>

<template>
  <div class="modal-footer justify-content-between">
    <button
      type="submit"
      class="btn btn-primary"
      :disabled="saving || !canSave"
    >
      {{ saveLabel }}
    </button>
    <button
      type="button"
      class="btn ms-auto"
      :disabled="saving"
      @click="emit('close')"
    >
      Закрыть
    </button>
  </div>
</template>
