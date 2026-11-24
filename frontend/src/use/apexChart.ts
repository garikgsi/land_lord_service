import chartUrl from '@tabler/core/dist/libs/apexcharts/dist/apexcharts.min.js?url';
import type { ApexChartConstructor } from '../types/use/ApexChart';

let chartReady: Promise<ApexChartConstructor> | undefined;

export default function useApexChart(): Promise<ApexChartConstructor> {
  chartReady ??= new Promise<ApexChartConstructor>((resolve, reject) => {
    const script = document.createElement('script');

    function failed() {
      script.remove();
      chartReady = undefined;
      reject(new Error('Не удалось загрузить график.'));
    }

    script.src = chartUrl;
    script.async = true;
    script.onload = () => {
      if (window.ApexCharts) resolve(window.ApexCharts);
      else failed();
    };

    script.onerror = failed;
    document.head.append(script);
  });

  return chartReady;
}
