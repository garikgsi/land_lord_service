import type { ContractAction } from '../types/components/ContractActions';
import type { ActionMenuItem } from '../types/components/ui/ActionMenu';
import type { ContractStatus } from '../types/models/Contract';

export const contractStatusLabels: Record<ContractStatus, string> = {
  active: 'Действует',
  upcoming: 'Аренда ещё не началась',
  finished: 'Завершён',
  cancelled: 'Отменён до начала',
};

export const contractStatusColors: Record<ContractStatus, string> = {
  active: 'green',
  upcoming: 'blue',
  finished: 'secondary',
  cancelled: 'red',
};

export const contractActionItems: ActionMenuItem<ContractAction>[] = [
  { value: 'amendment', label: 'Изменение' },
  { value: 'termination', label: 'Расторжение' },
  { value: 'indexation', label: 'Индексация' },
];
