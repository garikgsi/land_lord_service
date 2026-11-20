import type { User } from '../models/User';

export interface UserList {
  items: User[];
  total: number;
  page: number;
  pageSize: number;
}
export interface UserSaveResponse extends User {
  invitationSent?: boolean;
  message?: string;
}
