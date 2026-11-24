export interface ReferenceOption {
  id: number;
  name: string;
}

export interface ReferenceInputProps {
  id: string;
  label: string;
  endpoint: string;
  required?: boolean;
  validateOnBlur?: boolean;
  placeholder?: string;
  initial?: ReferenceOption;
  extraOptions?: readonly ReferenceOption[];
  error?: string;
  disabled?: boolean;
}

export type ReferenceLoadCallback = (items?: ReferenceOption[]) => void;
