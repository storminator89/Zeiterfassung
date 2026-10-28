# #30 central account lifecycle — separate, not implemented by #29

Reviewed planning anchor: `a0af8524830b79c1ecaf2b423ac7626c16734a9a`.
**#30 remains OPEN.** The dormant NEW-only schema/admission foundation below
now introduces the V5 fields and anonymous fixture, but no public lifecycle
endpoint or runtime activation. A local password/LDAP-placeholder rotation or
terminal lock must never be presented as central deactivation.

## Current source scope — Main `3cdcf90f`, 28 October 2026

The canonical schema/admission foundation is now implemented in an isolated
source patch, **not an activatable V5 runtime**. See the separate
[`account-lifecycle-v5-schema-map.json`](account-lifecycle-v5-schema-map.json)
for exact definitions and the four distinct V2/V3/V4/V5 signatures.
`RuntimeDatabase` explicitly refuses V5 reads/writes/snapshots before opening a
source; V5 never inherits the V4 capability. The fixture builds only an empty
anonymous-memory source. The standalone fresh registrar validates only a
caller-owned NEW canonical empty source; it adds no file opener, public setup
command or existing-data path. Schema constraints bind status/generation and
new grant issuance; they do not by themselves enforce the remaining channel or
last-local-Admin/recovery gates.

The owner prioritizes the complete Laravel transition without an elaborate legacy
migration; disposable test data may be discarded. The first V5 implementation is
therefore **NEW-only**: build a separate empty canonical V5 source and leave
existing V2/V3/V4 files and their exact contracts unchanged. No existing database,
history, key or credential is deleted, reset or migrated by this plan. Historical
upgrade/backfill alternatives below are not part of this selected first scope.

The intended channels are Laravel Web, Laravel API and Laravel terminal ingress.
An explicitly closed Native ingress for **new V5 only** is the candidate boundary
for this Laravel-only target, not authority to lock out an existing installation.
The Native shared entry is `config.php` after connection but before its first
DDL/seed, not merely `functions.php`. Placement, restricted `open_basedir`,
rollback-journal/WAL rejection, known-good Laravel replacement, local Admin
recovery and rollback are unresolved gates. Do not activate V5, relax a guard or
change a live Native entry point to make the plan appear complete.

### Proposed canonical state and non-revival contract

On new empty V5 sources, add constrained `users.account_status` with exactly
`active|inactive|departed`, and integer `users.auth_generation >= 1`. A new account
explicitly begins active/generation 1; this is not an old-account backfill or
permission to interpret missing columns as active. Each deliberate state
transition advances the generation exactly once; overflow and stale/replayed
commands fail without another transition/audit. SQLite CHECKs validate storage
type as well as value/range; ordinary edits and sync cannot reset the generation.
V5-specific redacted user audit
definitions include status/generation; existing V3/V4 definitions remain exact.
Bind new V5 terminal grants to their issued account generation as well.

Keep stock Laravel hashing, password marker and session engine. Add an explicit
V5 session-generation marker: never adopt a missing marker. JWTs need explicit
V5 profile/generation claims; reject pre-V5 tokens in V5 rather than assigning
generation 1. Every authenticated user-acting mutation receives trusted captured
source/hash/generation from its authenticated facade and rechecks them in the
same writer transaction. Preserve separately server-authorized first-admin,
self-registration, login-budget and device-only pairing/heartbeat operations;
require account generation whenever an operation uses a user identity or grant.
An active-row lookup alone cannot stop an old request after deactivate/reactivate.
Reactivation admits fresh authority only; it never revives an old session, JWT,
writer context, grant or separately disabled credential/device.

Protect the last **active locally login-eligible Admin** across status change,
demotion, deletion and source conversion under that same lock. Inactive Admin
rows, directory Admins and unusable local passwords are not recovery accounts.
Source eligibility cannot prove that a human knows a password; actual recovery
remains an acceptance gate, not an invented credential or automatic provisioning.

### Subsequent implementation file ownership

The table records the complete selected implementation boundary, including
the dormant schema/admission foundation now implemented and dependent future
edits; it is not unrestricted wildcard edit authority.
Application class paths are relative to `framework/app/`; other roots are explicit.

