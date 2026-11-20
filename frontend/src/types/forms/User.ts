import type { User } from '../models/User';

export type UserForm = Pick<
  User,
  'email' | 'firstName' | 'lastName' | 'phone' | 'position' | 'roles' | 'blocked'
>;
export interface UserFormDraft extends UserForm {
  firstName: string;
  lastName: string;
  phone: string;
  position: string;
}
