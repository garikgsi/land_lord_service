export interface ContractHistoryState {
  contractNum: string;
  contractDate: string;
  expiryDate: string;
  monthlyPayment: string;
  securityPayment: string;
  annulmentDate: string | null;
  lastIndexationDate: string | null;
  increaseDate: string | null;
  increasePercent: string | null;
  increaseIntervalMonths: number | null;
  renter: { id: number; name: string };
  landLord: { id: number; name: string };
  contractTypeId: number;
}

export interface ContractHistory {
  id: number;
  type: 'created' | 'amendment' | 'termination' | 'indexation';
  validFrom: string | null;
  validTo: string | null;
  current: boolean;
  previousData: Partial<ContractHistoryState>;
  nextData: ContractHistoryState;
}
