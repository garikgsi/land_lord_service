import test from 'node:test';
import assert from 'node:assert/strict';
import { meterConsumptionSummary } from '../src/use/meterConsumption.ts';

function reading(id, date, consumption, meterRead = consumption) {
  return {
    id,
    meterReadingDate: date,
    meterRead,
    consumption,
    intervalFrom: '2026-09-01T00:00:00',
  };
}

test('consumption compares the latest intervals by reading date, never cumulative readings', () => {
  const result = meterConsumptionSummary(
    [
      reading(4, '2026-10-25T00:00:00', 100, 10000),
      reading(1, '2026-11-25T00:00:00', 150, 10150),
      reading(9, '2026-09-25T00:00:00', 300, 9900),
    ],
    'кВт·ч',
  );

  assert.deepEqual(
    [
      result.periods.map(({ id, value }) => [id, value]),
      result.change,
      result.changeLabel,
      result.color,
      result.periods[0].detail,
    ],
    [
      [
        [1, 150],
        [4, 100],
      ],
      50,
      '+50%',
      'red',
      'ноябрь 2026 — 150 кВт·ч',
    ],
  );
});

test('decreased, equal and zero consumption have finite and truthful comparisons', () => {
  assert.deepEqual(
    [
      [50, 100],
      [100, 100],
      [0, 100],
      [100, 0],
      [0, 0],
    ].map(([current, previous]) => {
      const result = meterConsumptionSummary(
        [reading(2, '2026-11-25', current), reading(1, '2026-10-25', previous)],
        null,
      );

      return [result.change, result.changeLabel, result.color];
    }),
    [
      [-50, '-50%', 'green'],
      [0, '0%', 'secondary'],
      [-100, '-100%', 'green'],
      [null, '—', 'red'],
      [0, '0%', 'secondary'],
    ],
  );
});

test('missing measurements do not invent a second consumption or a percentage', () => {
  assert.deepEqual(
    [[], [reading(1, '2026-11-25', 123, 500)]].map((items) => {
      const result = meterConsumptionSummary(items, null);

      return [result.periods.length, result.change, result.changeLabel];
    }),
    [
      [0, null, '—'],
      [1, null, '—'],
    ],
  );
});

test('ApexCharts receives comparable arcs but displays actual consumption in its legend and centre', async () => {
  const { radialComparisonOptions } = await import('../src/use/radialComparison.ts');
  const summary = meterConsumptionSummary(
    [reading(2, '2026-11-25', 2378), reading(1, '2026-10-25', 2000)],
    'кВт·ч',
  );
  const options = radialComparisonOptions({
    series: summary.periods.map((period, index) => ({
      ...period,
      color: index === 0 ? 'azure' : 'indigo',
    })),
    label: 'Потребление',
    value: '2 378',
    unit: 'кВт·ч',
    valueColor: summary.color,
  });

  assert.deepEqual(
    [
      options.chart.type,
      options.series,
      options.labels,
      options.plotOptions.radialBar.dataLabels.total.formatter(),
      options.legend.formatter('октябрь 2026', { seriesIndex: 1 }),
      options.plotOptions.radialBar.dataLabels.value.color,
      options.tooltip.enabled,
      options.legend.show,
    ],
    [
      'radialBar',
      [100, (2000 / 2378) * 100],
      ['ноябрь 2026', 'октябрь 2026'],
      '2 378',
      'октябрь 2026 · 2\u00a0000 кВт·ч',
      'var(--tblr-red)',
      true,
      true,
    ],
  );
});
