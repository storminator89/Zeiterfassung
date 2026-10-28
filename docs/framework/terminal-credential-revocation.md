# Separately closed Terminal credential revocation source

Base: actual Main `4d78a7003ca8e265fe7657c3e8af9ab762cd6e2c`, tree
`ba3a5bdbe563940d10b340c3f8b8680901e62ce7`. This source-only #79/#81 slice ports
only the existing native `terminals.php` **Zugang sperren** action. It creates no
account/device/credential, provisions no keys, changes no actual configuration
or runtime and does not execute a revocation. No previously prepared revocation
packet was found; the already integrated Site and access-list sources are kept.

## Independent route and display capabilities

The new trailing trusted `includeTerminalCredentialRevocations=false` registrar
argument requires the existing device page, non-debug mode and an explicitly
bound operation writer. It is not inferred from request fields, a feature's
presence, the existing Site flag or a UI property. Registration writes only two
in-process route-capability booleans: `chrono_terminal_admin.credential_revocations_enabled`
and `chrono_terminal_admin.site_creation_enabled`. It neither opens a writer nor
changes any environment/file/DB setting. Default boot stays `/up`; shipped
FreshWeb/fixture calls do not select the new capability.

There remains one POST `terminals.php`: registered when Site or revocation is
explicitly selected, within the unchanged encrypted, persistently blocked
Session/LegacyFormCsrf/current-account/auth:web/auth.session stack. The raw-form
exception and fixed POST401 response cover either selected command. All prior
arguments and the Budget/LDAP/Setup/Terminal choices keep their order/meaning.

**Action isolation:** a Site-only profile still rejects `revoke_credential`;
a revoke-only profile explicitly rejects `site`. New and unknown/mixed commands
cannot fall through into another operation. A combined profile may use both.
The Site capability is checked independently of the optional revocation facade,
so a missing facade cannot reopen Site creation in a revoke-only profile. The
legacy direct four-argument controller constructor remains compatible; routed
container composition always injects the trusted config repository.
No module toggle, device, pairing, PIN/card creation/replacement or generic
Terminal dispatcher is admitted.

`Interactions::terminalCredentialRevocations=false` is a separate display-only
capability. It is not authorization. UI composition must select it together
with the route capability to show the existing native per-row button. Without
both, the old four-field access-list reader and read-only table remain; there
is no target-ID projection or action column. A manually written request still
requires the selected server command, valid Session/CSRF and a current local
Admin, irrespective of display visibility. An incomplete UI composition is not
claimed to be an end-to-end working profile.

## Exact fixed mutation and fresh authority

The new facade supports only raw `action=revoke_credential` and `user_id` after
known CSRF fields are removed. It rejects other keys, non-string and noncanonical,
nonpositive or overflowing IDs before resolving the writer. Canonical positive
IDs are the native hidden-field representation; malformed native accidental
casts to zero or another integer are deliberately not preserved.

Only the existing `web` guard supplies actor identity. A current local Admin
with an existing auth-password value is required. The facade derives a SHA256
version from that refreshed guard value, never from request/session-selected
actor/version data. Under the existing one-attempt same-PDO IMMEDIATE writer
lock, `assertExpectedWebPrincipal(admin, 'local', version, 'admin')` re-reads and
checks current source, role and password version. The helper, transaction shell,
rollback/uncertain-COMMIT behavior and all previous writer methods are unchanged.

The operation then executes exactly the native prepared SQL:

```sql
UPDATE employee_credentials SET enabled=0 WHERE user_id=?
```

It reads or changes no raw PIN/card hash, key, device/module, user account,
attendance/work row or grant. The existing actor/reason scope is `web:{admin}` /
`terminals.php`; no new credential journal is invented (#32 stays separate).
The native zero-row and already-disabled behavior is retained: confirmed COMMIT
confirms this fixed UPDATE, not that an eligible row was present or that hardware
is online. There is no automatic retry or rollback/success claim on uncertainty.

Existing native `terminal_identify_core` and `terminal_dispatch_locked` already
check credential `enabled` and the credential version. They are not edited or
executed by this slice. No new hardware/concurrency/replay acceptance is inferred,
and no already committed booking is undone. This is **not central account
deactivation**: it does not end Web/API/LDAP sessions, prohibit their login,
change credentials, implement #30 or close lifecycle/security/operational gates.

## Minimal separate target projection, same native UI

The old `adminTerminalCredentials()` and its four fields remain byte-identical.
The separately selected `adminTerminalCredentialRevocationTargets()` reuses the
existing named snapshot and current local-Admin gate before a fixed query. It
projects the same four native display values plus `user_id` for the hidden form.
Target and labels are selected in one snapshot, never index-matched across two
changing lists. Existing INNER JOIN scope, lack of extra sort/cap and native
NULL/empty/string-0 card-presence semantics are retained. The SQL derives only
card presence; no raw hash is selected or passed to the DTO.

The new DTO boolean and Interactions argument are trailing false defaults;
previous positional/named callers retain their meanings. The existing device,
Site form and read-only access cards, classes and labels are kept. Only the
explicit selected profile adds the native CSRF-protected **Zugang sperren**
column/form (`action`, `user_id`); values are escaped. The normal native
`saved=1` redirect occurs only after the writer returns confirmed. Known form
errors are escaped; unexpected failures ask the user to reload/check, without
claiming rollback. Selected mutation-capable pages are no-store. No JS, new
modal, second UI, CSS, assets or account/secret input is added.

## Verification boundary

Necessary PHP/compiled-Blade syntax and independent source review are augmented
by one bounded synthetic smoke in a separate recovery copy. It uses only the
existing anonymous-memory v2 builder, disposable private copies, offline
admission and the actual writer; all temporary DBs are removed. A synthetic
guard (not login/session authentication) covers denial before writer resolution,
lock-bound stale role/password denial, the enabled-only target update, repeated
and absent-target native semantics, original/backup preservation and a fresh
target-label projection without secret values. Direct controller probes cover
all four Site/revocation selections plus a missing revocation facade. Four
fresh-process route-table probes check default-off POST, prerequisites, protected
middleware, fixed auth response mapping and raw-form preservation without
dispatching an HTTP request or resolving a writer/DB/session.

No real accounts, credentials, DBs, terminals or keys are accessed; no key is
generated. No login, Session/CSRF execution, page rendering, HTTP/browser,
hardware, concurrency or commit-failure acceptance is claimed. The standalone
smoke harness/evidence is outside the patch; no tests or runtime files are
committed. No server/host/runtime configuration, GitHub CI/write or publication
is performed. Source review covers the final immutable tree and independent
Site-only/revoke-only/default-off action mapping; it is not operational approval.
#79/#81 remain partial/open, and the protected native UI, concurrent/failed
commit/locking, browser/Back/repeated-POST, Session revocation and hardware
acceptance gates remain open. Any later serial Main union must be re-reviewed.
