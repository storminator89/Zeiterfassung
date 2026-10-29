# Once COMMIT is attempted, a thrown error is not a known rejection

Source anchor: actual Main `7f5cc11531137488c080498c42899693a9875597`, tree
`9436b00bee53ff99ccbca522a0d0028d0ab84aa6` after PR293. Runtime stays held.

## Why confirmed cleanup is not enough

PR291 correctly masks failed rollback/idle confirmation. But even an idle handle
and a successful cleanup call cannot prove the operation never committed: PDO
may have physically committed before a failure is reported. Laravel's commit
implementation also has transaction-manager/event work after the PDO commit.
Today's Writer owns a private bare stock connection without such callbacks or
manager registration; no currently configured production callback is claimed.
That composition fact is not an explicit transaction-outcome boundary.

A post-physical-commit `AttendanceError`400/409 could previously emerge unchanged:
rollback would have nothing left to undo, idle confirmation could pass, and a
caller could misclassify the already committed operation as known validation.
This also prevents safely enabling further display-draft flows by status alone.

## One explicit phase, no new transaction manager

The existing one-attempt `executeLocked` shell now marks COMMIT-attempted directly
before calling its same connection's `commit()`. Any error caught after that
point poisons the adapter and throws the same fixed RuntimeException used for
cleanup uncertainty, with no previous exception or primary/native message.
The flag remains true through post-commit checks/audit-scope continuation.

Even a denied COMMIT followed by successful rollback uses this conservative
operational result. Do not retry automatically, infer success or render a known
rejection from its original exception. The captured adapter cannot be reused.
No reconnect, replacement PDO, public callback, caller-supplied phase or route
option is introduced. Cleanup attempts still follow the existing sole lifecycle.

Only errors **before any COMMIT attempt**, with successful existing rollback and
idle confirmation, retain the exact original object. PR291's unknown-cleanup
mask remains exact. All successful transactions and stored receipt/hash replay,
profile/generation/authority checks, audit restoration, operations and Runtime/
Setup/Auth/Native gates are unchanged. No draft/UI change is bundled here.

## Actual bounded fault evidence

Across explicit V2/V3/V4/V5 disposable file-only components, a test-only native
`Pdo\Sqlite` subclass calls its real `parent::commit()` successfully and throws
AttendanceError400/409 or InvalidArgumentException **afterwards**. A separate
ordinary PDO observes the committed booking and event receipt. The original
class exposes the same domain/validation error and permits adapter reuse; fixed
source returns only generic uncertainty and poisons the adapter. Passing that
actual error once through the existing API controller classification changes
domain400/409 to existing503. This is12 original/12 fixed cases, not actual
Laravel callback installation, network-loss or admitted HTTP acceptance.

A separate test operation with the same supplied stable ID reads the exact
stored receipt without duplicate booking/event DML. This exercises the existing
receipt authority; it is not automatic retry, authorization to retry a new ID,
or a claim that omitted-ID API aliases became safe. Real authenticated V5 replay
must still pass its unchanged current generation/source/role/hash checks.

The earlier40-case SQLite-authorizer/UDF journal is repeated on both versions:
pre-COMMIT known errors after real earlier DML keep their exact object only after
confirmed rollback; failed rollback stays masked; BEGIN refusal, success/replay,
audit restoration and no-body-retry controls pass. COMMIT denial now deliberately
uses generic uncertainty/poison even if the cleanup itself succeeds. No valid
credentials, password-hash/key/token generation, login, provisioner or operating
data is used; V5 ordinary fixture authority has an unusable password placeholder.

Syntax, Composer, whole-tree/inverse and independent source review are separate.
No real disk loss, process crash/concurrent workers, lost successful network
response, restore/human recovery, real HTTP/session/CSRF/browser or positive V5
Runtime admission is accepted. Existing per-channel response wording/statuses
and terminal work outside this shell remain unchanged. #50 review-draft and #61
save-draft need their own subsequent composition/evidence; neither is activated
by this packet. Migration/#30 and all operating acceptance gates remain open.
