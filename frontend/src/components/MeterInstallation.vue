<script setup lang="ts">
import { computed, ref } from 'vue';
import MeterDetachButton from './MeterDetachButton.vue';
import { useAuthStore } from '../store/auth';
import type { Meter } from '../types/models/Meter';
import type { MeterInstallationProps } from '../types/components/MeterInstallation';

const props = defineProps<MeterInstallationProps>();
const emit = defineEmits<{ updated: [meter: Meter]; createReading: [] }>();
const auth = useAuthStore();
const detachButton = ref<InstanceType<typeof MeterDetachButton> | null>(null);
const saving = computed(() => detachButton.value?.saving ?? false);
const disabled = computed(() => saving.value || props.disabled);

defineExpose({ saving });
</script>

<template>
  <button
    v-if="auth.canEditMeterReadings"
    type="button"
    class="btn btn-primary"
    :disabled="disabled"
    @click="emit('createReading')"
  >
    Передать показания
  </button>
  <MeterDetachButton
    ref="detachButton"
    :meter="meter"
    :disabled="disabled"
    @updated="emit('updated', $event)"
  />
</template>
