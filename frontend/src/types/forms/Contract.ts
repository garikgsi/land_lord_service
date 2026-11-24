export interface ContractDraft {
  objectId: number | null;
  renterId: number | null;
  contractTypeId: number | null;
  isSigned: boolean;
  contractNum: string;
  contractDate: string;
  expiryDate: string;
  monthlyPayment: string;
  securityPayment: string;
  square: string;
  spaceName: string;
  increaseDate: string;
  increasePercent: string;
}
