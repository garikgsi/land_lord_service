export interface NotificationType {
  id: number;
  name: string;
  days: number;
  roles: string[];
  isActive: boolean;
  timing: 'lead' | 'repeat';
}
