import type { Contract } from '../models/Contract';
import type { UnityProviderContract } from '../models/UnityProviderContract';

export interface ContractDetailsProps {
  contract: Contract;
}

export interface UnityProviderContractDetailsProps {
  contract: UnityProviderContract;
}

export interface ContractDetailsModalProps {
  kind: 'rental' | 'utility';
  contractId: number;
}
