import { onBeforeUnmount, onMounted, onUpdated, ref } from 'vue';
import type { ComputedRef } from 'vue';
import useApexChart from './apexChart';
import type { ApexChartInstance, ApexChartOptions } from '../types/use/ApexChart';

export default function useApexChartView(
  options: ComputedRef<ApexChartOptions>,
  signature: ComputedRef<string>,
) {
  const element = ref<HTMLDivElement | null>(null);
  const loading = ref(true);
  const error = ref('');
  let chart: ApexChartInstance | undefined;
  let lastValue = '';
  let disposed = false;

  async function sync() {
    if (!chart || lastValue === signature.value || disposed) return;

    lastValue = signature.value;

    try {
      await chart.updateOptions(options.value, true, false);
    } catch {
      if (!disposed) error.value = 'Не удалось обновить график.';
    }
  }

  async function initialize() {
    error.value = '';
    loading.value = true;

    try {
      const Chart = await useApexChart();

      if (disposed || !element.value) return;

      chart?.destroy();
      chart = new Chart(element.value, options.value);
      lastValue = signature.value;
      await chart.render();
      await sync();
    } catch {
      if (!disposed) error.value = 'Не удалось загрузить график.';
    } finally {
      if (!disposed) loading.value = false;
    }
  }

  onMounted(initialize);
  // ApexCharts owns its DOM; Vue prop changes are forwarded through the SDK.
  onUpdated(sync);
  onBeforeUnmount(() => {
    disposed = true;
    chart?.destroy();
  });

  return { element, loading, error, initialize };
}
