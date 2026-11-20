import type { User } from '../models/User';

export interface StoredSession {
  accessToken: string;
  expiresAt: string;
}
export interface AuthSession extends StoredSession {
  user: User;
}
export interface InvitationProfile {
  email: string;
  firstName: string | null;
  lastName: string | null;
  phone: string | null;
  position: string | null;
  hasAvatar: boolean;
}
