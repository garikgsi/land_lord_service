import type { Meter } from '../models/Meter';

export interface ContractMetersProps {
  objectId: number;
  spaceId: number;
}

export interface ContractMeterRow extends Meter {
  verificationLabel: string;
  readingLabel: string;
  badgeColor: string;
}

export type ContractMeterAction = 'readings' | 'detach';
