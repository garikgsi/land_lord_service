import type { Meter } from '../models/Meter';

export interface MeterRecordsProps {
  meter?: Meter;
  meterId: number;
  kind: 'readings' | 'verifications';
}

export interface MeterRecordRow {
  id: number;
  date: string;
  previousValue: number | string;
  value: number | string;
  consumption: number | string;
  month: string;
  statusLabel: string;
  statusColor: string | null;
  deadlineLabel: string;
}
