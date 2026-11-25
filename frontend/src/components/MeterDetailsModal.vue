<script setup lang="ts">
import useCreateNavigation from '../use/createNavigation';
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import { RouterLink } from 'vue-router';
import FormModal from './ui/FormModal.vue';
import MeterDetails from './MeterDetails.vue';
import MeterDeadlines from './MeterDeadlines.vue';
import useApi from '../use/api';
import type { Meter } from '../types/models/Meter';
import type { MeterDetailsModalProps } from '../types/components/MeterDetailsModal';

const { fromTable } = useCreateNavigation();
const props = defineProps<MeterDetailsModalProps>();
const emit = defineEmits<{ close: [] }>();
const { api, errorText } = useApi();
const record = ref<Meter>();
const error = ref('');
const loading = ref(true);
const title = computed(() => 'Счетчик ' + (record.value?.name ?? ''));
const cardRoute = computed(() => fromTable('/meters/' + props.meterId));
let controller: AbortController | undefined;

async function load() {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;
  error.value = '';

  try {
    const meter = await api<Meter>('/api/meters/' + props.meterId, { signal: current.signal });

    if (!current.signal.aborted) record.value = meter;
  } catch (e) {
    if (!current.signal.aborted) error.value = errorText(e);
  } finally {
    if (!current.signal.aborted) loading.value = false;
  }
}

onMounted(load);
onBeforeUnmount(() => controller?.abort());
</script>

<template>
  <FormModal
    :title="title"
    wide
    @close="emit('close')"
  >
    <div class="modal-body">
      <p
        v-if="loading"
        role="status"
      >
        Загрузка…
      </p>
      <div
        v-if="error"
        class="alert alert-danger"
        role="alert"
      >
        {{ error }}
        <button
          type="button"
          class="btn ms-2"
          @click="load"
        >
          Повторить
        </button>
      </div>
      <template v-if="record">
        <MeterDeadlines :meter="record" />
        <MeterDetails :meter="record" />
      </template>
    </div>
    <div class="modal-footer">
      <RouterLink
        v-if="record"
        :to="cardRoute"
        class="btn btn-primary"
      >
        К счетчику
      </RouterLink>
      <button
        type="button"
        class="btn"
        @click="emit('close')"
      >
        Закрыть
      </button>
    </div>
  </FormModal>
</template>
