import type { ContractHistory } from '../models/ContractHistory';

export interface ContractHistoryProps {
  contractId: number;
}

export interface ContractHistoryRow extends ContractHistory {
  title: string;
  dateLabel: string;
  changes: string[];
}
