# Unbound SQLite login-budget core

This is a definition-only source slice. Nothing binds this store to Web/API
login, opens a file, creates a table or enables an API. Existing providers,
routes, defaults, writer lifecycle, native login and all accounts are unchanged.
It deliberately does **not** implement `LegacyApiLoginBudget::consume(): void`:
an HTTP exception in the transaction would undo the consumed attempt.

## Fixed contract

`SharedSqliteLoginBudgetStore::consumeLocked(PDO, username, remoteAddress, now)`
returns an immutable `LoginBudgetDecision` containing only `limited: bool`.
The result is **provisional until confirmed COMMIT**, not authentication,
authorization, a committed receipt, a reusable capability or an HTTP response.
No password, raw identifiers, bucket hashes or counters are retained in it.

The trusted caller must supply the same already admitted stock SQLite PDO,
with exception mode and integer decoding, inside its own single **IMMEDIATE**
transaction. No nesting, savepoint, reconnect, arbitrary SQL/request callback,
read replica or retry may replace that scope. `inTransaction()` and driver checks
are fail-closed guards only; they cannot establish lock mode, private file
identity, schema/ledger admission or ownership. No connection configuration or
PRAGMA is changed by this core. `now` is one trusted server `time()` sample
(integer epoch >= 600), not a client field; `remoteAddress` is the existing raw
server address with native `local` fallback, not a forwarded-header choice.

The admitted `main.login_attempts` must already have the known native shape:
`bucket TEXT PRIMARY KEY, starts INTEGER NOT NULL, attempts INTEGER NOT NULL`,
and legitimate integer counters. **Before any DELETE/INSERT/UPDATE**, a streaming
read of every row in the same caller lock validates SQLite `typeof` and uncast
PHP values: starts must be INTEGER decoded as int >= 0; attempts must be INTEGER
decoded as int >= 1 and < PHP_INT_MAX (safe to increment). Expired and unrelated
rows are included: bad counter fields must not disappear in cleanup or normalize
through SQLite arithmetic. TEXT (even numerically spelled), REAL, BLOB, NULL,
negative starts, nonpositive/overflow-bound attempts and stringified fetches
refuse the operation **before DML**. Valid future starts are not clock-repaired.
This is an O(number of counters) streamed scan, not a row-count/latency bound.

The guard validates these two stored counter fields, **not bucket spelling**,
the whole schema, triggers, views, function overrides or physical admission.
Those remain the trusted admission/caller's responsibility. A missing table,
invalid checked counter field or driver/cursor failure produces a generic
technical exception, not a 429 or an empty-store fallback. Fixed `main`
qualification prevents a TEMP shadow from silently selecting a different
budget. No schema introspection, cast, normalization or repair is performed.
The post-increment integer check remains defense in depth, not the preexisting
counter-field validation. These explicit corrupt-field refusals intentionally
differ from native SQLite's permissive coercion/cleanup; native policy parity
applies to valid stored counter state, not corrupt-data normalization.

Native policy is preserved: cleanup uses `starts < now - 600` (strictly older);
SHA256 of `ip:` plus raw address has limit 60; then SHA256 of `user:` plus PHP
`strtolower` of the **untrimmed** username has limit 15. Both successful and
failed attempts consume. Existing start timestamps do not slide on hits. An IP
count over 60 returns limited **before** touching the user counter; otherwise
the user count over 15 limits. No success reset, cookie namespace or new cache.

## Required later operation boundary

1. Trusted adapter admits the existing dedicated connection/schema and takes
   the single IMMEDIATE scope before calling this helper.
2. Call once. On technical failure, roll back and discard as required by that
   lifecycle; no other store, auth method, quota reset or automatic retry.
3. **Commit and confirm the attempt first**, for limited and allowed decisions.
4. Only after confirmed commit may an adapter map `limited` to native plain-text
   429, `Retry-After: 600`, and `Too many login attempts. Try again later.`
   Credentials/hash/key work must occur only after that committed allowed result.

Failed or uncertain COMMIT is a technical uncertain outcome, never a confirmed
429/allow result and never permission to retry. A returned decision followed by
rollback must be discarded. The core cannot enforce the caller's later commit;
there is intentionally no public HTTP/guard adapter or activation in this slice.

IMMEDIATE serializes cleanup, both increments and the decision. Native code's
autocommit statements can interleave and over-reject; this definition preserves
quota bounds and IP-first priority, **not identical outcomes for every native
concurrent interleaving**. No real SQLite locking/race proof is claimed here.

## Schema and composition gates still open

Native `security.php` lazily creates the table, but must not be included: its
bootstrap may create signing/encryption keys and initialize sessions. Fresh4
currently omits this optional table; RuntimeAdmission pins `schema_version`.
Runtime lazy DDL would invalidate admission. A separately approved NEW-only
producer may create the known empty table **before** ledger/manifest/key-profile
admission. Existing files require a separately explicit cold transition; no
automatic metadata rewrite, old-data migration or reset is offered here.

All live Web/API consumers must share the admitted database and exact bucket
domain. Current named Web cache throttles and the dormant Laravel API budget
are not made shared merely by adding this class. Future producer, trusted
RuntimeDatabase/AttendanceWriter counter operation, Web limiter, API adapter,
transition/error envelope and private key admission need coordinated ownership
and independent review before binding. Do not activate one side with an empty
separate cache or keep an unreviewed bypass path. LDAP login is not LDAP intake.

## Evidence boundary

Only PHP syntax checks and one coarse invented PDO/statement state-model proof
are supplied outside the source tree. It covers thresholds, priority, cleanup,
fixed windows, shared bucket inputs and rollback/commit ordering in that model,
plus raw malformed starts/attempts refusal before any modeled DML (including
expired/unrelated rows). SQLite dynamic typing and `typeof` are supported by
the official [datatype](https://sqlite.org/datatype3.html#operators) and
[typeof](https://sqlite.org/lang_corefunc.html#typeof) documentation; the fake
model does not execute SQLite coercion or certify its physical behavior.
There is no PDO connection, SQLite database (even memory), transaction, account,
credential, key, HTTP request, application boot, race test, server or CI run.
Source review, real admitted-handle/locking and assembled caller acceptance
remain separate gates. #197, native cutover and operational gates are unchanged.
