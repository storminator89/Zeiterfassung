# #30 — prepared V5 Admin account-status flow, still unbound

**Source-only preparation; not an activated account-status feature.** Exact source
basis: Main `2072a6e2e167c8cd3cf51ea81c7765840d93647b`, tree
`a4ca5657569c056af79461bf370fead89519789e`. #30 remains open. No live account,
Runtime, FreshWeb, Native, provider, route, deployment or security configuration
is changed by this packet.

The existing `AttendanceWriter::changeAccountLifecycle` already implements the
one-lock transition. The missing everyday flow was its authenticated Web caller,
current target display and deliberate action in the existing Admin user table.
This packet implements that flow without duplicating the core or installing a
second status/authentication engine.

## Deliberate existing-UI flow

`LocalWebAccountLifecycle` has no provider/route binding. The existing Admin
controller gains a final optional constructor dependency, default `null`; the
ordinary application/container and current routes still have no such capability.
Only explicit component injection, existing Admin-page interactions, full session
service and non-debug composition select the prepared display/action. Merely
adding status columns, a query parameter, form field or display flag cannot
select a V5 profile. The concrete facade additionally requires immutable V5
Runtime metadata and `AccountLifecycleWebIdentity::requiresGeneration()` before
reading a source, issuing a confirmation or resolving a writer.

The existing ten-row user search/page supplies IDs, not a request-selected actor
or unbounded target scan. A separate idle named `chrono` read snapshot rechecks
the refreshed guard's complete active local Admin authority and syntactic local
login eligibility. It projects only each retained target's ID, username, current
status and generation. Missing targets are omitted; malformed states/generations
fail closed. No password/hash/source/model is passed to the page. All snapshot
PDO/statement work ends before ordinary framework encryption; no reader becomes
a writer and no transaction is upgraded or retried.

Each other permitted status gets one ten-minute authenticated-encrypted ticket,
using the existing Laravel `Encrypter`: fixed V5 purpose, actor ID/password version
and generation, target ID/expected status/generation, next status and expiry.
The ticket is neither a credential nor a reserved transition. It is not stored
in sessions or forwarded to audit. The final POST accepts exactly the action
flag, canonical positive target ID, confirmation and explicit reason. Arrays,
coercions, extra actor/source/generation fields, overflow, mixed actions,
tampering, wrong target, wrong/old actor, expiry, empty/control/overlong or
outer-whitespace reasons fail before writer resolution. The existing
`AccountLifecycleChange` owns the state/range/reason rules; no state defaults,
normalization, free-form SQL or caller-selected callbacks are introduced.

The original Admin card/table, button classes and `<dialog class="modal">`
pattern remain. A status badge truthfully distinguishes **Aktiv**, **Deaktiviert**
and **Ausgetreten**. Separate **Aktivieren**, **Deaktivieren** and **Austritt
erfassen** buttons open exactly one target-specific dialog with a required reason
and explicit action. There is no second checkbox, browser `confirm()` or extra
confirmation page. Dynamic labels use escaped attributes and `textContent`, not
HTML injection. Cancel/Escape/close clear the selection/reason; ordinary form
submission preserves posted controls, disables repeat submission and performs no
fetch/retry. Back/Forward-cache restoration clears the old form, disables its
actions and visibly requires a reload. Existing CSRF and Admin document
nonce/CSP/no-store policies are reused, not relaxed.

## Existing lock, data and recovery remain authoritative

Only the existing `changeAccountLifecycle` operation receives the captured actor
and validated command. Its unchanged IMMEDIATE single-attempt writer rechecks
that same complete actor, target status/generation and local recovery invariant
under the same lock, performs the one status/generation CAS and emits the
existing single redacted audit. A stale/replayed target returns409 without
another mutation. A formerly active actor cannot revive after deliberate
reactivation: its old generation still fails401 inside the lock.

The flow does not delete retained users or attendance/history/requests/links,
end an open work interval, rotate passwords, contact LDAP, infer departure from a
sync, or re-enable separately disabled credentials/devices. Activation increments
generation; it never restores old session/JWT/writer/grant authority. The last
syntactically login-eligible active local Admin is protected by the existing core.
This does not prove that a human knows a recovery password or has tested restore.
The core and all existing Web/JWT/LDAP/terminal enforcement are unchanged.

