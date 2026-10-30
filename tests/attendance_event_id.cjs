const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { webcrypto } = require('node:crypto');
const source = fs.readFileSync('framework/public/assets/js/attendance.js', 'utf8')
  .replace('    const ready =', '    globalThis.testEventId = newEventId;\n    const ready =');
function load(crypto) {
  const scope = { crypto, Uint8Array, document: {readyState: 'loading', addEventListener() {}} };
  vm.runInNewContext(source, scope);
  return scope.testEventId;
}
const fallback = load({getRandomValues: webcrypto.getRandomValues.bind(webcrypto)});
const ids = new Set(Array.from({length:1000}, () => fallback()));
assert.equal(ids.size, 1000);
for (const id of ids) assert.match(id, /^[a-f0-9]{32}$/);
let uuidCalls = 0;
assert.equal(load({randomUUID() {uuidCalls++;return '550e8400-e29b-41d4-a716-446655440000';}})(), '550e8400-e29b-41d4-a716-446655440000');
assert.equal(uuidCalls, 1);
assert.throws(load(undefined), /Buchung konnte nicht vorbereitet werden/);
assert.throws(load({}), /Buchung konnte nicht vorbereitet werden/);
console.log('PASS HTTP secure-random fallback, UUID path, unique IDs and missing-entropy refusal');
