# #30 — dormant V5 writer core, not central deactivation

Source anchor: Main `3cdcf90f`. This additive core depends on the separate NEW-only
V5 schema/admission package. **#30 stays open; no route, UI, provider, source,
Native boundary or runtime is activated.** `RuntimeDatabase` must still refuse
V5 before opening a file. V2/V3/V4 keep their existing profile/operation contracts.

## Implemented isolated contract

`AccountLifecycleAuthority` captures an already authenticated active account's
ID, source, role, password fingerprint and explicit integer generation. Capture
is trusted facade work, never parsing request/session data. Missing/invalid
status or generation is refused; no V4 authority is adopted as V5. Same-lock
matching rejects a former generation even after deliberate reactivation. The
object is not serializable and does not expose its password fingerprint in
debug output. This class alone is **not** a Web/JWT/session/grant integration.

`AttendanceWriter::changeAccountLifecycle` accepts this separate actor and a
target/expected-status/expected-generation/next-status/reason command. Its existing
single-attempt IMMEDIATE transaction checks the current active locally eligible
Admin, checks the exact target state/generation, preserves local recovery, then
updates just status and generation in one compare-and-swap. Equal-state commands,
overflow and stale/replayed commands fail without a transition/audit. The V5 user
trigger emits one redacted transition; no second explicit audit is inserted.
The result escapes only after confirmed COMMIT and idle-state verification. The
existing failure shell rolls back where possible, never retries, and never turns
an uncertain COMMIT into a success or a safe-to-retry response.

Neither deactivation nor reactivation touches attendance, open intervals,
requests, history, grants, credential enabled flags or devices. In particular,
reactivation does not re-enable a separately disabled credential/device. The
old grant row being retained is not authority to use it: terminal verification
still requires a separate V5 implementation before runtime activation.

Managed edit/delete accept an additional optional V5 authority argument. In V5
it is mandatory and bound to the existing trusted ID/source/hash/role tuple
under the same lock. Ordinary edits retain status/generation. Status changes,
demotion, deletion and password replacement retain at least one active local
Admin with a lookup-eligible nonempty, trim-stable username and a PHP-recognized
password hash that is not the explicitly rejected empty password or
`defaultpassword`. LDAP Admins, inactive/departed rows and broken local hashes
are not recovery accounts. This proves syntactic login eligibility only, never
that a human knows the password. No source-conversion operation is introduced.

## Deliberate fail-closed boundary

The appended constructor flag is trusted admitted-profile composition only,
not a configuration toggle or permission to activate V5. Ledger/profile mismatch
is rejected. V5 lifecycle/edit/delete recheck the profile under the writer lock.
All other existing writer operations refuse V5 before their transaction body.
Terminal ingress refuses V5 even earlier, before its Native autocommit
nonce/limit/identify effects or key callbacks. Thus older API null/null context,
settings, intake, sync, login-budget and setup methods cannot quietly apply V4
rules to V5. No runtime caller enables the new constructor argument.

## Bounded evidence and remaining gates

One synthetic PHP 8.5.12 writer journey exercises actual disposable SQLite
files materialized from the canonical empty V5 fixture: CAS/replay/audit,
deactivate/reactivate/non-revival, retained open attendance/history/grant/device
rows, recovery refusal across state/demotion/password/delete, a fresh ordinary
edit retaining authority, missing/stale actor refusal and early old-path refusal.
It also exercises the existing V4 managed-edit signature and pause-settings
operation unchanged. Synthetic files are removed; no operating database,
account, key, directory or host/security setting is used.

No real Web/session/JWT, HTTP/CSRF, terminal grant dispatch/receipt, LDAP sync,
concurrency, injected COMMIT failure, restore or human recovery acceptance is
claimed. Those gates, all V5 enrollment defaults and authenticated facades,
same-snapshot reads, generation-bound terminal identify/event/receipt and the
reviewed NEW-V5-only Native refusal remain required before activation. Do not
expose a lifecycle button or mark #30 complete using this core alone.
