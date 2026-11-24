import type { Renter } from '../models/Renter';

export interface RenterFormProps {
  initial?: Renter;
  modal?: boolean;
}

export type RenterTab = 'main' | 'contacts';
