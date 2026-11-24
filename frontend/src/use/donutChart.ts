import type { ApexDonutOptions } from '../types/use/ApexChart';
import type { DonutChartProps } from '../types/components/ui/DonutChart';

export function donutChartOptions(props: DonutChartProps): ApexDonutOptions {
  return {
    chart: {
      type: 'donut',
      height: 240,
      fontFamily: 'inherit',
      background: 'transparent',
      foreColor: 'var(--tblr-body-color)',
      parentHeightOffset: 0,
      animations: { enabled: false },
      toolbar: { show: false },
    },
    series: props.series.map((item) => item.value),
    labels: props.series.map((item) => item.label),
    colors: props.series.map((item) => 'var(--tblr-' + item.color + ')'),
    theme: { tokens: true },
    stroke: { width: 2, colors: ['var(--tblr-bg-surface)'] },
    dataLabels: { enabled: false },
    plotOptions: {
      pie: {
        expandOnClick: false,
        donut: {
          size: '70%',
          labels: {
            show: true,
            name: { show: true, fontSize: '12px', color: 'var(--tblr-secondary)' },
            value: {
              show: true,
              fontSize: '22px',
              fontWeight: 600,
              color: 'var(--tblr-body-color)',
              formatter: () => props.total,
            },
            total: {
              show: true,
              showAlways: true,
              label: props.totalLabel,
              color: 'var(--tblr-secondary)',
              fontSize: '12px',
              formatter: () => props.total,
            },
          },
        },
      },
    },
    legend: {
      show: true,
      showForSingleSeries: true,
      position: 'bottom',
      horizontalAlign: 'left',
      fontSize: '13px',
      offsetX: 0,
      offsetY: 0,
      markers: { size: 4 },
      formatter: (name, { seriesIndex }) =>
        (name + ' · ' + (props.series[seriesIndex]?.valueLabel ?? '—'))
          .replaceAll('&', '&amp;')
          .replaceAll('<', '&lt;')
          .replaceAll('>', '&gt;'),
      onItemClick: { toggleDataSeries: false },
      onItemHover: { highlightDataSeries: true },
    },
    tooltip: {
      enabled: true,
      fillSeriesColor: false,
      y: { formatter: (_value, { seriesIndex }) => props.series[seriesIndex]?.valueLabel ?? '—' },
    },
  };
}
