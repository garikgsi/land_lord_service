export interface RadialComparisonSeries {
  id: number;
  label: string;
  detail: string;
  value: number;
  color: 'azure' | 'indigo';
}

export interface RadialComparisonChartProps {
  series: readonly RadialComparisonSeries[];
  label: string;
  value: string;
  unit?: string | null;
  valueColor?: 'red' | 'green' | 'secondary';
}
