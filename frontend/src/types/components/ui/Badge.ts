import type { RouteLocationRaw } from 'vue-router';

export interface BadgeProps {
  color?: string | null;
  to?: RouteLocationRaw;
  action?: boolean;
  wrap?: boolean;
  pill?: boolean;
  size?: 'normal' | 'small';
}
