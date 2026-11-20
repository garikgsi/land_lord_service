import test from 'node:test';
import assert from 'node:assert/strict';
import useRentalObjectValidation from '../src/use/rentalObjectValidation.ts';

const validation = useRentalObjectValidation();

test('rental object area accepts exact decimal text and a decimal comma', () => {
  assert.deepEqual(['100.12345678901234567890123456789', '0,01'].map(validation.square), ['', '']);
});

test('rental object area rejects zero, negative, exponent and malformed input', () => {
  assert.deepEqual(
    ['0', '0.00', '-1', '1e3', 'NaN', '1.2.3', ''].map((value) => !!validation.square(value)),
    [true, true, true, true, true, true, true],
  );
});

test('required object fields and reading day are validated under API field names', () => {
  assert.deepEqual(
    Object.keys(
      validation.rentalObject({
        name: ' ',
        address: '',
        square: '0',
        landLordId: null,
        readingDay: '32',
      }),
    ),
    ['name', 'address', 'square', 'landLordId', 'readingDay'],
  );
});

test('valid rental object passes client validation', () => {
  assert.deepEqual(
    validation.rentalObject({
      name: 'Объект',
      address: 'Адрес',
      square: '100.25',
      landLordId: 1,
      readingDay: '25',
    }),
    {},
  );
});