| Group | Exact source owners / required result |
| --- | --- |
| Canonical V5 | New `Accounts/AccountLifecycleSchema.php`, `framework/database/fixtures/account_lifecycle_v5.php`, V5 reference builder and fresh ledger registrar; separate entry in `LegacyDatabase/V2Preflight.php`. Keep V4 fixture/references/registrar and baseline `docs/framework/schema-map.json` unchanged; document V5 separately. |
| Admission | `LegacyDatabase/RuntimeAdmission.php`, `LegacyDatabase/RuntimeDatabase.php`, `framework/config/chrono_runtime.php`: explicit `account-lifecycle-v5`, four distinct predecessor/final signatures, versions `[1,2,3,4,5]`, exact four-row ledger. Never silently return V4 eligibility for V5. |
| NEW-only creation/setup | `LegacyDatabase/FreshApplicationFiles.php`, `Console/Commands/PrepareFreshApplication.php`; `Setup/FirstAdminSetupFiles.php`, `Setup/FreshApplicationKeyFiles.php`, `Setup/FreshLdapEncryptionKeyFiles.php`: explicit complete V5 branch, no existing-target replacement or old-data adoption. |
| Composition | `FreshWeb/FreshWebConfiguration.php`, `FreshWeb/FreshWebLdapSyncProfile.php`, `Providers/FreshWebServiceProvider.php`, `Providers/SharedLoginBudgetServiceProvider.php`, `Providers/LegacyRuntimeDatabaseServiceProvider.php`, `Providers/AppServiceProvider.php`, `Api/AdmittedLegacyApiConfiguration.php`, `Http/LocalWebRoutes.php`, `Accounts/IntakePolicyAccess.php`/`Accounts/IntakePolicyStore.php`: exact V5 profile everywhere; defaults off until all enforcement is coherent. |
| Paired identity metadata | `Setup/FreshLdapEncryptionKeyFiles.php::databaseProfileDigest` and `Ldap/LdapSyncFileSources.php::databaseProfileDigest`: identical complete V5 fields, including V4 predecessor signature. No real key/metadata access. |
| Web identity | `Auth/LegacyUser.php`, `Auth/LocalSession.php`, `Auth/ExistingAccountUserProvider.php`, `Auth/ExistingLdapUserProvider.php`, `Http/Middleware/RefreshLocalAccount.php`: active/generation checks including the stock local-only Eloquent profile, without a new guard or LDAP placeholder rotation. |
| Authoritative reads | `Chrono/LegacyChronoReads.php::snapshot`, `RuntimeDatabase::databaseSnapshot`, intake/Admin/LDAP confirmation readers: captured actor generation in the same snapshot; do not hide inactive subjects' retained history from authorized readers. |
| All writes | `LegacyDatabase/AttendanceWriter.php`, `LegacyDatabase/ManagedUserWriterOperations.php`, `LegacyDatabase/LdapSessionWriterOperations.php`; calling `Chrono/LegacyLocalWeb{Attendance,Absences,Corrections,CorrectionWithdrawals,WorkModels,PauseSettings,Departments,CarryIn,TerminalSites,AdminUsers,AdminUserMutations,TerminalCredentialRevocations,TerminalDeviceRevocations,TerminalModuleSettings,LdapSync,LdapSessionRevocations}.php`: current actor state and captured generation under the sole existing writer lock. |
| API | `Api/LegacyApiLogin.php`, `Api/LegacyApiIdentity.php`, claim validation in `Api/LegacyIssuedTokenVerifier.php`, `Chrono/LegacyApiAttendance.php`: V5 issuance, verification, reads and lock-time writes use the same generation; no V5 null-context compatibility bypass. |
| Lifecycle command/UI | New fixed writer/facade contract; existing `Http/Controllers/LocalWebAdminPauseSettingsController.php`, `Http/LocalWebRoutes.php`, `View/ExistingUi/AdminPauseSettingsPage.php`, `View/ExistingUi/Interactions.php`, `framework/resources/views/chrono/admin-pause-settings.blade.php`: target/state/generation-bound confirmation, reason, exact fields, CSRF, no-store and confirmed-COMMIT outcome. No decorative status toggle. |
| Terminal | `lib/terminal_core.php`, `AttendanceWriter::terminal` and relevant Laravel protocol continuations: final grant issuance atomic with active/generation check; event, replay and historical receipt all bound to current authority. |
| Enrollment/sync | `AttendanceWriter::{firstLocalAdministrator,registerLocalUser,createLocalUser,importLdapUsersWithIntakePolicy}`, `Ldap/MaterializedLdapImport.php`, `lib/ldap_import.php`: explicit new-account defaults; preserve retained status/generation and identity collisions; sync never reactivates. LDAP confirmation tickets bind both actor and target generation. |
| Native boundary, still gated | Repository-root `config.php` and every admitted ingress to it: only a separately reviewed new-V5 refusal can close the old bypass. Do not publish/activate that refusal without the safe Laravel/recovery/runtime gates above. |

