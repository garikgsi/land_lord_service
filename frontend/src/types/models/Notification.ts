export interface Notification {
  id: number;
  kind: string;
  title: string;
  message: string;
  sourcePath: string | null;
  dueDate: string | null;
  createdAt: string;
  readAt: string | null;
}

export interface NotificationCount {
  unreadCount: number;
}

export interface NotificationRow extends Notification {
  dateLabel: string;
  dueLabel: string;
  statusLabel: string;
  unread: boolean;
}
