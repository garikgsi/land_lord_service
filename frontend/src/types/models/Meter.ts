import type { Unity, ObjectUnityProvider } from './Unity';
import type { LandLord } from './LandLord';
import type { NamedRecord } from './NamedRecord';

export interface MeterInstallation extends NamedRecord {
  type: 'object' | 'space';
  object: NamedRecord & { readingDay: number; landLord: LandLord };
  comment: string | null;
  provider: ObjectUnityProvider | null;
}

export type MeterReadingSubmissionStatus = 'on_time' | 'late';

export interface MeterReading {
  id: number;
  meterId: number;
  meterReadingDate: string;
  meterRead: number;
  previousRead?: number;
  readingDueDate?: string | null;
  submissionStatus?: MeterReadingSubmissionStatus | null;
  consumption?: number;
  intervalFrom?: string;
  intervalTo?: string;
}

export interface MeterVerification {
  id: number;
  meterId: number;
  verificationDate: string;
}

export interface Meter extends NamedRecord {
  utility: Unity;
  createDate: string;
  startRead: number;
  serialNumber: string | null;
  verificationIntervalDays: number;
  verificationDate: string | null;
  nextVerificationDate: string;
  verificationExpired: boolean;
  readingDay: number | null;
  effectiveReadingDay: number | null;
  readingDueDate: string | null;
  nextReadingDueDate: string | null;
  readingAttention: boolean;
  readingRequired: boolean;
  readingOverdue: boolean;
  readingSubmitted: boolean;
  readingSubmittedLate: boolean;
  installation: MeterInstallation | null;
  lastReading: MeterReading | null;
}
