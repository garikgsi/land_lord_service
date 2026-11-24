export interface DonutSeries {
  label: string;
  value: number;
  valueLabel: string;
  color: string;
}

export interface DonutChartProps {
  series: readonly DonutSeries[];
  total: string;
  totalLabel: string;
}
