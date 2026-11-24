import type { NamedRecord } from './NamedRecord';
import type { Meter } from './Meter';

export interface UnityProviderContract {
  id: number;
  object: NamedRecord;
  unityProvider: NamedRecord;
  contractNum: string | null;
  contractDate: string | null;
  expiryDate: string;
  isSuspended: boolean;
  status: 'active' | 'suspended' | 'finished';
  unities: NamedRecord[];
  meterId: number | null;
  meters: Meter[];
}