`SourceSnapshot`, `OfflineFiles`, `UserAuditV3Upgrade` and offline upgrade commands
need no migration-related edits for this NEW-only scope. No second LDAP connector,
new guard/queue/paid service or host/security change is introduced.

### Source-confirmed traps and bounded evidence

1. `LegacyApiAttendance::transition` currently forwards no password version or
   generation. The writer's old `null/null` principal compatibility path must
   never admit V5 writes from a pre-deactivation request after reactivation.
2. Older `workModel`, `pauseSettings`, `department`, `createTerminalSite` and
   `ownCarryIn` writers recheck role/source rather than a fully bound version.
   Inventory their callers and every equivalent; a current-active lookup is not
   enough to reject an old in-flight action after generation changes.
3. `terminal_identify_core` persists limits and then inserts its grant outside
   the event lock. A prior active check can race with deactivation. Keep failed
   PIN/device limits persistent, but recheck device, credential, account state
   and generation with the grant INSERT under one writer lock. Wrapping the
   whole old identify flow in a rollback transaction would erase failed-attempt
   limits. Event **and** historical receipt/replay need generation binding.
4. Native login still converts matching local names and rotates LDAP placeholders.
   V5 Laravel enforcement is not global while an old Native ingress can use V5;
   close the boundary deliberately rather than hiding a runtime cutover.

Prefer one coarse invented journey: fresh local Admins and ordinary LDAP user;
issued Web/JWT/writer/grant authority; deliberate deactivate; refusal across
channels; unchanged retained history; fake sync cannot reactivate; last-local-
Admin refusal; deliberate reactivate allows fresh but never old authority. Cover
the API in-flight and terminal identify/receipt boundaries in that same scenario.
Use disposable synthetic fixtures/fake directory only, never real accounts,
keys, directory or operating database. Syntax/build checks are separate. The implementation checkpoint exercised one coarse anonymous-memory
schema/admission journey and PHP syntax checks; it did not run a new CI dispatch.
This plan alone does not authorize additional tests or CI dispatches. Actual HTTP/browser/CSRF,
concurrency/commit-failure, filesystem-admission and recovery remain explicit
gates; report only genuinely exercised boundaries at the exact commit/runtime.

## Coherent schema/admission boundary

A separate reviewed schema revision must own one canonical constrained
activation/departure status and a monotone local authorization generation for
each retained account. Exact field/table names and values must be fixed in that
revision rather than inferred from missing columns. The generation is necessary
so reactivation cannot revive pre-deactivation sessions/JWTs or short-lived
terminal grants. Directory passwords/remote groups remain external facts, not a
locally inferred status. The selected first implementation creates only a new
empty V5 source, not an existing-account backfill. Unknown/partial/corrupt status
must fail closed, not default to active.

The current `RuntimeAdmission`, `V2Preflight`, `RuntimeDatabase`, canonical
`IntakePolicyV4References`, `FreshIntakePolicyV4Installation`,
`FreshApplicationFiles`, source signatures and exact migration ledger recognize
V2/V3/V4. Adding a status column/table cannot be made usable by weakening their
exact signatures or silently calling it V4. Introduce a complete new canonical
profile, fixture, separate schema map, NEW-only preservation evidence and explicit
fresh-install path; retain current profiles unchanged. Review backups, restore,
partial-profile rejection and read/write/snapshot admission against the same
profile. Never open or migrate an operating DB through an HTTP action.

## One locked transition and deliberate recovery

