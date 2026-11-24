<script setup lang="ts">
import { computed, ref } from 'vue';
import FormModal from './ui/FormModal.vue';
import { useAuthStore } from '../store/auth';
import useApi from '../use/api';
import type { Meter } from '../types/models/Meter';
import type { MeterInstallationProps } from '../types/components/MeterInstallation';

const props = defineProps<MeterInstallationProps>();
const emit = defineEmits<{ updated: [meter: Meter]; close: [] }>();
const auth = useAuthStore();
const { api, errorText } = useApi();
const error = ref('');
const saving = ref(false);
const disabled = computed(() => saving.value || props.disabled);

defineExpose({ saving });

function close() {
  if (!saving.value) emit('close');
}

async function detach() {
  if (disabled.value || !auth.canInstallMeters || !props.meter.installation) return;

  saving.value = true;
  error.value = '';

  try {
    const meter = await api<Meter>('/api/meters/' + props.meter.id + '/installation', {
      method: 'PUT',
      body: JSON.stringify({
        objectId: null,
        spaceId: null,
        comment: null,
        objectUnityProviderId: null,
      }),
    });

    emit('updated', meter);
  } catch (e) {
    error.value = errorText(e);
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <FormModal
    title="Снять прибор?"
    :busy="saving"
    @close="close"
  >
    <div class="modal-body">
      Показания и поверки сохранятся. Передача показаний больше не потребуется.
      <div
        v-if="error"
        class="alert alert-danger mt-3"
        role="alert"
      >
        {{ error }}
      </div>
    </div>
    <div class="modal-footer">
      <button
        type="button"
        class="btn"
        :disabled="saving"
        @click="close"
      >
        Не снимать
      </button>
      <button
        type="button"
        class="btn btn-danger"
        :disabled="saving"
        @click="detach"
      >
        Снять
      </button>
    </div>
  </FormModal>
</template>
