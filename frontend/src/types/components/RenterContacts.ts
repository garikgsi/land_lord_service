import type { RenterContact } from '../models/RenterContact';

export interface RenterContactsProps {
  renterId: number;
}

export interface RenterContactRow extends RenterContact {
  fullName: string;
}
