import type { NamedRecord } from './NamedRecord';

export interface Unity extends NamedRecord {
  useMeter: boolean;
  unit: string | null;
}

export type UnityProvider = NamedRecord;

export interface ObjectUnityProvider {
  id: number;
  objectId: number;
  name: string;
  unity: Unity;
  unityProvider: UnityProvider;
}
