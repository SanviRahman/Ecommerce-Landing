import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import path from 'node:path';
const sandbox = { module: { exports: {} } };
vm.runInNewContext(fs.readFileSync(path.resolve(import.meta.dirname, '../../public/frontend/js/home-builder-sort.js'), 'utf8'), sandbox);
const { moveKey, isExactOrder } = sandbox.module.exports;
const a = '11111111-1111-4111-8111-111111111111';
const b = '22222222-2222-4222-8222-222222222222';
const c = '33333333-3333-4333-8333-333333333333';
const keys = [a, b, c];
const checks = [
  ['Reordering first to last retains other keys', () => assert.deepEqual(moveKey(keys, a, 2), [b, c, a])],
  ['Reordering last to first retains other keys', () => assert.deepEqual(moveKey(keys, c, 0), [c, a, b])],
  ['Moving middle before first is stable', () => assert.deepEqual(moveKey(keys, b, 0), [b, a, c])],
  ['Moving middle after last is stable', () => assert.deepEqual(moveKey(keys, b, 2), [a, c, b])],
  ['Moving to same slot changes nothing', () => assert.deepEqual(moveKey(keys, b, 1), keys)],
  ['Unknown source changes nothing', () => assert.deepEqual(moveKey(keys, 'missing', 1), keys)],
  ['Negative destination changes nothing', () => assert.deepEqual(moveKey(keys, b, -1), keys)],
  ['Out-of-range destination changes nothing', () => assert.deepEqual(moveKey(keys, b, 3), keys)],
  ['Fractional destination changes nothing', () => assert.deepEqual(moveKey(keys, b, 1.5), keys)],
  ['Pure move never mutates original array', () => {moveKey(keys, b, 2); assert.deepEqual(keys, [a, b, c]);}],
  ['Exact permutation is accepted', () => assert.equal(isExactOrder(keys, [c,a,b]), true)],
  ['Unchanged exact order is accepted', () => assert.equal(isExactOrder(keys, keys), true)],
  ['Missing key is rejected', () => assert.equal(isExactOrder(keys, [a,b]), false)],
  ['Unknown key is rejected', () => assert.equal(isExactOrder(keys, [a,b,'other']), false)],
  ['Duplicate key is rejected', () => assert.equal(isExactOrder(keys, [a,a,b]), false)],
  ['Duplicate expected keys are rejected', () => assert.equal(isExactOrder([a,a,b], [a,a,b]), false)],
  ['Non-string keys are rejected', () => assert.equal(isExactOrder(keys, [a,b,7]), false)],
  ['Wrong input types are rejected', () => assert.equal(isExactOrder(keys, 'bad'), false)],
];
let passed = 0;
for (const [label, test] of checks) {
  try { test(); console.log('PASS ' + label); passed++; }
  catch (error) { console.error('FAIL ' + label + ': ' + error.message); process.exitCode = 1; }
}
console.log(`RESULT: ${passed}/${checks.length} P06 sort-core unit tests ${process.exitCode ? 'FAILED' : 'PASS'} (Node, no database).`);
