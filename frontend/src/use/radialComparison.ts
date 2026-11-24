import type { ApexRadialOptions } from '../types/use/ApexChart';
import type { RadialComparisonChartProps } from '../types/components/ui/RadialComparisonChart';

export function radialComparisonOptions(props: RadialComparisonChartProps): ApexRadialOptions {
  const series = props.series.slice(0, 2);
  const maximum = Math.max(1, ...series.map((item) => item.value));
  const valueColor = 'var(--tblr-' + (props.valueColor ?? 'secondary') + ')';

  return {
    chart: {
      type: 'radialBar',
      height: 290,
      fontFamily: 'inherit',
      background: 'transparent',
      foreColor: 'var(--tblr-body-color)',
      parentHeightOffset: 0,
      animations: { enabled: false },
      toolbar: { show: false },
    },
    series: series.map((item) => (item.value / maximum) * 100),
    labels: series.map((item) => item.label),
    colors: series.map((item) => 'var(--tblr-' + item.color + ')'),
    theme: { tokens: true },
    stroke: { lineCap: 'round' },
    fill: { type: 'solid' },
    plotOptions: {
      radialBar: {
        startAngle: -135,
        endAngle: 135,
        hollow: { size: '58%' },
        track: { background: 'var(--tblr-border-color)', strokeWidth: '100%', margin: 6 },
        dataLabels: {
          name: {
            fontSize: '12px',
            color: 'var(--tblr-secondary)',
            offsetY: -8,
            formatter: () => props.label,
          },
          value: {
            fontSize: '24px',
            fontWeight: 600,
            color: valueColor,
            offsetY: 20,
            formatter: () => props.value,
          },
          total: {
            show: true,
            label: props.label,
            color: 'var(--tblr-secondary)',
            fontSize: '12px',
            fontWeight: 400,
            formatter: () => props.value,
          },
        },
      },
    },
    legend: {
      show: true,
      showForSingleSeries: true,
      position: 'bottom',
      horizontalAlign: 'left',
      fontSize: '12px',
      offsetX: 0,
      offsetY: 0,
      markers: { size: 4 },
      formatter: (name, { seriesIndex }) =>
        (
          name +
          ' · ' +
          (series[seriesIndex]?.value.toLocaleString('ru-RU') ?? '—') +
          (props.unit ? ' ' + props.unit : '')
        )
          .replaceAll('&', '&amp;')
          .replaceAll('<', '&lt;')
          .replaceAll('>', '&gt;'),
      onItemClick: { toggleDataSeries: false },
      onItemHover: { highlightDataSeries: true },
    },
    tooltip: {
      enabled: true,
      fillSeriesColor: false,
      custom: ({ seriesIndex }) => {
        const tooltip = document.createElement('div');

        tooltip.className = 'radial-comparison-tooltip';
        tooltip.textContent = series[seriesIndex]?.detail ?? '';

        return tooltip;
      },
    },
  };
}
