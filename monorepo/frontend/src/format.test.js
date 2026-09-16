import { test } from 'node:test';
import assert from 'node:assert/strict';
import { label } from './format.js';

test('label renders kind, payload version and time', () => {
  assert.equal(label({ kind: 'click', at: 0 }), 'click · v2 · 00:00:00');
});
