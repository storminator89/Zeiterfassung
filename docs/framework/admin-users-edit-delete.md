# Existing Admin edit/delete boundary — inactive integrated source

**Follow-up #31:** The historical no-new-master-data-validation statement below
is superseded by the [shared field contract](../admin-user-assignment-integrity.md):
edit now checks required name/email and finite daily hours/opening balance in the
same existing writer, and target IDs use the existing canonical positive parser.
Authority, active/local recovery, last-Admin guards and transaction/SQL/password
branches are unchanged. No runtime activation follows from this validation.

Base: reviewed local LDAP/Admin freeze
`dd2f89f6cc99c5ed059446d12987291773ed2c62`, not a claim of remote Main or complete
application activation. The isolated six-file core `f3bbbbbf` was independently
source-cleared before these serial integration hunks. The fixed operations are
now composed into the existing writer/provider/Admincontroller and original user
controls, but default routes and runtime switches remain closed. No account,
credential or runtime file is created or changed by preparing this source.

## Owned files and shared integration reservation

- `lib/admin_users.php`: mechanically extract the existing target parser and
  target/last-admin check from `admin_mutate_existing_user`; its native wrapper
  retains pre-BEGIN ID validation and its existing transaction/callback behavior.
  Create and assignment helpers are unchanged. The extracted locked check neither
  owns a transaction nor accepts a mutation callback.
- New `LocalWebAdminUserMutations`, `LegacyLocalWebAdminUserMutations` and
  `AdminUserMutationResult` in `App\\Chrono`: strict edit/delete command boundary,
  current local Admin and hash captured from the refreshed trusted guard, lazy
  operation-writer resolution, and a nonsecret confirmed-commit transport hint.
- New `ManagedUserWriterOperations`: fixed operations for **the same** existing
  `AttendanceWriter`, not another writer/connection/transaction manager. A single
  trait-use declaration integrates it into that class; it uses only the
  captured PDO, private principal check, existing single-attempt `executeLocked`
  and scoped audit context. No public PDO/SQL/callback/rollback API is added.

The four common files were edited only after the separate LDAP mount was frozen,
independently source-cleared and handed over by Root. Its policy card, LDAP form,
no-store/local-asset/CSP/nonce headers, bind-password blanking and existing page
parameters remain unchanged. Only three exact native edit/delete labels were
added to each DE/EN/ZH dictionary. No new route, generic dispatcher, CSRF exception,
limiter, DTO or layout is introduced. The existing `admin.php` POST and
`get_user.php` GET are reused.

**Credential-document boundary:** the new UI/dispatch capability requires the
explicit V4 intake composition, `app.debug=false`, existing Admin interaction
flag and the new service. The private V4 nonce/local-asset document branch is
therefore mandatory for password editing. Old V2/V3 Admin views stay read-only;
the fixed backend core remains profile-independent. No password editor is silently
introduced into the old remote-asset branch. Turning all intake methods off does
not turn this existing-account capability off.

## Fixed behavior

Only `edit_user` and `delete_user` with their exact existing field names are
accepted; mixed actions, actor/source/version/SQL fields and nonstring form
values are refused before writer resolution. Neither path creates accounts or
calls the intake-policy gate. Turning every intake method off must still permit
the current local Admin to maintain existing accounts and recover configuration.

The writer rechecks current local Admin, source and the captured password-hash
version under its one IMMEDIATE lock, before reading/changing the target. Native
target validation, last-admin rule and `validateUserAssignment` are reused.
Department existence, eligible supervisor, self-assignment and the selected
supervisor-chain cycle checks remain unchanged; unrelated historical components
are not repaired. No new general master-data validation or account-status model
is introduced. The legacy readonly daily-hours form field and SQL projection are
preserved: this is not a replacement for dated append-only work-model changes.

Edit uses the existing two fixed SQL forms. An empty password, including PHP's
existing empty-string-`"0"` behavior, leaves the hash untouched; otherwise native
`password_hash(..., PASSWORD_DEFAULT)` is used without password normalization.
`auth_source`, API-token field, IDs, timestamps, work models and stored original
time/correction records are not rewritten. No secret is returned or flashed.

Delete retains the **exact native predicate**: any `audit_log` row with the
target's `user_id` refuses409. This includes account/work-model audits and V2
baseline rows, not just punch history. Ordinary valid initialized accounts may
therefore remain undeletable even without bookings. Never remove an audit row,
trigger, model or relation to manufacture a deletable account. A delete performs
only the existing fixed `DELETE FROM users WHERE id=?` if every safeguard passes;
there is no cascade, cleanup, repair or invented account deactivation.

Existing DB audit triggers run once in the same transaction, under `web:{id}` /
`admin.php`. V2 versus separately admitted V3/V4 audit payload differences remain
as they are. This packet adds no audit table/trigger or duplicate manual audit.

