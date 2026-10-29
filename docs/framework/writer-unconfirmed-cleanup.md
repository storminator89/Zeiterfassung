# Writer cleanup uncertainty must not become a known domain rejection

Source anchor: actual Main `daf608a542946c44eb7dd62ee0cd62caea1c9932`, tree
`37181b17ed3ec58a7666f51b2e1611adfeb422ac` after PR290. This is a narrow
failure-classification correction, not Runtime activation or a recovery tool.

## Concrete existing gap

`AttendanceWriter::executeLocked` already owns the sole one-attempt transaction,
tries rollback after any failure and poisons its adapter if cleanup or idle-state
confirmation fails. It previously rethrew the original error even in that last
case. A primary `AttendanceError`400/409 or `InvalidArgumentException` could then
look like ordinary known validation/replay feedback although a live transaction
and earlier domain DML still existed on the captured PDO. Some existing callers
retain form input or choose a domain status only for those known exceptions.
Adapter poisoning alone does not make that result confirmed.

Only the existing secondary cleanup catch changes: keep the poison and throw a
fixed `RuntimeException` with no previous exception, primary message or native
SQL/path diagnostics. Do not automatically retry the body, reconnect, commit,
repair or return a success. If rollback and the original idle checks succeed,
the **same original error object** still propagates. Successful transactions,
stored event receipts, profile/authority checks, transaction count and audit
context/global restoration remain unchanged. The current V5 Runtime hold and
all source/opening/Setup/Auth/Native gates are byte-identical.

The adapter stays unusable even if some external caller later cleans its PDO.
Neither poisoning nor the generic failure proves rollback, confirmed absence of
COMMIT, restoration or human recoverability. Discard the operation scope; do not
infer a safe new command or reset an operating source from this exception.

## Existing caller contracts, not a new universal response policy

The caller inventory covers36 fixed writer call sites. The operational exception
bypasses typed domain/validation translations and uses their existing generic
failure handling. No controller, facade, response envelope or status is changed.
Attendance/API/terminal and setup/lifecycle paths commonly return503; API login
budget remains500 and some HTML failure views remain200. Not every existing
message literally says "unconfirmed". No universal503 or log-redaction guarantee
is asserted; the new exception's own message and chain are fixed/redacted.

Stable event-ID receipt/hash replay is untouched. Existing API/terminal text may
instruct retrying the same operation; this change introduces no automatic retry
and does not make every endpoint retry-safe. API aliases that generate a fresh
event ID when omitted still have their documented retry limitation. Terminal
nonce/limit/identify autocommit work and post-COMMIT pairing response operations
partly outside `executeLocked` are not made atomic by this correction.

## Bounded executable evidence

The published original class and fixed class each execute40 disposable file-only
component cases on PHP8.5.12/Laravel13.38.0 across explicit V2/V3/V4/V5 profiles.
Only row-shaped unusable password placeholders are used, no credential/hash/key/
token generation, login, account provisioner or operating data. These fixtures
are not admitted installations or recovery accounts.

- A throwing fixture UDF before the event-receipt INSERT raises the exact primary
  AttendanceError400/409 or InvalidArgumentException **after earlier real work-
  session/projection DML**. Native PDO SQLite authorizer denial makes the actual
  ROLLBACK fail, leaving that transaction/DML live on its captured handle. A
  separate observer still sees only the old committed state. The original class
  returns the domain error; fixed source yields only the constant RuntimeException
  without a previous error. No repeated body or second cleanup attempt occurs.
- That actual caught error is passed once through the existing API controller's
  real error-classification branch, not a bearer login or HTTP middleware stack.
  Original uncertain domain400/409 becomes a misleading domain response; fixed
  uncertainty is generic503 with no primary sentinel and unchanged stable ID.
- Confirmed rollback preserves object identity, source rows and reusable adapter;
  genuine COMMIT denial with successful cleanup preserves its original PDO error.
  COMMIT plus ROLLBACK denial remains unconfirmed/poisoned. BEGIN refusal and
  normal success/replay controls also pass. Fresh successful stable-ID replay
  returns its same stored receipt without duplicate mutation after confirmed
  cleanup; no general retry/recovery recommendation follows.
- Audit scope/native globals restore on every case. Poisoned adapters refuse
  another transaction both before and after explicit **test-owned** cleanup.
  Removing the injected denial and rolling back a fixture is not production
  recovery or an assertion that a real lost outcome can be rolled back.
- Actual standard-provider V5 boot still stops at its unchanged Runtime hold,
  before effective routes/writer/PDO resolution. Syntax, strict Composer validation,
  full source-preservation/inverse and independent source review are separate.

Not exercised: replacement-handle/idle corruption injection, actual disk loss,
lost COMMIT response after successful physical commit, process crash/concurrency,
real HTTP/session/CSRF/browser/keys, positive V5 Runtime admission, restore or human
password knowledge. These remain operating acceptance gates; migration/#30 stays
open. No new CI workflow, runtime option, recovery bypass or remote activation.
