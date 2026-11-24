import type { Contract } from '../models/Contract';

export interface ContractTableProps {
  items: Contract[];
  total?: number;
  page?: number;
  loading?: boolean;
  error?: boolean;
}

export interface ContractTableRow extends Contract {
  period: string;
  payment: string;
  statusLabel: string;
  statusColor: string;
}
