import type { Meter } from '../models/Meter';
import type { MeterReadingBadge } from '../use/MeterReading';

export interface MeterTableRow extends Meter {
  readingBadge: MeterReadingBadge;
}
