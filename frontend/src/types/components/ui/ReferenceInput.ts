export interface ReferenceOption {
  id: number;
  name: string;
}

export interface ReferenceInputProps {
  id: string;
  label: string;
  endpoint: string;
  initial?: ReferenceOption;
  error?: string;
  disabled?: boolean;
}

export type ReferenceLoadCallback = (items?: ReferenceOption[]) => void;
