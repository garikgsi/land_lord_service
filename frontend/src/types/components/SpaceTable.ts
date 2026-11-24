import type { Space } from '../models/Space';

export interface SpaceTableRow extends Space {
  statusLabel: string;
  statusColor: string;
}
