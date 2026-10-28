# Independently closed optional Terminal module switch

Base: actual Main `a0af8524830b79c1ecaf2b423ac7626c16734a9a`, after device
revocation #219. Existing transport/core/backend/HTTP ingress, device/access
readers, Site creation and both revocation operations are retained, not repeated.
This #79/#81 source-only slice ports the existing `terminals.php` **Optionaler
Terminal-Betrieb** / `action=toggle` card. It changes no actual installation
setting and activates no route or composition. Pairing and personal credential
provisioning remain unconnected; no key or credential source is needed.

## Separate route, display and server permission

The trailing trusted `LocalWebRoutes::register(...,
includeTerminalModuleSettings: false)` argument is independently default-false.
Selection requires the existing Terminal page, exact non-debug mode and an
explicitly bound operation-scoped `AttendanceWriter`. It records only the
in-process server capability `chrono_terminal_admin.module_settings_enabled`.
Registration resolves no writer, connection, key, native session or setting.
All existing registrar positions and shipped compositions remain unchanged;
ordinary boot still registers only `/up`.

The single existing Terminal POST is included when any separately selected Site,
credential revocation, device revocation or module capability requires it. It
retains encrypted cookies, persistent blocked Session, `LegacyFormCsrf`, refreshed
account, `auth:web` and `auth.session`. Raw-form preservation and the fixed POST
authentication 401 include the new operation. GET authentication still redirects.
Each command remains independently checked by its own server capability and
facade; a module-only profile cannot invoke Site or either revocation operation.
Unknown/mixed fields cannot dispatch another command.

The trailing `Interactions::terminalModuleSettings=false` is display only. Only
both route and display selection show the existing native card, field names,
classes and copy. The effective checked state remains native
`APP_TERMINAL_ENABLED==='1'` **and** stored singleton `enabled=1`, not the stored
flag alone. A dedicated fresh-current-local-Admin snapshot returns only these two
booleans; it runs only for the selected card. Neither rendered state nor a UI flag
authorizes a write. Selected pages are no-store. No new asset, URL or redesign.

The environment permission is **read, never set**. Selecting this source
capability does not set `APP_TERMINAL_ENABLED`, alter a host/security profile,
open public Terminal ingress or authorize cutover. No source shipped composition
selects it automatically. The visible server-permission wording is inherited
from the native UI, not an action performed by this package.

## Fixed operation under the existing writer lock

`LocalWebTerminalModuleSettings` accepts only `action=toggle` and optional
`enabled`. Missing `enabled` means false. Presence of a form string means true,
including an empty string, matching native `isset`; malformed arrays, objects,
booleans and null are rejected rather than coerced. Actor/role/password version
comes only from the refreshed local Admin web guard. An absent facade or
capability refuses before mutation; construction is lazy.

`AttendanceWriter::saveTerminalModuleSettings()` reuses the existing single-attempt
`executeLocked()` transaction shell and the same captured admitted PDO. Inside
that IMMEDIATE lock it first calls the existing
`assertExpectedWebPrincipal(admin, 'local', version, 'admin')`, then preserves the
native guards in their native order:

1. Enabling requires the exact current server flag `APP_TERMINAL_ENABLED==='1'`.
2. Disabling refuses while any `work_sessions.ended_at IS NULL` row exists.
3. Enabling from effectively disabled refuses while any legacy
   `zeiterfassung.endzeit IS NULL` row exists. Repeated enabling of an already
   effective-enabled module retains native behavior; no extra guard is invented.
4. Only `UPDATE terminal_settings SET enabled=? WHERE id=1` is executed.

No migration, schema/index change, copied connection, new transaction manager,
request SQL/callback or device/credential/user/grant/nonce/work-row update. Native
repeated/zero-row UPDATE semantics are retained; no stronger row-existence or
idempotency contract is claimed. The existing audit context is
`web:{admin}` / `terminals.php`; no new settings journal is invented.

Success redirects to the existing `terminals.php?saved=1` only after the writer
confirms COMMIT and idle state. Domain validation stays on the existing HTML
page. Unexpected failure asks to reload/check saved state and not automatically
repeat; it does not claim rollback or success. The writer's failure/poisoning
lifecycle is unchanged.

## Verification and remaining acceptance gates

A bounded coarse synthetic journey outside the patch uses an invented guard and
the existing known-v2 builder plus disposable offline-admitted DB copies. It
covers lazy defaults; rejected actor/form before writer resolution; stale current
role/password under the writer lock; server permission and old/new open-work
guards; repeat and checkbox-presence semantics; only singleton setting mutation;
fresh effective-state projection; independent four-command selection and missing
facade denial; and inert default/selected native-card rendering. Sixteen isolated
route-table subsets check capability selection, prerequisites, protected
middleware declarations, raw-form preservation and fixed authentication responses
without HTTP dispatch. PHP 8.5.12 and Laravel 13.38.0 compiled-Blade syntax plus
unchanged-path/inverse evidence supplement independent immutable source review.
The disposable CLI process alone sets/restores synthetic environment values;
no running service or actual installation setting is touched.

No real DB/account/device/credential/key, signing-key generation, HTTP/server/
browser, login or Session/CSRF middleware execution, new GitHub CI/test run,
publication, runtime/host/config change or activation. Tests/evidence are not
committed. Initial harness-only app-key/connection-name setup errors were fixed
outside source before the final synthetic pass; no product-source change was
needed for them.

The switch affects **both Web and Terminal booking mode** if deliberately
activated later. Source/synthetic checks do not establish safe operational
cutover, concurrent/in-flight booking behavior, interrupted/back/repeated browser
POST, Session revocation, uncertain-COMMIT, protocol/agent compatibility or actual
hardware/NFC acceptance. Those gates and #79/#81 remain open. Re-review any later
Main union, especially independently appended registrar/display arguments and
shared writer/provider/controller hunks.
