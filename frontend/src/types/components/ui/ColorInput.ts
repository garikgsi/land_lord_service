export interface ColorInputProps {
  id: string;
  label: string;
  defaultColor?: string;
  disabled?: boolean;
  error?: string;
}

export interface ColorOption {
  name: string;
  label: string;
  value: string;
}
