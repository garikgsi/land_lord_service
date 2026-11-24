import type { NamedRecord } from './NamedRecord';

export type InterventionContractType = 'rental' | 'utility';

export type ContractInterventionReason = 'expiry' | 'indexation';

export interface ContractIntervention {
  id: string;
  contractId: number;
  contractType: InterventionContractType;
  isSigned: boolean | null;
  contractNum: string;
  interventionDate: string;
  reason: ContractInterventionReason;
  overdue: boolean;
  object: NamedRecord;
  space: NamedRecord | null;
  renter: NamedRecord | null;
  unityProvider: NamedRecord | null;
}
