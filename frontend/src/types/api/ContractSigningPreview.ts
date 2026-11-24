export interface ContractSigningPeriod {
  periodMonth: string;
  periodStart: string;
  periodEnd: string;
  monthlyPayment: string;
  amount: string;
}

export interface ContractSigningPreview {
  asOf: string;
  monthCount: number;
  totalAmount: string;
  periods: ContractSigningPeriod[];
  confirmation: string;
}
