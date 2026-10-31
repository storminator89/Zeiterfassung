const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('framework/public/assets/js/attendance.js', 'utf8')
  .replace('    const ready =', '    globalThis.liveClock = {liveSessionSnapshot, liveSessionSeconds, formatLiveSession};\n    const ready =');
const scope = {document: {readyState: 'loading', addEventListener() {}}};
vm.runInNewContext(source, scope);
const {liveSessionSnapshot: project, liveSessionSeconds: seconds, formatLiveSession: format} = scope.liveClock;
const base = {version: 1, record_id: '9007199254740993', state: 'working', as_of_epoch: 5000,
  started_at_epoch: 1000, closed_pause_seconds: 61, pause_started_at_epoch: null, elapsed_work_seconds: 3939};
const working = project(base, 'working', base.record_id, 1500);
assert.equal(seconds(working, 1500), 3939);
assert.equal(seconds(working, 2499), 3939);
assert.equal(seconds(working, 2500), 3940);
assert.equal(format(seconds(working, 2500)), '01:05:40');
const pausedWire = {...base, state: 'paused', pause_started_at_epoch: 4900, elapsed_work_seconds: 3839};
const paused = project(pausedWire, 'paused', base.record_id, 1500);
assert.equal(seconds(paused, 1500), 3839);
assert.equal(seconds(paused, 61500), 3839, 'paused work time must remain frozen');
assert.equal(seconds(working, 91501), null, 'expired snapshot cannot keep claiming an active session');
assert.equal(seconds(working, 1499), null, 'monotonic clock reversal must fail closed');
assert.equal(project(base, 'absent', null, 0), null);
assert.equal(project(base, 'working', '7', 0), null, 'never attach another record timing');
for (const change of [
  {as_of_epoch: 999}, {closed_pause_seconds: 4001}, {elapsed_work_seconds: 3940},
  {pause_started_at_epoch: 4900}, {as_of_epoch: Infinity}, {elapsed_work_seconds: -1},
  {elapsed_work_seconds: Number.MAX_SAFE_INTEGER + 1}, {version: 2}
]) assert.equal(project({...base, ...change}, 'working', base.record_id, 0), null);
assert.equal(project({...pausedWire, pause_started_at_epoch: null}, 'paused', base.record_id, 0), null);
assert.equal(project({...pausedWire, pause_started_at_epoch: 5001}, 'paused', base.record_id, 0), null);
assert.equal(project(null, 'working', '7', 0), null);
assert.equal(format(90061), '25:01:01', 'durations must not wrap at midnight');
assert.equal(format(0), '00:00:00');
assert.equal(format(null), '—');
console.log('PASS server-bound net timer, exact pause seconds, pause freeze, stale/invalid guards and durations over 24 hours');
