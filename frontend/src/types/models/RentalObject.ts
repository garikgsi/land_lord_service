import type { LandLord } from './LandLord';

export interface RentalObject {
  id: number;
  name: string;
  address: string;
  square: string;
  landLord: LandLord;
  readingDay: number;
}
