<script setup lang="ts">
import { computed } from 'vue';
import useApexChartView from '../../use/apexChartView';
import { radialComparisonOptions } from '../../use/radialComparison';
import type { RadialComparisonChartProps } from '../../types/components/ui/RadialComparisonChart';

const props = defineProps<RadialComparisonChartProps>();
const options = computed(() => radialComparisonOptions(props));
const signature = computed(() =>
  JSON.stringify([props.series, props.label, props.value, props.unit, props.valueColor]),
);
const description = computed(
  () =>
    props.label +
    ': ' +
    props.value +
    (props.unit ? ' ' + props.unit : '') +
    '. ' +
    props.series.map((item) => item.detail).join('. '),
);
const { element, loading, error, initialize } = useApexChartView(options, signature);
</script>

<template>
  <div
    class="radial-comparison"
    :aria-label="description"
    role="group"
  >
    <p
      v-if="loading"
      class="text-secondary small"
      role="status"
    >
      Загрузка графика…
    </p>
    <div
      v-if="error"
      class="text-danger small mb-2"
      role="alert"
    >
      {{ error }}
      <button
        type="button"
        class="btn btn-sm ms-2"
        @click="initialize"
      >
        Повторить
      </button>
    </div>
    <div
      ref="element"
      class="radial-comparison-plot"
    >
    </div>
  </div>
</template>

<style scoped>
.radial-comparison-plot {
  min-height: 290px;
}

.radial-comparison :deep(.radial-comparison-tooltip) {
  max-width: 17rem;
  padding: 0.5rem;
  white-space: normal;
  font-size: 0.75rem;
}

.radial-comparison :deep(.apexcharts-legend) {
  gap: 0.25rem;
}
</style>
