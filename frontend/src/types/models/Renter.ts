import type { NamedRecord } from './NamedRecord';

export interface Renter extends NamedRecord {
  inn: string | null;
  ownership: NamedRecord;
}
