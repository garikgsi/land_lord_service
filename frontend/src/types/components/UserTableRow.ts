import type { User } from '../models/User';

export interface UserTableRow extends User {
  fullName: string;
  statusLabel: string;
  statusColor: string;
  invitationLabel: string;
}
