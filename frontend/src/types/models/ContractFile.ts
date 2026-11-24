export interface ContractFile {
  id: number;
  name: string;
  sizeBytes: number;
  createdAt: string;
}

export interface ContractFileList {
  items: ContractFile[];
}
