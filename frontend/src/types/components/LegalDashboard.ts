import type { ContractTableProps } from './ContractTable';
import type { Contract } from '../models/Contract';
import type { UnityProviderContract } from '../models/UnityProviderContract';

export interface LegalContractRow extends Contract {
  objectLabel: string;
  showObject: boolean;
  renterLabel: string;
  squareLabel: string;
  paymentLabel: string;
  expiryLabel: string;
  indexationLabel: string;
  percentLabel: string;
  statusLabel: string;
  statusColor: string;
  expiryAttention: boolean;
  indexationAttention: boolean;
}

export interface UnityProviderContractRow extends UnityProviderContract {
  showObject: boolean;
  verificationLabels: Record<number, string>;
  communicationLabel: string;
  meterColors: Record<number, string>;
  statusLabel: string;
  statusColor: string;
  expiryLabel: string;
}

export interface UnityProviderContractsTableProps {
  objectId?: number;
  unityProviderId?: number;
  dashboard?: boolean;
}

export interface RentalContractsTableProps extends ContractTableProps {
  caption: string;
  showObject?: boolean;
  showActions?: boolean;
  preview?: boolean;
}
