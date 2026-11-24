import type { NamedRecord } from './NamedRecord';
import type { SpaceAvailability } from '../api/SpaceAvailability';

export interface Space extends NamedRecord {
  square: string;
  object: NamedRecord;
  active: boolean;
  contractEndDate: string | null;
  contractAttention: boolean;
}

export interface SpaceList extends SpaceAvailability {
  items: Space[];
  total: number;
  page: number;
  pageSize: number;
}
