# Terminal device administration in the existing journal (#32)

This bounded addition journals the existing administrative new-device enrollment
and device-revocation saves. It does not activate Terminal, change its protocol,
issue new permissions or attest that a device has actually paired. #32 remains
partial/open: personal credential changes, protocol-side pairing/heartbeat and
historical gaps are not covered by this addition.

## Redacted entity and existing scopes

The fixed `terminals` entity contains only `id`, `site_id`, `name` and the saved
`enabled` value. Enrollment is INSERT with no before snapshot; revocation is
UPDATE with both selected snapshots. A repeated revoke still replaces its
invalidation generation exactly as before and records the selected no-op values.
A missing device retains its successful zero-row behavior without an invented
journal row. Neither enabled=0 nor an INSERT proves why a device is disabled;
this is a saved administrative state, not current connectivity or pairing status.

No enrollment code, generation, pairing hash/expiry, last contact, HMAC key,
PIN/card, grant, employee credential, password or token is selected for the
journal. Only this fixed entity is added to the existing history filter and
selected-field presentation/export; unexpected fields remain hidden. The old
journal query remains unchanged: owner `user_id=0` is visible to administration,
not members or supervisors. Device names are the existing business field, not
new inferred personal metadata. Time and actor/action reason come from the
existing trusted SQLite UDFs, never supplied actor parameters.

## Atomic administrative save, not a new writer

Native and Laravel preserve their existing authorization, local Admin/version
recheck, module/server gates, name/site validation and caller-owned transaction.
The existing INSERT stays in each caller; its device ID is captured **before**
the journal INSERT changes lastInsertId and that captured ID still feeds the
existing one-time-code response/session result. The revocation SQL is shared
without changing its random-generation replacement or pairing-field reset.
Both journal operations require an existing transaction, open no DB/file/route,
register no UDF and accept no arbitrary entity, SQL, actor or callback.

A journal failure propagates to the existing transaction owner, so the device
change and journal cannot be committed independently by these save paths. The
previous COMMIT/rollback/unknown-outcome handling remains authoritative; this
addition does not claim recovery from a lost COMMIT response. The append-only
journal protections remain unchanged; operator SQL, protocol actions and old
history are not newly captured or reconstructed.

No schema, trigger, application version, ledger, runtime/admission, security or
host setting changes. No connection between this feature and the held V6 or
Native-cutover packages. Personal credential journaling is separate work, not
silently included here.

## Bounded verification

PHP 8.5.12 / SQLite 3.46.1, existing locked Laravel dependencies: one coarse
private disposable V4/V5 component journey exercised the actual revoke writer,
repeated/absent devices, exact IDs above 2^53, saved generation/pairing-reset
semantics, unchanged unrelated tables/schema, Admin-only selected history/export,
nonadmin/current-version/malformed-input/closed-host refusals and complete
rollback of device, journal and sequences on a late journal failure. Shared
Native journal cores were exercised under the original audit UDF context with a
manually reproduced enrollment INSERT using fixed unusable source markers;
device-ID capture, caller rollback and idle refusal passed. The positive full
Native enrollment handler and positive Laravel enrollment writer were **not**
executed. Actual enrollment writer verification is limited to closed-host refusal.
Canaries for excluded source values did not appear in the journal; unexpected
snapshot fields were omitted from presentation. Owned private fixtures were
removed. Necessary PHP syntax and diff checks passed; no new repository tests,
workflow changes or manual CI dispatch.

No real credentials, enrollment-code/credential/key generator, live account,
server or host flag was used. No full app/HTTP/auth/CSRF/browser/visual/device/
protocol/hardware/concurrency/crash/production or deployment acceptance is
claimed. The existing UI markup and styles are unchanged.
