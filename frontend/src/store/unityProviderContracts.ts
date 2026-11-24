import type { UnityProviderContract } from '../types/models/UnityProviderContract';

export const unityProviderContractStatusLabels: Record<UnityProviderContract['status'], string> = {
  active: 'Действует',
  suspended: 'Приостановлен',
  finished: 'Завершён',
};

export const unityProviderContractStatusColors: Record<UnityProviderContract['status'], string> = {
  active: 'green',
  suspended: 'orange',
  finished: 'red',
};
