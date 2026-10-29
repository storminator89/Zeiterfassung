# FreshWeb: existing LDAP roles and local recovery (default off)

The explicit existing-account provider uses Laravel's stock `SessionGuard`,
`LocalSession`, `RefreshLocalAccount` and `AuthenticateSession`. Existing persisted
`user`, `supervisor` and `admin` roles are admitted for both local and LDAP
accounts; no directory group mapping, automatic promotion, source conversion or
account creation occurs at login. This is source composition, not deployment or
real directory acceptance. All login/sync/terminal flags still default off.

## Package, transport and secret transition

The maintained-package decision and version evidence remain in
[LDAP provider boundary](ldap-provider-boundary.md): the reviewed LdapRecord
v4.1.1 password transformation was not suitable for literal native password
bytes; the supported PHP 8.5/OpenLDAP extension boundary is used instead. Verified
StartTLS, certificate/name checking, explicit CA/target, timeouts, filter/DN
escaping and bounded complete collection are not weakened by role admission.

Login-only operator inputs remain `FRAMEWORK_CHRONO_LDAP_LOGIN_ENABLED` (false),
`FRAMEWORK_CHRONO_LDAP_LOGIN_URI`, `FRAMEWORK_CHRONO_LDAP_LOGIN_BASE_DN` and
`FRAMEWORK_CHRONO_LDAP_LOGIN_CA_FILE` (null). They never come from a request,
settings-table fallback, sync credentials or native bootstrap. Metadata loading
and local login do not read a CA or test directory reachability. Corrupt operator
metadata fails profile admission; directory outage never turns into local-password
fallback for an LDAP identity. Local recovery does not contact LDAP.

The independently admitted sync profile reads the **existing** native
`encryption.key` only for the explicitly submitted bind password, keeping native
v2 AES-256-GCM, binary `hex2bin` root, 12-byte IV and 16-byte tag. No Laravel Crypt
reinterpretation, root regeneration or legacy ciphertext acceptance is added.
As in the original native form, sync requires a newly supplied bind password;
this is not a new stored-password decrypt/reuse endpoint. See
[the exact sync contract](fresh-ldap-sync.md).

## Roles are business authority, not security administration

| Operation | Existing LDAP account | Local security boundary |
| --- | --- | --- |
| Own attendance, absences, history and correction requests/withdrawal | User, Supervisor, Admin, unchanged own scope | Same source/hash and V5 generation checks |
| Current direct-team reads and correction review | Supervisor; no self-review or foreign team | Existing domain policies unchanged |
| Business-wide review/reads, pause rules, departments, work models and own carry-in | Admin; no self-review | Current Admin role/source/hash under snapshot/writer lock |
| User/role/password CRUD, intake policy, lifecycle changes, LDAP sync/session revocation | Not a directory-Admin capability | Current local Admin only |
| Terminal/key administration, secret-bearing DB snapshot and recovery | Not a directory-Admin capability | Existing local actor guards unchanged |
| Own local password change | Not an LDAP password operation | Existing local-only service unchanged |

The Admin document loads only business fields for LDAP Admins: no user table,
security forms, confirmation tickets or sensitive user-data JavaScript are
fetched/rendered. Direct sensitive POSTs are also denied before services/writers.
A local Admin can explicitly assign a known role to an existing LDAP row. Sync
never promotes roles and never converts local name collisions. The last usable
local recovery Admin is protected independently of any LDAP Admin count.

For V2–V4, current source/role/hash are checked in the same read snapshot or writer
transaction. Explicit V5 additionally binds active status and integer auth
generation throughout provider/session/reader/writer. Reactivation does not revive
old authority. The business-Admin writer context requires an exact source plus
64-hex fingerprint; null-pair compatibility cannot authorize an LDAP actor.

## Shared application composition

After closed setup, FreshWeb can compose the exact admitted V4 or NEW V5 runtime
with LDAP login, separately admitted sync, and separately admitted terminal roots
in one Application. V5 is not aliased to V4. Its canonical cohort/manifest,
shared login budget, local recovery eligibility and generation-aware writers
remain mandatory. LDAP login and the persisted LDAP intake flag are independent.
When login is selected, the existing local-Admin LDAP session-revocation workflow
is mounted for all three known target roles, with its existing confirmation CAS.

Terminal HTTP additionally requires `existing_keys_enabled=true` and its exact
root profile. FreshWeb freezes all terminal metadata and checks the bound terminal
profile before web session middleware. The terminal profile also rechecks a
selected FreshWeb profile before protocol dispatch/key use, including a cached
backend. No keys, device rows, native fallback or new public authorization are
inferred by enabling the other channel. See [terminal admission](existing-terminal-root-admission.md).

Cold profile changes require stopped/drained workers, uncached configuration and
routes, and closed setup (`setup_enabled=false` plus the existing closure marker).
The marker alone does not prove a real recovery login. No local/new account,
secret, descriptor, host setting or running instance is created by this patch.

The integrated [V5 session-lock contract](v5-nonexpiring-session-locks.md)
remains unchanged: explicit V5 uses the exact stock FileStore, global lock zero
(non-expiring), positive wait and no route-level lock/wait override; V4 keeps its
positive lease. The guard still rejects overrides before session middleware.
No timeout now purports to bound a directory operation. Crash recovery stays
fail-closed and requires the separately documented global stopped-maintenance
process; this composition does not clear locks or infer operator stop proof.
These source guarantees are not an actual production concurrency/deployment pass.

## Bounded evidence and limits

Two coarse synthetic journeys on PHP 8.5.12 / Laravel 13.38.0 exercise V4 and V5:
stock session/source/hash/generation refresh, all three LDAP roles through fake
credential verification, direct-team review/self-denial, business facades and
writers, actual reader/controller/Blade separation, explicit local role assignment,
last-local-Admin retention and all-role LDAP hash revocation. A second standard
provider boot uses one disposable cohort for local recovery/shared-budget login,
pinned fake sync collection through the actual root/cipher/writer, target tickets
and a signed terminal heartbeat; config drift rejects before terminal mutation.
Only the external collector is substituted in the sync journey. All fixtures are
owned synthetic temporary data and removed afterwards.

Existing official-extension/TLS and import/cipher interop evidence remains
separate. No real LDAP server, device, browser, HTTP/cookie integration,
concurrent request/crash recovery, production root/account or deployment is
claimed. The user performs browser acceptance; real directory rollout remains
its separately authorized gate (#63). Remote group/password changes are not
polled inside sessions. Local hash revocation is not directory deactivation;
V5 local lifecycle and terminal-generation rules retain their distinct contracts.
