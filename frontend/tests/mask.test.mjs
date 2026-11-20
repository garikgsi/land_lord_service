import test from 'node:test';
import assert from 'node:assert/strict';
import useMask from '../src/use/mask.ts';

test('an empty optional phone remains empty', () => {
  const phone = useMask().phoneModel('');

  assert.deepEqual([phone.value, phone.unmaskedValue], ['', '']);
});
test('canonical phone is displayed in the requested Russian mask', () => {
  const phone = useMask().phoneModel('+79991234567');

  assert.deepEqual(
    [phone.value, phone.unmaskedValue, phone.isComplete],
    ['+7 (999) 123-45-67', '79991234567', true],
  );
});
test('a formatted value preserves the same phone', () => {
  assert.equal(useMask().phoneModel('+7 (999) 123-45-67').unmaskedValue, '79991234567');
});
test('a pasted national number beginning with 8 uses country code 7', () => {
  assert.equal(useMask().phoneModel('8 (999) 123-45-67').value, '+7 (999) 123-45-67');
});
test('ten subscriber digits receive the country code', () => {
  assert.equal(useMask().phoneModel('9991234567').value, '+7 (999) 123-45-67');
});
test('a partially entered phone remains incomplete', () => {
  assert.equal(useMask().phoneModel('+7999').isComplete, false);
});
