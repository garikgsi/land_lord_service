<script setup lang="ts">
import { computed } from 'vue';
import useApexChartView from '../../use/apexChartView';
import { donutChartOptions } from '../../use/donutChart';
import type { DonutChartProps } from '../../types/components/ui/DonutChart';

const props = defineProps<DonutChartProps>();
const options = computed(() => donutChartOptions(props));
const signature = computed(() => JSON.stringify([props.series, props.total, props.totalLabel]));
const description = computed(() =>
  props.series.map((item) => item.label + ': ' + item.valueLabel).join('. '),
);
const { element, loading, error, initialize } = useApexChartView(options, signature);
</script>

<template>
  <div
    class="donut-chart"
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
      class="donut-chart-plot"
    >
    </div>
  </div>
</template>

<style scoped>
.donut-chart-plot {
  min-height: 240px;
}
</style>
