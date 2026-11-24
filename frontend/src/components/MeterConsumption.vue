<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { IconArrowDown, IconArrowUp } from '@tabler/icons-vue';
import RadialComparisonChart from './ui/RadialComparisonChart.vue';
import useApi from '../use/api';
import { meterConsumptionSummary } from '../use/meterConsumption';
import type { MeterDetailsProps } from '../types/components/MeterDetails';
import type { MeterReading } from '../types/models/Meter';
import type { RegistryList } from '../types/api/Registry';
import type { RadialComparisonSeries } from '../types/components/ui/RadialComparisonChart';

const props = defineProps<MeterDetailsProps>();
const { api, errorText } = useApi();
const items = ref<MeterReading[]>([]);
const loading = ref(true);
const error = ref('');
const summary = computed(() => meterConsumptionSummary(items.value, props.meter.utility.unit));
const series = computed<RadialComparisonSeries[]>(() =>
  summary.value.periods.map((period, index) => ({
    ...period,
    color: index === 0 ? 'azure' : 'indigo',
  })),
);
const latest = computed(() => summary.value.periods[0]);
const currentReading = computed(() =>
  latest.value ? latest.value.reading.toLocaleString('ru-RU') : '—',
);
const consumption = computed(() =>
  latest.value ? latest.value.value.toLocaleString('ru-RU') : '—',
);
const changeClass = computed(
  () => 'd-inline-flex align-items-center gap-1 small text-' + summary.value.color,
);
const changeIcon = computed(() =>
  summary.value.color === 'red'
    ? IconArrowUp
    : summary.value.color === 'green'
      ? IconArrowDown
      : null,
);
let controller: AbortController | undefined;

async function load() {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;
  error.value = '';

  try {
    const result = await api<RegistryList<MeterReading>>(
      '/api/meters/' + props.meter.id + '/readings',
      {
        signal: current.signal,
      },
    );

    if (!current.signal.aborted) items.value = result.items;
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
  <aside
    class="card meter-consumption"
    aria-label="Динамика потребления"
  >
    <div class="card-body">
      <h2 class="text-secondary text-uppercase small fw-medium mb-1">
        Текущие показания
      </h2>
      <div
        v-if="loading"
        class="text-secondary py-4"
        role="status"
      >
        Загрузка…
      </div>
      <div
        v-else-if="error"
        class="text-danger small"
        role="alert"
      >
        {{ error }}
        <button
          type="button"
          class="btn btn-sm mt-2"
          @click="load"
        >
          Повторить
        </button>
      </div>
      <template v-else>
        <div class="d-flex align-items-baseline flex-wrap gap-2 mb-2">
          <span class="h2 mb-0">
            {{ currentReading }}
          </span>
          <span class="text-secondary small">
            {{ meter.utility.unit }}
          </span>
          <span
            :class="changeClass"
            :title="summary.changeDescription"
            :aria-label="summary.changeDescription"
          >
            {{ summary.changeLabel }}
            <component
              :is="changeIcon"
              v-if="changeIcon"
              :size="14"
              aria-hidden="true"
            />
          </span>
        </div>
        <RadialComparisonChart
          v-if="latest"
          :series="series"
          label="Потребление"
          :value="consumption"
          :unit="meter.utility.unit"
          :value-color="summary.color"
        />
        <p
          v-else
          class="text-secondary small mb-0 py-4"
        >
          Показания ещё не передавались.
        </p>
        <p
          v-if="latest && series.length < 2"
          class="text-secondary small mt-3 mb-0"
        >
          Для сравнения нужно ещё одно показание.
        </p>
      </template>
    </div>
  </aside>
</template>

<style scoped>
.meter-consumption {
  container-type: inline-size;
  align-self: start;
  width: 100%;
  max-width: 19rem;
  margin-inline: auto;
}
</style>
