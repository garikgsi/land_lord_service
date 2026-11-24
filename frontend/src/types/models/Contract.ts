import type { Space } from './Space';
import type { Renter } from './Renter';
import type { LandLord } from './LandLord';
import type { NamedRecord } from './NamedRecord';

export type ContractStatus = 'active' | 'upcoming' | 'finished' | 'cancelled';

export interface ContractAttentionDates {
  expiry: string | null;
  indexation: string | null;
}

export interface Contract {
  id: number;
  validFrom: string | null;
  isSigned: boolean;
  contractNum: string;
  contractDate: string;
  expiryDate: string;
  annulmentDate: string | null;
  effectiveEnd: string;
  monthlyPayment: string;
  securityPayment: string;
  lastIndexationDate: string | null;
  increaseDate: string | null;
  increasePercent: string | null;
  increaseIntervalMonths: number | null;
  nextIndexationReminderDate: string | null;
  space: Space;
  renter: Renter;
  landLord: LandLord;
  contractType: NamedRecord;
  status: ContractStatus;
  canRecontract: boolean;
  attentionDates: ContractAttentionDates;
}
