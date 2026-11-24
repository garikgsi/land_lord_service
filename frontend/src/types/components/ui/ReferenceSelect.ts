import type { ReferenceInputProps } from './ReferenceInput';

export interface ReferenceSelectProps extends ReferenceInputProps {
  allowCreate?: boolean;
}

export type ReferenceCreationKind =
  | 'land-lords'
  | 'renters'
  | 'unities'
  | 'unity-providers'
  | 'ownerships'
  | 'contract-types'
  | 'object-provider';
