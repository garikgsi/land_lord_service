export interface ActionMenuItem<T extends string> {
  value: T;
  label: string;
}

export interface ActionMenuProps<T extends string> {
  label: string;
  items: readonly ActionMenuItem<T>[];
  disabled?: boolean;
  floating?: boolean;
}
