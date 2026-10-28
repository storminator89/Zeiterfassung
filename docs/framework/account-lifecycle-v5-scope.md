# #30 central account lifecycle — separate, not implemented by #29

Reviewed planning anchor: `a0af8524830b79c1ecaf2b423ac7626c16734a9a`.
**No status field, migration, endpoint or deactivate/reactivate operation is
introduced by this document. #30 remains OPEN.** A local password/LDAP-placeholder
rotation or terminal lock must never be presented as central deactivation.

## Coherent schema/admission boundary

A separate reviewed schema revision must own one canonical constrained
activation/departure status and a monotone local authorization generation for
each retained account. Exact field/table names and values must be fixed in that
revision rather than inferred from missing columns. The generation is necessary
so reactivation cannot revive pre-deactivation sessions/JWTs or short-lived
terminal grants. Directory passwords/remote groups remain external facts, not a
locally inferred status. Existing accounts need an explicit reviewed backfill;
unknown/partial/corrupt status must fail closed, not default to active.

The current `RuntimeAdmission`, `V2Preflight`, `RuntimeDatabase`, canonical
`IntakePolicyV4References`, `FreshIntakePolicyV4Installation`,
`FreshApplicationFiles`, source signatures and exact migration ledger recognize
V2/V3/V4. Adding a status column/table cannot be made usable by weakening their
exact signatures or silently calling it V4. Introduce a complete new canonical
profile, fixture, schema map, offline upgrade/preservation evidence and explicit
fresh-install path; retain current profiles unchanged. Test backups, restore,
partial-upgrade rejection and read/write/snapshot admission against the same
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
