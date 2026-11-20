import test from 'node:test';
import assert from 'node:assert/strict';
import useValidation from '../src/use/validation.ts';

const validation = useValidation();

test('empty and whitespace-only first name are invalid', () => {
  assert.deepEqual(
    ['', '   '].map((value) => !!validation.firstName(value)),
    [true, true],
  );
});
test('malformed email and a domain without TLD are invalid', () => {
  assert.deepEqual(
    ['wrong', 'a@', 'a@localhost', 'a b@example.com'].map((value) => !!validation.email(value)),
    [true, true, true, true],
  );
});
test('email plus addressing and subdomains remain valid', () => {
  assert.equal(validation.email('name+tag@sub.example.com'), '');
});
test('phone is optional and accepts canonical and existing display formats', () => {
  assert.deepEqual(
    [null, '', '+79991234567', '+7 (999) 123-45-67', '+7 999 123-45-67'].map((value) =>
      validation.phone(value),
    ),
    ['', '', '', '', ''],
  );
});
test('incomplete and unsupported phones are invalid', () => {
  assert.deepEqual(
    ['+7999', '+12345678901', 'text'].map((value) => !!validation.phone(value)),
    [true, true, true],
  );
});
test('profile validation returns errors under the same field names as the API', () => {
  assert.deepEqual(
    Object.keys(validation.userProfile({ firstName: ' ', email: 'wrong', phone: '+7999' })),
    ['firstName', 'email', 'phone'],
  );
});
