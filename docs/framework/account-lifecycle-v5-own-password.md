# V5 own-local-password business flow — Runtime remains closed

Source base: Main `c7abc17837f7f86b28ce3e2b3245633d21aa06ce`, tree
`f26b6bab589b727bb3ef8f2fe1cb1fef74316ae7`. This closes one missing business
operation in the already prepared Laravel Settings flow. It is not an installer,
profile selector, V5 Runtime admission, public route or Native cutover.

## Separate complete authority, not a legacy compatibility exception

The old `replaceOwnLocalPassword()` method remains byte-identical and still
refuses V5. The separate `replaceOwnLocalPasswordForAccountLifecycle()` accepts
only the existing captured `AccountLifecycleAuthority` and a bcrypt hash. It
requires an explicit V5 writer and local source/known role; it cannot target an
older profile. No request-selected actor, role, generation, source or SQL callback.

The existing `OwnPasswordChange` facade captures authority from the refreshed
stock web guard's `LegacyUser` through the existing trusted
`AccountLifecycleWebIdentity`. The immutable selected profile and identity must
agree. Missing V5 generation identity refuses before accounting/hashing/writer
resolution; inactive/departed/malformed captured rows refuse. The optional
constructor dependency preserves old V2/V3/V4 callers. The scoped provider adds
only this existing metadata-only identity; resolving it opens no V5 source.

The current strict separate form, current-password check, untrimmed 12–72-byte
new password, confirmation, configured bcrypt generation and persisted locked
FileStore quotas remain unchanged. No secret is flashed, rendered in value
attributes, logged or added to a quota/audit payload. Hash generation stays
outside the existing single-attempt IMMEDIATE writer transaction.

Inside that same writer lock, existing profile/active-principal checks verify
current ID, active status, integer generation, role, local source and exact old
password fingerprint. The subsequent fixed own-row CAS additionally includes
current hash, role, active state and generation; only the password column changes.
A zero/ignored row count fails and rolls back. Existing scoped/redacted audit and
transaction/COMMIT/idle-state ownership are reused, with no retry or second PDO.
The existing local-recovery invariant is checked against the replacement row;
it cannot silently make the last reachable local Administrator unusable. That
same invariant includes its existing bounded password-eligibility checks under
the lock; this is not a promise that all bcrypt verification is outside locks.

## Existing revocation policy and result handling

A password-only replacement does **not** invent a generation increment. This
matches the existing V5 Admin password-edit contract: old Web/JWT authority
fails its next existing password-version check. Generation still binds deliberate
account-status transitions, so deactivate/reactivate cannot revive an older
captured request even when the password stayed the same. Terminal PINs/grants
remain separate; no additional terminal-wide revocation or cancellation of
already executing operations is claimed.

The unchanged Settings controller reaches full stock logout/session invalidation
and303 to login only after the facade's confirmed COMMIT. Unknown operational
outcomes remain fixed503 with sign-out attempt and no automatic retry; a logout
failure after confirmed change remains a distinct503. Strict form failures do
not apply preferences. Existing session/CSRF/refresh/auth middleware remains the
required calling boundary; capture from a model is not a new login mechanism.

## Bounded component evidence

PHP8.5.12 / locked Laravel13.38.0: one coarse invented component journey uses the
actual Settings controller/Blade/CSRF middleware/SessionGuard, the new facade and
real existing writer. A canonical anonymous V5 memory fixture produces only new
private disposable SQLite files. The writer's rollback-journal-file requirement
is not weakened for testing. All credentials/signing bytes are invented test-only;
no operating source, account, key provisioner, directory or listener is used.

Observed: strict form/current-password refusal before writer resolution; one
password-only CAS and redacted audit, full logout after COMMIT, old session/JWT
and stale writer refusal, new-password login only, persistent sixth-attempt429,
LDAP and missing-generation refusal, unknown writer failure503 with no retry.
The same journey additionally exercises inactive/departed refusal, deliberate
deactivate/reactivate with permanent old-authority refusal, fresh recovered
authority, stale role/source, request-generation injection, ignored CAS rollback,
last-local-Admin recovery and successful local-Admin replacement. Password-only
generation and every other account field remain unchanged. Separate V2/V3/V4
old-writer calls retain their behavior and refuse the new V5 operation.

The existing full V2 Settings journey is independently rerun on the final source.
All three public V5 Runtime entrypoints still refuse before open; the ordinary
default acquires no Settings route or admitted writer. The component writer is
constructed directly with explicit fixture metadata, exactly as existing V5
rehearsals; no Runtime reflection, rewritten source or bypass flag is used to
pretend a physical Runtime was admitted. Original/backup fixture bytes remain
unchanged and temporary files/handles are cleaned up.

No actual HTTPS/browser/cookie/JWT transport, end-user deployment, physical V5
Runtime/FreshWeb admission, real LDAP/terminal, process concurrency, lost COMMIT
acknowledgement, crash/restore or human recovery acceptance. The unknown-writer
case is not a real lost-COMMIT fault test. Runtime/FreshWeb/Setup/shared-budget/
Native `HOLD_COMPATIBILITY` boundaries, NEW-files/provisioning requirements and
all public defaults remain unchanged. Existing dormant Runtime-profile/NEW-files
packages are not duplicated or silently admitted here. #30/#62/#76/#82 remain
open for complete operating composition and real acceptance.
