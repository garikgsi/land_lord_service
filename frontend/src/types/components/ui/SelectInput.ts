export interface SelectOption {
  value: string;
  label: string;
}

export interface SelectInputProps {
  id: string;
  label: string;
  options: readonly SelectOption[];
  disabled?: boolean;
}
