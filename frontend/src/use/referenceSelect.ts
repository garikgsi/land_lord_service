import type { ReferenceCreationKind } from '../types/components/ui/ReferenceSelect';

export function referenceCreationKind(endpoint: string): ReferenceCreationKind | null {
  const path = endpoint.split('?')[0];

  if (/^\/api\/objects\/\d+\/unity-providers$/.test(path)) return 'object-provider';

  const kind = path?.replace('/api/', '');

  return [
    'land-lords',
    'renters',
    'unities',
    'unity-providers',
    'ownerships',
    'contract-types',
  ].includes(kind ?? '')
    ? (kind as ReferenceCreationKind)
    : null;
}
