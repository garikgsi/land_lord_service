import type { CSSProperties } from 'vue';

export interface RegistryProps {
  kind: 'objects' | 'land-lords';
}

export interface RegistryTableRow {
  id: number;
  name: string;
  address: string;
  square: string;
  landLord: string;
  landLordId: number | null;
  landLordColor: CSSProperties;
  landLordColorLabel: string;
  landLordColorClass: string;
  landLordBadgeColor: string;
  path: string;
}

export type RentalObjectTab = 'main' | 'spaces' | 'utilities';
