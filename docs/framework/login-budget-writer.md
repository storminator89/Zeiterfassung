# Unbound operation-owned login-budget caller

This source slice adds a fixed operation, **not an active Web/API profile**.
There is no container binding, route, HTTP response, schema producer/DDL, account,
credential, LDAP, key source or cache reset. Default routes/throttles stay exact.
Its prerequisite is the exact reviewed corrected BudgetCore996e, not the older
valid-state-only definition. See [core contract](shared-login-budget-core.md).

## Fixed operation and confirmed boundary

`AttendanceWriter::consumeLoginBudget(username, remoteAddress): bool` returns
`true` for limited and `false` for allowed **only after** the existing single
IMMEDIATE lifecycle has confirmed COMMIT and the captured handle/level is idle.
This is not a reusable authorization capability or proof of user credentials.

The existing `executeLocked` body/failure rules are unchanged: idle check, one
BEGIN, active ownership check, fixed body, active check, one COMMIT, idle check,
then return. No transaction() retry, second manager, savepoint, raw BEGIN/COMMIT,
reconnect, foreign handle or arbitrary SQL/request callback is added. Rollback or
failed/uncertain commit/idle confirmation throws one unchained generic technical
exception; it does not return a decision, translate 429/401, authenticate, reset
quota or authorize a retry. A possibly committed failure remains uncertain.

The body requires the actual V4 writer/profile in the same lock, independently
of all intake allow/deny flags. Existing-account login must not depend on manual,
registration or LDAP-sync intake being enabled. The operation uses no principal,
role, guard, user query, password hash, directory, signing key or forged audit
actor. `login_attempts` has no canonical audit trigger; no audit scope is invented.

`RuntimeDatabase::writer` supplies its immutable admitted `schemaVersion` as a
new trailing optional internal constructor argument. Custom existing callers
keep default null and all old writer methods retain their behavior; the NEW
budget operation refuses an absent/nonpositive cookie. Never source that expected
cookie from request data or the PDO's current value at point of use. Inside the
lock, the operation compares `PRAGMA main.schema_version` strictly before and
after the exact core. Thus schema drift between open and BEGIN fails before DML.
The checked cookie is necessary, not full provenance: RuntimeDatabase's existing
private identity/settings/ledger/schema admission and sole-owner assumptions
remain mandatory. A cookie supplied to a custom constructor alone proves none
of them. No attempt is made to defeat a trusted owner forging schema_version.

One server `time()` sample is taken after locking; caller cannot select clock,
limits or bucket formulas. The core validates all stored starts/attempts before
cleanup/increment, preserves IP-first60 then lowercase-user15/600s policy, and
returns its provisional limited value only to the internal body. The writer's
confirmed result is the only value returned to the external facade. The global
O(countercount) scan and explicit corrupt-field refusal remain documented limits.

`Auth\WriterLoginBudget::limited(username, remoteAddress): bool` is a pure,
unbound facade with a trusted lazy factory returning a **new admitted operation
writer per call**. Construction does not resolve auth, a connection or the
factory. Each call invokes it once; no decision or writer is cached. This is
not a public SQL/PDO callback. The future composition must supply only the fixed
RuntimeDatabase writer factory, not a request-selected callback or shared writer.
The final typed writer check cannot prove a custom factory used correct physical
admission, fresh operation scope or no auth work; that composition needs review.

## Caller and schema gates deliberately not included

Future Web/API adapters must call the SAME facade/backend BEFORE guard/provider/
hash/LDAP/JWT work, and translate a **returned committed** limited result to native
plain-text429 / Retry-After600 only OUTSIDE the writer transaction. Technical
exceptions are not 429, bad credentials, local fallback or permission to retry.
Web/API field and username-normalization contracts remain separate; raw address
must come from REMOTE_ADDR with native local fallback, not forwarded headers.
Do not double-count with the current named Web cache throttle or leave a live
alternative domain. Current services/routes/providers are untouched and do not
call the new facade, so this is not yet a working shared persistent login budget.

The admitted main.login_attempts table must already physically exist with its
known canonical structure. Missing/corrupt state fails technically, without DDL
or another store. Existing V4 signatures historically normalize optional table
absence; a signature/cookie alone is NOT proof of table presence. Separately
reviewed B4 must create the known EMPTY table in NEW-only V4 source BEFORE
materialization/ledger/cookie/manifest admission and explicitly require presence.
No V2/V3 or existing-file upgrade, metadata rewrite, counter migration, key or
setup provision is performed. Until B4 and the complete selected caller/profile
union are reviewed, no production Web/API binding is permitted by this packet.

## Evidence limits

Syntax/static whole-method projection plus one coarse invented PDO/connection
model can exercise this exact operation/facade and preserved transaction shell.
Such a model bypasses constructor admission and models BEGIN/COMMIT/ROLLBACK;
it is NOT a real PHP SQLite handle, lock, commit, race or physical-file test.
No real database (even memory), credentials, keys, accounts, HTTP request, app
boot, server, CI, host/security change or runtime activation is part of the proof.
Later real admitted-handle/worker/HTTPS/client acceptance, native cutover, #197,
session29/30 and publication gates stay separate.
