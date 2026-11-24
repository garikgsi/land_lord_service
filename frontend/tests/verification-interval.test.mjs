import test from 'node:test';
import assert from 'node:assert/strict';
import {
  verificationIntervalPreset,
  verificationIntervalPresets,
} from '../src/use/verificationInterval.ts';

test('year presets store exact days using a 365-day year', () => {
  assert.deepEqual(
    verificationIntervalPresets.map((preset) => preset.days),
    ['365', '730', '1095', '1825'],
  );
});

test('saved days select their matching year preset', () => {
  assert.deepEqual(
    ['365', '730', '1095', '1825', '0730'].map((days) => verificationIntervalPreset(days)?.years),
    [1, 2, 3, 5, 2],
  );
});

test('arbitrary days and invalid input remain custom without rounding to years', () => {
  assert.deepEqual(
    ['', '0', '366', '1460', '7300', '-365', '365.0'].map((days) =>
      verificationIntervalPreset(days),
    ),
    Array(7).fill(undefined),
  );
});