The local Admin's explicit deactivate command must bind the displayed target
and expected state/generation. The dedicated existing writer lock must recheck
the actor's current **active local Admin** status/source/role/version, target
state and compare-and-swap revision before changing anything. Deactivation must
be idempotent or return a stale-state response with no ambiguous extra mutation.
One redacted audit transition records actor/reason and state/generation, never
credentials. Do not delete attendance, sessions of work, requests, audit rows,
relationships or history; do not auto-end a running attendance interval.

Protect the **last active reachable local Admin**, not merely the count of all
Admin-role rows. Apply that same invariant to status changes, edit/demotion,
delete and source conversion; an inactive or LDAP Admin is not local break-glass.
An explicit reactivation is a separate authorized transition with a fresh
generation and recovery checks. Re-enabling an account must not re-enable a
separately disabled terminal credential/device or restore previously issued
credentials. A forgotten success response is not authority to retry a transition.

## Required enforcement map — one status, not a UI-only toggle

| Boundary on this anchor | Required property |
| --- | --- |
| `Auth/LocalSession`, `ExistingAccountUserProvider`, `ExistingLdapUserProvider`, stock Eloquent local profile and `RefreshLocalAccount` | Current status required on both login paths and every authoritative session refresh; generation binding survives login without rotation and invalidates old sessions permanently after deactivate/reactivate. No new custom guard/session engine. |
| `Api/LegacyApiIdentity`, local API login/issuance and `AttendanceWriter::assertExpectedWebPrincipal` | Current active status and generation checked on JWT verification/issuance and again under the write lock; old tokens do not revive on reactivation. Preserve already-issued token compatibility only through an explicit documented version transition. |
| `Chrono/LegacyChronoReads`, `RuntimeDatabase::databaseSnapshot`, scoped Admin/review/settings/model writers | Same-snapshot current active actor for reads; same-lock current active actor for every mutation, including older writer methods that presently recheck only role/source. Disabling a user must not erase visibility of their retained history to authorized supervisors/Admins. |
| `lib/terminal_core.php` identify, grant dispatch and same-lock retry paths; terminal credential Admin workflow | Inactive users cannot receive or use grants. Bind grants to account generation or explicitly revoke them under the same transaction; repeated event/receipt paths must not bypass inactive-user checks. Never confuse device/credential enabled flags with account status. |
| `AttendanceWriter::importLdapUsersWithIntakePolicy`, `lib/ldap_import.php`, manager/assignment and local-name collision boundaries | Sync may update allowed directory attributes but never clear inactive/departed status or reset its generation. Existing disabled names remain retained and collision-protected; no drop-and-recreate or silent source conversion. No inference that absence from one incomplete search means departure. |
| First-admin setup, local self-registration and manual account creation | New-account defaults are explicitly fixed by the new canonical schema; closed setup and enrollment-policy gates remain authoritative. Registration cannot reactivate or replace an existing retained identity. |
| Native `login.php`, `functions.php` session checks, `api.php`, `lib/attendance.php`, terminal/LDAP/Admin helpers | Native compatibility must be an explicit selected target. Do not claim global lifecycle enforcement while an enabled native entry point bypasses it; do not bundle an unapproved Native cutover or real directory/profile change. |

## Existing UI and acceptance

Use the current Admin user table/dialog patterns, navigation, typography and
colors. Display the persisted state truthfully and require deliberate target-
specific confirmation with a reason. Keep deactivate/reactivate distinct from
“end LDAP sessions,” password editing, delete and terminal locks. No optimistic
success before commit, no automatic retry after uncertain commit, no request-
selected source/role/actor. All-off intake and ordinary local Admin recovery
must remain available.

One coarse synthetic journey can cover an invented active/departed transition,
unchanged history, a fake sync that cannot reactivate, last-local-Admin refusal,
old session/JWT/writer/grant denial and fresh deliberate reactivation that does
not revive old authority. Separate two-browser/real-directory/HTTP/CSRF/terminal/
concurrency/commit-failure/restore acceptance must name the exact reviewed commit
and runtime. Only claim passed boundaries actually exercised. No real account,
directory credential, operating DB, key, host/security setting, new paid service,
GitHub write or activation is authorized by this planning document.
