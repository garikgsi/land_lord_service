import type { User } from '../models/User';

export interface UserTableRow extends User {
  fullName: string;
  statusLabel: string;
  statusClass: string;
  invitationLabel: string;
}
