import type { Role } from './Role';

export interface User {
  id: number;
  email: string;
  firstName: string | null;
  lastName: string | null;
  roles: Role[];
  phone: string | null;
  position: string | null;
  avatarUrl: string | null;
  blocked: boolean;
  active: boolean;
  createdAt: string;
}
