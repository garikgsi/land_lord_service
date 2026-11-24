import type { ObjectUnityProvider, UnityProvider } from '../models/Unity';
import type { NamedRecord } from '../models/NamedRecord';
import type { Meter } from '../models/Meter';
import type { UnityProviderContract } from '../models/UnityProviderContract';

export interface ObjectUtilityGroup {
  id: string;
  association?: ObjectUnityProvider;
  unity: NamedRecord | null;
  unityProvider: UnityProvider | null;
  meters: (Meter & { badgeColor: string })[];
}

export interface ObjectUtilityRow extends ObjectUtilityGroup {
  contract: UnityProviderContract | null;
  contractNumLabel: string;
  expiryLabel: string;
  statusLabel: string;
  statusColor: string;
}
