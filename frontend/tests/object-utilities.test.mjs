import test from 'node:test';
import assert from 'node:assert/strict';
import { objectUtilityRows } from '../src/use/objectUtilities.ts';

const unity = { id: 1, name: 'Water' };
const provider = { id: 10, unityProvider: { id: 3, name: 'Supplier' }, unity };

test('utility table keeps providers but excludes meters without a supplier, tenant meters and removed devices', () => {
  const rows = objectUtilityRows(
    [provider],
    [
      { id: 1, utility: unity, installation: { type: 'space', provider: null } },
      { id: 2, utility: unity, installation: { type: 'object', provider: null } },
      { id: 3, utility: unity, installation: null },
    ],
  );

  assert.deepEqual(
    rows.map((row) => [row.id, row.meters.map((meter) => meter.id)]),
    [['provider-10', []]],
  );
});

test('meter badges distinguish submitted, required and not yet due readings', () => {
  const meters = [
    { id: 1, utility: unity, installation: { type: 'object', provider }, readingSubmitted: true },
    { id: 2, utility: unity, installation: { type: 'object', provider }, readingAttention: true },
    { id: 3, utility: unity, installation: { type: 'object', provider } },
    {
      id: 4,
      utility: unity,
      installation: { type: 'object', provider },
      readingSubmitted: true,
      verificationExpired: true,
    },
  ];

  assert.deepEqual(
    objectUtilityRows([provider], meters)[0].meters.map((meter) => meter.badgeColor),
    ['green', 'orange', 'secondary', 'orange'],
  );
});

test('utility table shows every supplier contract as its own row, including finished contracts', () => {
  const contracts = [
    {
      id: 5,
      meterId: 1,
      unityProvider: provider.unityProvider,
      unities: [unity],
      contractNum: 'A-5',
      expiryDate: '2026-12-31',
      status: 'active',
    },
    {
      id: 4,
      unityProvider: provider.unityProvider,
      unities: [unity],
      contractNum: 'A-4',
      expiryDate: '2025-12-31',
      status: 'finished',
    },
  ];
  const rows = objectUtilityRows(
    [provider],
    [{ id: 1, utility: unity, installation: { type: 'object', provider } }],
    contracts,
  );

  assert.deepEqual(
    rows.map((row) => [
      row.contract?.id,
      row.contractNumLabel,
      row.expiryLabel,
      row.statusLabel,
      row.meters.map((meter) => meter.id),
    ]),
    [
      [5, 'A-5', '31.12.2026', 'Действует', [1]],
      [4, 'A-4', '31.12.2025', 'Завершён', []],
    ],
  );
});

test('contracts cover each communication and keep suppliers without contracts, excluding meters without suppliers', () => {
  const heat = { id: 2, name: 'Heat' };
  const contracts = [
    {
      id: 8,
      unityProvider: provider.unityProvider,
      unities: [unity, heat],
      contractNum: null,
      expiryDate: '2026-12-31',
      status: 'suspended',
    },
  ];
  const otherProvider = { ...provider, id: 11, unityProvider: { id: 7, name: 'Other supplier' } };
  const rows = objectUtilityRows(
    [provider, otherProvider],
    [{ id: 3, utility: heat, installation: { type: 'object', provider: null } }],
    contracts,
  );

  assert.deepEqual(
    rows.map((row) => [
      row.unity?.id,
      row.unityProvider?.id ?? null,
      row.contract?.id ?? null,
      row.meters.length,
    ]),
    [
      [1, 3, 8, 0],
      [1, 7, null, 0],
      [2, 3, 8, 0],
    ],
  );
});

test('a contract without communications or a provider association remains visible', () => {
  const contracts = [
    {
      id: 12,
      unityProvider: provider.unityProvider,
      unities: [],
      contractNum: 'N-12',
      expiryDate: '2026-12-31',
      status: 'active',
    },
  ];

  assert.deepEqual(
    objectUtilityRows([], [], contracts).map((row) => [
      row.unity,
      row.unityProvider.id,
      row.contract.id,
    ]),
    [[null, 3, 12]],
  );
});
