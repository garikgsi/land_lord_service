import type { NamedRecord } from '../models/NamedRecord';

export interface MeterSelectProps {
  id: string;
  utilityId: number | null;
  initial?: NamedRecord;
  disabled?: boolean;
  error?: string;
}
