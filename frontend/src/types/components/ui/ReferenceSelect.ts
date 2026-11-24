import type { ReferenceInputProps } from './ReferenceInput';

export interface ReferenceSelectProps extends ReferenceInputProps {
  allowCreate?: boolean;
}

export type ReferenceCreationKind =
  | 'meters'
  | 'land-lords'
  | 'renters'
  | 'unities'
  | 'unity-providers'
  | 'ownerships'
  | 'contract-types'
  | 'object-provider';
