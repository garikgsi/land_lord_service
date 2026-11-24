import type { NamedRecord } from '../models/NamedRecord';
import type { NamedRegistryKind } from './NamedRegistry';

export interface NamedCatalogueFormProps {
  initial?: NamedRecord;
  modal?: boolean;
  kind: NamedRegistryKind;
}
