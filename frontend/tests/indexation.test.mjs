import test from 'node:test';
import assert from 'node:assert/strict';
import { indexedAmount, indexationPercent } from '../src/use/indexation.ts';

test('percentage uses exact decimals and rounds HALF_UP only once', () => {
  assert.deepEqual(
    [
      indexedAmount('100.005', '0'),
      indexedAmount('100.005', '10'),
      indexedAmount('9007199254740993', '10'),
      indexedAmount('100', '-100'),
    ],
    ['100.01', '110.01', '9907919180215092.30', '0.00'],
  );
});

test('reverse calculation keeps entered amount authoritative and rounds percent to six places', () => {
  assert.deepEqual(
    [
      indexationPercent('300', '350'),
      indexationPercent('100', '0'),
      indexationPercent('100', '200'),
      indexationPercent('0', '0'),
      indexedAmount('100', '5,5'),
    ],
    ['16.666667', '-100', '100', '0', '105.50'],
  );
});

test('negative target, percentage below minus 100 and zero base cannot silently produce a value', () => {
  for (const calculate of [
    () => indexedAmount('100', '-100.01'),
    () => indexationPercent('100', '-1'),
    () => indexationPercent('0', '100'),
    () => indexedAmount('100', ''),
  ])
    assert.throws(calculate);
});
