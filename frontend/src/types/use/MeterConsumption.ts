export interface MeterConsumptionPeriod {
  id: number;
  label: string;
  detail: string;
  value: number;
  reading: number;
  date: string;
}

export interface MeterConsumptionSummary {
  periods: MeterConsumptionPeriod[];
  change: number | null;
  changeLabel: string;
  changeDescription: string;
  color: 'red' | 'green' | 'secondary';
}
