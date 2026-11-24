<script setup lang="ts">
import { computed, ref } from 'vue';
import { IconPlugOff } from '@tabler/icons-vue';
import MeterDetachModal from './MeterDetachModal.vue';
import { useAuthStore } from '../store/auth';
import type { Meter } from '../types/models/Meter';
import type { MeterDetachButtonProps } from '../types/components/MeterDetachButton';

const props = defineProps<MeterDetachButtonProps>();
const emit = defineEmits<{ updated: [meter: Meter] }>();
const auth = useAuthStore();
const detachOpen = ref(false);
const modal = ref<InstanceType<typeof MeterDetachModal> | null>(null);
const saving = computed(() => modal.value?.saving ?? false);
const disabled = computed(() => saving.value || props.disabled);
const buttonClass = computed(() =>
  props.iconOnly ? 'btn btn-sm btn-icon text-primary' : 'btn btn-danger',
);

function updated(meter: Meter) {
  detachOpen.value = false;
  emit('updated', meter);
}

defineExpose({ saving });
</script>

<template>
  <button
    v-if="auth.canInstallMeters && meter.installation"
    type="button"
    :class="buttonClass"
    title="Снять"
    aria-label="Снять счётчик"
    :disabled="disabled"
    @click="detachOpen = true"
  >
    <IconPlugOff
      v-if="iconOnly"
      :size="18"
      aria-hidden="true"
    />
    <span v-else>
      Снять
    </span>
  </button>
  <MeterDetachModal
    v-if="detachOpen"
    ref="modal"
    :meter="meter"
    :disabled="props.disabled"
    @close="detachOpen = false"
    @updated="updated"
  />
</template>
