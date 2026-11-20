import test from 'node:test';
import assert from 'node:assert/strict';
import { userFormPayload } from '../src/userForm.ts';

const original = {
  email: 'test@example.com',
  firstName: 'Анна',
  lastName: null,
  phone: null,
  position: null,
  roles: ['ROLE_DIRECTOR', 'ROLE_ACCOUNTANT'],
  blocked: false,
};
const signature = (form) => JSON.stringify(userFormPayload(form));

test('unchanged values including empty optional inputs do not enable saving', () => {
  const form = { ...original, lastName: '', phone: '', position: '' };

  assert.equal(signature(form), signature(original));
});

test('editing and reverting a field returns the form to its original state', () => {
  const form = { ...original };
  const initial = signature(form);

  form.firstName = 'Мария';
  const changed = signature(form) !== initial;

  form.firstName = original.firstName;
  assert.deepEqual([changed, signature(form) === initial], [true, true]);
});

test('role ordering and duplicate checkbox values do not count as changes', () => {
  assert.equal(
    signature({ ...original, roles: ['ROLE_ACCOUNTANT', 'ROLE_DIRECTOR', 'ROLE_ACCOUNTANT'] }),
    signature(original),
  );
});

test('the form compares the same trimmed values that are sent to the API', () => {
  assert.equal(
    signature({
      ...original,
      email: ' TEST@EXAMPLE.COM ',
      firstName: ' Анна ',
      lastName: ' ',
      phone: ' ',
      position: ' ',
    }),
    signature(original),
  );
});

test('a changed role or login block is included in the profile payload', () => {
  assert.deepEqual(
    [
      signature({ ...original, roles: ['ROLE_TECHNICIAN'] }) !== signature(original),
      signature({ ...original, blocked: true }) !== signature(original),
    ],
    [true, true],
  );
});

test('super administrator includes administrator in the effective form values', () => {
  assert.deepEqual(userFormPayload({ ...original, roles: ['ROLE_SUPER_ADMIN'] }).roles, [
    'ROLE_ADMIN',
    'ROLE_SUPER_ADMIN',
  ]);
});
