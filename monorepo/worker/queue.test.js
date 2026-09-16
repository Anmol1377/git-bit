import { test } from 'node:test';
import assert from 'node:assert/strict';
import { drain } from './queue.js';

test('drain keeps only events on the current payload version', () => {
  assert.deepEqual(
    drain([{ id: 'a', v: 2 }, { id: 'b', v: 1 }, null]),
    [{ id: 'a', v: 2 }]
  );
});
