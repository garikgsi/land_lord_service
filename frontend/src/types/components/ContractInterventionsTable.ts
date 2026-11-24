import type { ContractIntervention } from '../models/ContractIntervention';

export interface ContractInterventionRow extends ContractIntervention {
  dateLabel: string;
  reasonLabel: string;
  dateClass: string;
  partyLabel: string;
  contractTypeLabel: string;
  editPath: string;
}
