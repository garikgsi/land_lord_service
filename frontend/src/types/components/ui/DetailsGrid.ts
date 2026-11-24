import type { RouteLocationRaw } from 'vue-router';

export interface DetailField {
  label: string;
  value: string;
  to?: RouteLocationRaw;
  color?: string;
}

export interface DetailsGridProps {
  fields: readonly DetailField[];
}
