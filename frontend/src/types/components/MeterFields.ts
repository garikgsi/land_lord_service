import type { NamedRecord } from '../models/NamedRecord';

export interface MeterFieldsProps {
  idPrefix?: string;
  initialUtility?: NamedRecord;
  disabled?: boolean;
  readonlyVerification?: boolean;
  fixedUtility?: boolean;
  showReadingDay?: boolean;
  readingDayPlaceholder?: string;
  errors: Record<string, string>;
  validation: Record<string, string>;
}

export interface MeterCreateFormProps {
  utilityId?: number;
  modal?: boolean;
}