Only a confirmed writer COMMIT produces the minimal actor/target/status receipt
and303 back to the existing Admin card. A new-form/read failure does not erase
that known committed receipt or invent another confirmation. Self-deactivation
or own departure uses the existing complete logout/session invalidation/CSRF
regeneration and303 to login, not a stale Admin continuation. Unexpected writer
failure returns fixed no-store503, logs out where possible, emits no success or
new form and never retries. A failure after known COMMIT is labelled as saved,
not as a safe-to-retry failed transition. Reasons/tickets/secrets are not flashed.

## Why Setup/FreshWeb is still blocked

| Existing gate, deliberately unchanged | Remaining requirement |
| --- | --- |
| `RuntimeDatabase::requireImplementedRuntime()` in public read/writer/snapshot entrypoints | Complete coherent V5 cross-channel physical/runtime and recovery admission, not a V4 alias or bypass opener. |
| `FreshWebConfiguration::fromApplication()` exact `intake-policy-v4`, and `FreshWebServiceProvider` V4 runtime check | Reviewed complete private V5 composition plus explicit route/capability selection; no service binding is supplied here. |
| `FirstAdminSetupFiles::prepareNew`, `FreshApplicationKeyFiles::createNew`, `FreshApiSigningKeyFiles::createNew`, `FreshLdapEncryptionKeyFiles::createNew` | They require V4 and then read through the closed Runtime. Simply deleting their profile check would neither work nor safely permit token/key generation. Select exact V5 fresh references/complete manifest/ledger only after coherent admission; preserve private NEW-only identity, empty state and closure. |
| `FreshWebLdapSyncProfile` and `SharedLoginBudgetServiceProvider` | Bind the same complete frozen V5 manifest and physical profile without weakening default-off selection, policy, setup closure or persistent budget. |
| Existing Native boundary and `HOLD_COMPATIBILITY` | Deliberate refusal/replacement and known-good Laravel/local-Admin recovery; no Native cutover is inferred from a UI button. |

Existing separate packets `chrono-v5-new-files-maine954a448-support`,
`chrono-v5-runtime-profile-main25eb9778-support`,
`chrono-v5-terminal-admin-main091f843b-support` and
`chrono-v5-own-password-mainc7abc178-support` already cover their named dormant
source slices. This packet does not duplicate, bundle, publish or activate them.
Their independent final-Main reanchors/reviews remain necessary before assembly.
No new install-preflight wrapper or secret-generation shortcut is added.

## Bounded evidence and remaining acceptance

PHP8.5.12/Laravel13.38.0: one coarse explicit-component journey runs the actual
Admin controller/Blade/CSRF, real stock array SessionGuards, current-snapshot
facade and original file-only writer on NEW disposable private canonical V5
SQLite files generated from anonymous memory. It covers default unbound refusal,
strict fields/reason/tamper/expiry/target binding before writer, actual CSRF denial,
deactivate/reactivate/departure, target replay409, one redacted audit, confirmed
receipt, unchanged history/open interval, old session/JWT/writer non-revival,
self-logout, last-local-Admin refusal, real deactivate/reactivate of an in-flight
actor, preserved saved receipt despite new-form failure and unconfirmed writer
resolution503/logout/no retry. Original/backup bytes are unchanged and all owned
temporary cohorts are removed. No Runtime opener, reflection, source rewrite,
file-only-writer relaxation, real account/credential or operating source is used.

A Node24.22.1 VM runs the actual rendered dialog script against minimal DOM
doubles for open/cancel/close/reset, escaped target text, serialized controls,
repeat-submit refusal and visible Back/Forward-cache reload requirement. This is
not browser/dialog/focus/history/visual acceptance. Four PHP source lints, three
Laravel-compiled Blade lints, actual rendered JavaScript syntax, inverse/full-tree
preservation and cold pack import are separate checks, not a new repository test
battery or manually dispatched CI run.

Real HTTP/two-browser/cookie/HTTPS/CSRF-stack/CSP/visual acceptance, real directory
and terminal/grant integration, concurrency, lost COMMIT/crash/restore, actual
setup/token/key provisioning and human recovery remain untested here. Do not
bind or expose the prepared control, remove the Runtime hold, claim global
lifecycle acceptance or close #30 on this component evidence alone.
