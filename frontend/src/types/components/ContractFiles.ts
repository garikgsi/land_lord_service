import type { ContractFile } from '../models/ContractFile';

export interface ContractFilesProps {
  contractId: number;
  readonly?: boolean;
}

export interface ContractFileRow extends ContractFile {
  sizeLabel: string;
}
