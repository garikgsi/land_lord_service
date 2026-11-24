import type { RouteLocationRaw } from 'vue-router';

export interface TableRecord {
  id: number | string;
}

export interface TableColumn<T extends TableRecord> {
  key: Extract<keyof T, string> | 'actions';
  label: string;
  class?: string;
  value?: (row: T) => string | number;
}

export interface DataTableProps<T extends TableRecord> {
  rows: readonly T[];
  columns: readonly TableColumn<T>[];
  caption: string;
  loading?: boolean;
  error?: boolean;
  emptyText?: string;
  rowRoute?: (row: T) => RouteLocationRaw;
  rowAction?: (row: T) => void;
  rowLabel?: (row: T) => string;
  total?: number;
  page?: number;
  pageSize?: number;
  clientPagination?: boolean;
  deleteEndpoint?: string;
  deleteAllowed?: (row: T) => boolean;
}

export interface DataTableSlots<T extends TableRecord> {
  header?: () => unknown;
  actions?: () => unknown;
  cell?: (props: { row: T; column: TableColumn<T> }) => unknown;
  empty?: () => unknown;
}
