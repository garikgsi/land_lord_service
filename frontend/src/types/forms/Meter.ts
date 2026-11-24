import type { NamedRecord } from '../models/NamedRecord';

export interface MeterDraft {
  utilityId: number | null;
  createDate: string;
  startRead: string;
  serialNumber: string;
  verificationIntervalDays: string;
  verificationDate: string;
  readingDay: string;
}

export interface MeterLocationDraft {
  type: 'object' | 'space';
  objectId: number | null;
  spaceId: number | null;
  objectUnityProviderId: number | null;
  comment: string;
}

export interface MeterLocationProps {
  fixedLocation?: boolean;
  initialObject?: NamedRecord;
  initialSpace?: NamedRecord;
  utilityId?: number | null;
  initialProvider?: NamedRecord;
  disabled?: boolean;
  errors?: Record<string, string>;
}

export interface MeterTableProps {
  objectId?: number;
  spaceId?: number;
}

export type UtilityKind = 'unities' | 'unity-providers';

export interface UtilityRegistryProps {
  kind: UtilityKind;
}

export interface ObjectUnityProvidersProps {
  objectId: number;
}
