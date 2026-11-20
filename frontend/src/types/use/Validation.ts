import type { User } from '../models/User';
export type UserProfileValidationInput = Pick<User, 'firstName' | 'email' | 'phone'>;
export type UserProfileErrors = Partial<Record<keyof UserProfileValidationInput, string>>;
