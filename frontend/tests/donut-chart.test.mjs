import test from 'node:test';
import assert from 'node:assert/strict';
import { donutChartOptions } from '../src/use/donutChart.ts';

const areaSeries = (occupied, available) => [
  {
    label: 'Занято',
    value: Number(occupied),
    valueLabel: occupied.replace('.', ',') + ' м²',
    color: 'green',
  },
  {
    label: 'Свободно',
    value: Number(available),
    valueLabel: available.replace('.', ',') + ' м²',
    color: 'orange',
  },
];

test('occupancy donut uses actual areas and preserves their units and decimal labels', () => {
  const options = donutChartOptions({
    series: areaSeries('155.4', '144.6'),
    total: '300 м²',
    totalLabel: 'Всего',
  });

  assert.deepEqual(
    [
      options.chart.type,
      options.series,
      options.labels,
      options.colors,
      options.plotOptions.pie.donut.labels.total.formatter(),
      options.legend.formatter('Занято', { seriesIndex: 0 }),
      options.tooltip.y.formatter(144.6, { seriesIndex: 1 }),
    ],
    [
      'donut',
      [155.4, 144.6],
      ['Занято', 'Свободно'],
      ['var(--tblr-green)', 'var(--tblr-orange)'],
      '300 м²',
      'Занято · 155,4 м²',
      '144,6 м²',
    ],
  );
});

test('empty and fully occupied objects retain zero categories without invented areas', () => {
  assert.deepEqual(
    [
      ['0', '300'],
      ['300', '0'],
      ['0', '0'],
    ].map(
      ([occupied, available]) =>
        donutChartOptions({
          series: areaSeries(occupied, available),
          total: '300 м²',
          totalLabel: 'Всего',
        }).series,
    ),
    [
      [0, 300],
      [300, 0],
      [0, 0],
    ],
  );
});

test('legend and tooltips preserve exact server precision while the chart uses numbers', () => {
  const options = donutChartOptions({
    series: areaSeries('0.1234567890123456789', '1'),
    total: '1,1234567890123456789 м²',
    totalLabel: 'Всего',
  });

  assert.deepEqual(
    [
      options.legend.formatter('Занято', { seriesIndex: 0 }),
      options.tooltip.y.formatter(0, { seriesIndex: 0 }),
      options.plotOptions.pie.donut.labels.total.formatter(),
    ],
    ['Занято · 0,1234567890123456789 м²', '0,1234567890123456789 м²', '1,1234567890123456789 м²'],
  );
});
