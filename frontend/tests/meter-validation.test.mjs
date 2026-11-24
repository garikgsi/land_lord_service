import test from 'node:test';
import assert from 'node:assert/strict';
import {
  integerError,
  dateError,
  locationErrors,
  meterErrors,
} from '../src/use/meterValidation.ts';

test('integer fields reject fractions, signs and overflow, allowing zero baseline', () => {
  assert.deepEqual(
    ['0', '123', '1.5', '-1', '2147483648', ''].map((value) => !!integerError(value)),
    [false, false, true, true, true, true],
  );
});

test('calendar date validation rejects impossible dates', () => {
  assert.deepEqual(
    ['2026-02-28', '2026-02-30', '', '2026-13-01'].map((value) => !!dateError(value)),
    [false, true, true, true],
  );
});

test('space installation requires both parent object selection and space', () => {
  assert.deepEqual(locationErrors({ type: 'space', objectId: 1, spaceId: null, comment: '' }), {
    objectId: '',
    spaceId: 'Выберите помещение.',
  });
});

test('manufacture date is required, optional verification and inherited day can be empty', () => {
  const draft = {
    utilityId: 1,
    createDate: '2020-01-01',
    startRead: '0',
    verificationIntervalDays: '365',
    serialNumber: '',
    model: '',
    comment: '',
    verificationDate: '',
    readingDay: '',
  };

  assert.equal(Object.values(meterErrors(draft)).some(Boolean), false);
});

test('a meter can be created without an installation', () => {
  assert.deepEqual(locationErrors({ type: 'object', objectId: null, spaceId: null, comment: '' }), {
    objectId: '',
    spaceId: '',
  });
});
