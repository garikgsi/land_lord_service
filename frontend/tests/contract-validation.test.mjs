import test from 'node:test';
import assert from 'node:assert/strict';
import useContractValidation from '../src/use/contractValidation.ts';

const { contract, nextDay } = useContractValidation();
const valid = {
  objectId: 1,
  renterId: 1,
  contractTypeId: 1,
  contractNum: 'N1',
  contractDate: '2026-11-21',
  expiryDate: '2026-12-31',
  monthlyPayment: '123.12345678901234567890123456789',
  securityPayment: '0',
  square: '40.0001',
  spaceName: '',
  increaseDate: '',
  increasePercent: '',
};

test('exact decimal strings remain valid without converting to float', () => {
  assert.deepEqual(contract(valid, true), {});
});

test('required fields, dates, paired indexation and new space area are validated', () => {
  assert.deepEqual(
    Object.keys(
      contract(
        {
          ...valid,
          contractNum: ' ',
          contractDate: '2026-02-30',
          square: '0',
          monthlyPayment: '-1',
          increaseDate: '2026-12-01',
        },
        true,
      ),
    ).sort(),
    ['contractNum', 'contractDate', 'square', 'monthlyPayment', 'increasePercent'].sort(),
  );
});

test('existing Space does not require a newly entered area', () => {
  assert.deepEqual(contract({ ...valid, square: '' }, false), {});
});

test('same-day end is allowed and next contract starts the following day across leap day', () => {
  assert.deepEqual(
    [
      contract({ ...valid, expiryDate: valid.contractDate }, true),
      nextDay('2028-02-28'),
      nextDay('2028-02-29'),
      nextDay('2026-12-31'),
    ],
    [{}, '2028-02-29', '2028-03-01', '2027-01-01'],
  );
});

test('draft area is required and exact capacity allows zero remainder but rejects any excess', () => {
  assert.deepEqual(
    [
      contract({ ...valid, square: '' }, true),
      contract({ ...valid, square: '60.0000000000000000001' }, true, '60'),
      contract({ ...valid, square: '60' }, true, '60'),
      contract({ ...valid, square: '0.1' }, true, '-0.0000000000000000001'),
    ].map((errors) => Object.keys(errors)),
    [['square'], ['square'], [], ['square']],
  );
});
