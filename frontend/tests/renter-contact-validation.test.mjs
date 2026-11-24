import test from 'node:test';
import assert from 'node:assert/strict';
import useRenterContactValidation from '../src/use/renterContactValidation.ts';

const { contact } = useRenterContactValidation();

test('only contact first name is required; optional channels can stay empty', () => {
  assert.deepEqual(
    contact({ name: 'Иван', lastName: '', patronymic: '', phone: '', email: '' }),
    {},
  );
});

test('contact name and filled phone/email are checked on client', () => {
  assert.deepEqual(
    Object.keys(
      contact({ name: ' ', lastName: '', patronymic: '', phone: '+7999', email: 'wrong' }),
    ).sort(),
    ['email', 'name', 'phone'],
  );
});

test('valid canonical phone and email pass contact validation', () => {
  assert.deepEqual(
    contact({
      name: 'Иван',
      lastName: 'Иванов',
      patronymic: '',
      phone: '+79991234567',
      email: 'ivan@example.com',
    }),
    {},
  );
});
