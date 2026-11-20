import test from 'node:test';
import assert from 'node:assert/strict';
import useEvent from '../src/use/event.ts';

test('different scripts share one event bus and can unsubscribe their own handler', () => {
  const events = [];
  const handler = () => events.push('expired');
  const firstScript = useEvent();
  const secondScript = useEvent();

  firstScript.on('session-expired', handler);

  try {
    secondScript.emit('session-expired');
  } finally {
    firstScript.off('session-expired', handler);
  }

  secondScript.emit('session-expired');
  assert.deepEqual(events, ['expired']);
});