### Lifecycle notice correction on Main `4d78a700`

The Laravel history-protected delete still refuses with 409 under the exact
existing audit-row predicate; no account or history is changed. Its old native
hint incorrectly suggested that a password change plus terminal blocking was a
central access-removal workflow. The fixed refusal and a short accessible help
tooltip on the existing Admin card now state the actual limit: local password
changes do not prevent a new LDAP login and do not disable terminal access.
This is an intentional Laravel copy correction, not native-text equivalence or
an implemented account-deactivation feature.

The tooltip is hidden until focus, hover or activation, described by its labelled
button, remains reachable while hovered, and dismisses on Escape, outside pointer
interaction or leaving focus/hover. It uses the existing nonce-script branch and
local scoped styling; no remote asset, new form, request or preference is added.
Source/syntax inspection is not keyboard/touch/browser acceptance.

[#29](https://github.com/storminator89/Zeiterfassung/issues/29) still requires an
explicit stable-auth-version or deliberate single-session model plus separate
parallel-login and actual-revocation acceptance.
[#30](https://github.com/storminator89/Zeiterfassung/issues/30) still requires one
reviewed account-status/deactivation contract enforced across login, existing
sessions, JWTs, terminal grants and LDAP sync, without deleting history or silent
reactivation. No status field, hash/version lifecycle, schema/admission, auth,
Writer transaction, native endpoint, route/provider/profile or runtime flag is
changed by this three-file display-only correction.

### Explicit Root-approved Laravel recovery restriction

Native `COUNT(role='admin')` counts directory Admins, but the currently admitted
Laravel directory login supports only LDAP `role='user'`. Therefore the Laravel
writer **also** counts `role='admin' AND auth_source='local'` under the same lock:
the last local Admin cannot be demoted/deleted even if directory Admin rows exist.
An edit cannot assign an Admin/Supervisor role to a nonlocal target. Existing
directory Admin/Supervisor rows may be corrected back to the supported ordinary
user role without source conversion. These deliberate Laravel recovery limits
were explicitly requested by Root; they are not claimed as native equivalence or
a new general LDAP identity policy. Native handling is not silently broadened.

## Serial UI/transport integration and remaining acceptance

1. The trait is used by the existing writer with a separate lazy service binding;
   never replace create/intake/import/registration helpers or constructor gates.
2. Only `edit_user`/`delete_user` are added to the Admincontroller's strict action map,
   with a separate handler. Keep middleware, current-account refresh, session
   blocking, CSRF, local-Admin checks and all existing intake/LDAP actions intact.
3. Existing row edit/delete controls, dialog, fields and cancellation pattern
   are reused with a default-off display capability. Fetch only the
   current safe `get_user.php` projection. Keep its request-generation protection;
   blank the password on every open/close and ignore stale/dismissed responses.
   Preserve department/supervisor options without exporting hash/token/source.
4. `AdminUserMutationResult` is handled **after** the mutation try/catch. It is returned
   only after the existing writer's successful COMMIT. Self-password changes,
   self-demotion and successful self-deletion require the existing stock logout /
   session invalidation and normal-login redirect, not another Admin-page read.
   A later logout/read/render failure must never be reported as a rolled-back or
   failed mutation, automatically retried, or repaired by a fabricated session.
5. Native translated success labels and refusal messages are preserved. Unexpected
   pre-confirmation failures return a fixed unconfirmed outcome, never PDO,
   path, account, password or request diagnostics. Retain409 history refusal.

## Verification performed and still needed

Performed here: source inspection, PHP8.5.12 syntax checking, build-only Blade
compilation/PHP lint, static extracted-JS syntax, diff hygiene and shared-file
coordination. Compiled views and JS were not rendered/executed. No app/DB/bootstrap, real or synthetic accounts,
user passwords, HTTP/browser/listener, function tests, GitHub CI/write or
deployment is exercised by this preparation. This is not a functional pass.

After the combined source/security review and applicable authorization, a few
coarse journeys on the approved **published** acceptance target must establish:

- ordinary target edit/reload/history; empty versus changed password; IDs,
  existing work models/original bookings and allowed audit fields preserved;
- last total Admin and last local Admin with an existing LDAP-Admin row refused;
  LDAP-role promotion refused while correction back to user preserves source;
- stale/revoked/demoted actor and concurrent Admin changes denied under the
  writer lock; history-protected deletion retains all original rows;
- confirmed self-change followed by normal reauthentication, Cancel/Escape,
  repeated opens/newer selections, stale GET completion and blank-password reuse.

No local function tests or new GitHub-CI runs are part of this packet. Applicable
syntax/build checks are not substitutes for the published critical journeys.
