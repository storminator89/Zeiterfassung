# Separately closed Terminal device revocation source

Base: Main `b482b1cd7f5aa22580747452cd710e2a7c103721`, tree
`f49109399934aea6cf7908698a0ae246c5d48cd1`, after the separate credential
revocation (#215) and admitted API composition (#216). This #79/#81 source-only
slice ports the existing native device-row **Sperren** operation. It does not
execute a real revocation, activate a profile, provision a device, create a
pairing code or read/replace any signing key. The earlier Site and personal
credential operations are retained, not reimplemented.

## Independent route and UI selection

The trailing trusted registrar argument `includeTerminalDeviceRevocations=false`
requires `includeTerminalDevices=true`, exact non-debug mode and an explicitly
bound operation-scoped `AttendanceWriter`. Registration records only the new
in-process server capability `chrono_terminal_admin.device_revocations_enabled`;
the existing Site and credential capability booleans remain independent.
No writer, DB, key source or native session is resolved by registration. Default
boot and every shipped composition retain their existing route selection.

There is still one POST `terminals.php`, registered when any of the three fixed
capabilities is selected. It retains the existing encrypted cookies, persistently
blocked Session, `LegacyFormCsrf`, refreshed account, `auth:web` and `auth.session`
chain. The raw-form exception and explicit POST-authentication 401 mapping cover
device revocation too. All preceding registrar arguments retain their order.

The controller routes only exact `revoke_device` / `id` to the new facade.
Unknown commands, other fields and mixed commands cannot select another action.
Site, credential-only and device-only compositions cannot invoke one another;
neither an absent facade nor a display flag grants another server capability.
The old constructor's positional arguments keep their meaning; the optional
device facade is trailing after the existing config argument. The legacy direct
four-argument Site constructor remains compatible; registered compositions inject
the config repository and enforce its separate Site boolean.

`Interactions::terminalDeviceRevocations=false` independently controls display,
not authority. Only both route and display selections show the existing native
per-device **Sperren** CSRF form (`action=revoke_device`, `id`). Existing device
IDs and labels already come from one current-local-Admin snapshot, so no new
reader or target projection is necessary. No raw generation/pairing data, key,
account field or secret is added to a DTO. Device status columns/classes, Site
form and personal credential list remain; there is no second UI or new asset/JS.
The selected view explains that pairing is revoked but existing bookings are not
changed. Mutation-enabled pages are no-store. Default read-only content is kept.

## Fixed mutation and current authority under the writer lock

The facade accepts only canonical positive decimal string IDs that fit a PHP
integer. Arrays, whitespace, signs, zero, leading zeros and overflow fail before
writer resolution. This intentionally does not preserve the native malformed
input cast-to-zero behavior. It takes the local Admin identity and SHA256 password
version only from the existing refreshed `web` guard, never request data.

The operation reuses `executeLocked()` and `assertExpectedWebPrincipal()` to
recheck that source/role/password version under the same one-attempt IMMEDIATE
lock as this native SQL:

```sql
UPDATE terminals SET enabled=0,generation=?,pairing_hash=NULL,pairing_expires=NULL WHERE id=?
```

The generation is exactly native `bin2hex(random_bytes(16))`, generated only after
fresh authority is checked. This is an invalidation generation, not a read,
derivation or rotation of an installation signing key. No current generation,
pairing code/hash or key is read, displayed or returned. Other device columns,
personal credentials, module settings, users, grants, nonces and work records are
not changed. Audit context stays `web:{admin}` / `terminals.php`; no new audit
journal is invented.

Native repeated revocation replaces the generation again; a valid absent target
still confirms a zero-row UPDATE. There is no row-existence or hardware-online
claim. Success redirect occurs only after the existing writer confirms COMMIT
and idle state. Unexpected failures ask to reload/check and not automatically
repeat; they never claim rollback or success. The existing transaction shell,
rollback and uncertain-commit behavior remain unchanged.

Native `terminal_auth_core()` requires enabled devices; the lock-bound
`terminal_dispatch_locked()` additionally rechecks current enabled/generation,
and `terminal_pair_locked()` requires the pairing hash/expiry. Those existing
rules are not edited or executed by this slice. Already committed work is not
undone and no concurrency, in-flight identify, hardware or protocol acceptance is
inferred. This is not personal credential revocation, central account/session
deactivation, re-pairing, key provisioning or module activation.

## Verification boundary

PHP 8.5 syntax, Laravel 13 compiled-Blade syntax, unchanged-path evidence and
independent source review are supplemented by bounded synthetic coarse checks
outside the patch. They use a synthetic guard and the existing anonymous-memory
known-v2 builder with disposable offline-admitted DB copies, not real accounts,
devices or keys. Covered: denied identities/malformed forms before resolving a
writer; stale role/password rejection under the writer lock; exact target
column changes; repeat/absent-target behavior; other-table/schema/original/backup
preservation; independent Site/Credential/Device command selection and missing
facades. Separate process route-table checks cover all eight capability subsets,
prerequisites, protected middleware, raw-form preservation and fixed 401 without
HTTP dispatch or DB/session/key resolution. Compiled-Blade syntax and source
review cover the optional column, escaping and preserved read-only default.

No local server, HTTP/browser, login, Session/CSRF middleware execution, real
account/device/DB/key access, signing-key generation, hardware, concurrent lock
or commit-failure acceptance is performed. No CI, GitHub write, activation or
publication is performed. Tests/evidence are not added to the repository. The
protected native UI, repeated/back/interrupted POST, Session revocation, race and
uncertain-COMMIT acceptance gates remain open. #29/#30/#79/#81 remain partial;
this source review is not runtime/cutover approval. Re-review any later Main union.
