<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import RentalObjectArea from './RentalObjectArea.vue';
import useApi from '../use/api';
import type { RentalObjectOverviewProps } from '../types/components/RentalObjectOverview';
import type { SpaceAvailability } from '../types/api/SpaceAvailability';

const props = defineProps<RentalObjectOverviewProps>();
const { api, errorText } = useApi();
const area = ref<SpaceAvailability>();
const loading = ref(true);
const error = ref('');
let controller: AbortController | undefined;

async function reload() {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;
  error.value = '';

  try {
    const availability = await api<SpaceAvailability>(
      '/api/objects/' + props.objectId + '/availability',
      { signal: current.signal },
    );

    if (current.signal.aborted) return;

    area.value = availability;
  } catch (e) {
    if (!current.signal.aborted) error.value = errorText(e);
  } finally {
    if (!current.signal.aborted) loading.value = false;
  }
}

onMounted(reload);
onBeforeUnmount(() => controller?.abort());
defineExpose({ reload });
</script>

<template>
  <div :aria-busy="loading">
    <p
      v-if="loading && !area"
      class="text-secondary"
      role="status"
    >
      Загрузка площади…
    </p>
    <div
      v-if="error"
      class="alert alert-danger"
      role="alert"
    >
      {{ error }}
      <button
        type="button"
        class="btn btn-sm ms-2"
        @click="reload"
      >
        Повторить
      </button>
    </div>
    <RentalObjectArea
      v-if="area"
      :area="area"
    />
  </div>
</template>
