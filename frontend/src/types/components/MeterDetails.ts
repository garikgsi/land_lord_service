import type { Meter } from '../models/Meter';
import type { DetailField } from './ui/DetailsGrid';

export interface MeterDetailsProps {
  meter: Meter;
}

export interface MeterDetailGroup {
  id: string;
  title: string;
  fields: DetailField[];
}
