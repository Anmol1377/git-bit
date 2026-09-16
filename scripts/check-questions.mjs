/* Guards the question bank — runs in CI so a broken edit cannot reach a session.
   node scripts/check-questions.mjs                                           */
import { readFileSync } from 'node:fs';
import assert from 'node:assert/strict';

const QS = JSON.parse(readFileSync('api/questions.json', 'utf8'));

assert.equal(QS.length, 15, 'expected 15 questions');
assert.equal(new Set(QS.map(q => q.id)).size, 15, 'question ids must be unique');
for (const q of QS) {
  assert.equal(q.a.length, 4, `Q${q.id} needs exactly 4 options`);
  assert.ok(Number.isInteger(q.c) && q.a[q.c], `Q${q.id} correct index must point at a real option`);
  assert.ok(['easy', 'medium', 'hard'].includes(q.d), `Q${q.id} bad difficulty: ${q.d}`);
  assert.ok(q.why?.length > 10, `Q${q.id} needs an explanation`);
}
assert.deepEqual(
  QS.map(q => q.d).filter((d, i, a) => d !== a[i - 1]),
  ['easy', 'medium', 'hard'],
  'difficulty must ramp easy -> medium -> hard and never go back');

console.log(`✅ ${QS.length} questions valid`);
