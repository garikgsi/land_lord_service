import type { Meter } from '../models/Meter';

export interface MeterReadingBadge {
  label: string;
  color: string | null;
  title?: string;
}

export type MeterSubmissionState = Pick<
  Meter,
  | 'installation'
  | 'readingRequired'
  | 'readingOverdue'
  | 'readingDueDate'
  | 'readingSubmitted'
  | 'readingSubmittedLate'
>;
