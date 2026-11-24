import type { Meter } from '../types/models/Meter';
import type { ObjectUnityProvider } from '../types/models/Unity';
import type { UnityProviderContract } from '../types/models/UnityProviderContract';
import type { ObjectUtilityGroup, ObjectUtilityRow } from '../types/components/ObjectUtilities';
import {
  unityProviderContractStatusLabels,
  unityProviderContractStatusColors,
} from '../store/unityProviderContracts.ts';

export function objectUtilityRows(
  providers: ObjectUnityProvider[],
  meters: Meter[],
  contracts: readonly UnityProviderContract[] = [],
): ObjectUtilityRow[] {
  const rows: ObjectUtilityGroup[] = providers.map((association) => ({
    id: 'provider-' + association.id,
    association,
    unity: association.unity,
    unityProvider: association.unityProvider,
    meters: [],
  }));

  for (const meter of meters) {
    if (meter.installation?.type !== 'object') continue;

    const providerId = meter.installation.provider?.id;
    const row = rows.find((item) => item.association?.id === providerId);

    if (!row) continue;

    row.meters.push({
      ...meter,
      badgeColor:
        meter.verificationExpired || meter.readingAttention
          ? 'orange'
          : meter.readingSubmitted
            ? 'green'
            : 'secondary',
    });
  }

  for (const contract of contracts) {
    for (const unity of contract.unities.length ? contract.unities : [null]) {
      if (
        rows.some(
          (row) =>
            row.unityProvider?.id === contract.unityProvider.id &&
            (!unity || row.unity?.id === unity.id),
        )
      )
        continue;

      rows.push({
        id: 'contract-provider-' + contract.unityProvider.id + '-unity-' + (unity?.id ?? 'none'),
        unity,
        unityProvider: contract.unityProvider,
        meters: [],
      });
    }
  }

  return rows.flatMap((row) => {
    const matches = contracts.filter(
      (contract) =>
        row.unityProvider?.id === contract.unityProvider.id &&
        (!contract.unities.length || contract.unities.some((unity) => unity.id === row.unity?.id)),
    );

    const result = (matches.length ? matches : [null]).map((contract) => ({
      ...row,
      id: contract ? row.id + '-contract-' + contract.id : row.id,
      contract,
      meters: contract ? row.meters.filter((meter) => meter.id === contract.meterId) : row.meters,
      contractNumLabel: contract?.contractNum ?? '—',
      expiryLabel: contract ? contract.expiryDate.split('-').reverse().join('.') : '—',
      statusLabel: contract ? unityProviderContractStatusLabels[contract.status] : '—',
      statusColor: contract ? unityProviderContractStatusColors[contract.status] : 'secondary',
    }));
    const unassigned = row.meters.filter(
      (meter) => !matches.some((contract) => contract.meterId === meter.id),
    );

    if (matches.length && unassigned.length)
      result.push({
        ...row,
        id: row.id + '-unassigned',
        meters: unassigned,
        contract: null,
        contractNumLabel: '—',
        expiryLabel: '—',
        statusLabel: '—',
        statusColor: 'secondary',
      });

    return result;
  });
}
