import type { Contract } from '../models/Contract';

export type ContractAction = 'amendment' | 'termination' | 'indexation';

export interface ContractActionsProps {
  contract: Contract;
  table?: boolean;
  hideMenu?: boolean;
}

export interface ContractActionDraft {
  renterId: number | null;
  expiryDate: string;
  monthlyPayment: string;
  annulmentDate: string;
  increaseDate: string;
  increasePercent: string;
  newMonthlyPayment: string;
  periodic: boolean;
  intervalMonths: string;
}
