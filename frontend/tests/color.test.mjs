import test from 'node:test';
import assert from 'node:assert/strict';
import useColor from '../src/use/color.ts';

const { isColor, colorStyle } = useColor();

test('only null or six-digit hex colours are accepted', () => {
  assert.deepEqual([null, '#123aBC', '#123', '', 'red', '#ffffff; color:red'].map(isColor), [
    true,
    true,
    false,
    false,
    false,
    false,
  ]);
});

test('unset colour leaves the entity default intact', () => {
  assert.deepEqual(colorStyle(null), {});
});

test('badge text contrasts with bright and dark selected backgrounds', () => {
  assert.deepEqual(['#ffffff', '#000000', '#ffff00', '#4263eb'].map(colorStyle), [
    { backgroundColor: '#ffffff', color: '#000000' },
    { backgroundColor: '#000000', color: '#ffffff' },
    { backgroundColor: '#ffff00', color: '#000000' },
    { backgroundColor: '#4263eb', color: '#ffffff' },
  ]);
});
