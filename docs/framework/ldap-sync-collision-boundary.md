# Laravel LDAP sync: local-account collision boundary

This is an inactive preparation for the Laravel migration, not an enabled LDAP
sync endpoint or a native import-policy change. Base: Main
`b5558c36b64c9491ddec26800a0588952d32316a`. No real directory, bind credentials,
accounts, configuration, keys, routes, providers or database schema are changed.

## One admitted operation

`MaterializedLdapImport::fromArray()` accepts only the existing materialized
`entries/host/port/bind_user/ciphertext/base_dn` shape. It accepts the native
canonical `v2:` envelope shape, not a raw bind-password field. This is not proof
of successful transport or authenticated ciphertext. It performs no connection,
LDAP call, encryption, decryption, key lookup or account query. A later trusted
transport must complete TLS, bind, the full successful search and cleanup before
constructing the stage; no network or cleanup may run under the writer lock.

`AttendanceWriter::importLdapUsers()` reuses the existing admitted operation-scoped
writer and IMMEDIATE/single-attempt transaction. It reloads the server-derived
actor ID inside that lock and requires a current **local admin**. It then checks
the complete eligible UID set against current `users` before any settings,
department, identity, model or audit write. Any matching account with a source
other than exactly `ldap` rejects the entire stage with a fixed, identity-free
domain error. This protects every local account, including the importing and last
local admin. It does not skip individual collisions and report partial success.
Neither an earlier preview nor an earlier role/source check authorizes the write.

Only after this check does the unchanged `chrono_ldap_import_locked()` run on
the same PDO. Settings, identities, departments, manager plan, work models and
existing trigger audits commit or roll back together. The audit actor is fixed
from the trusted admin ID (`web:<id>`), reason `LDAP synchronization`; no request
audit actor or credential is added. Commit/rollback uncertainty is still an
unconfirmed failure, not permission to retry or promise a restored state.

## Preserved and deliberately different behavior

- Existing directory-managed usernames can still be updated; new usernames get
  the native ordinary `user` role and random non-reusable local placeholder hash.
- Username comparison is the exact native SQLite lookup: no case folding,
  trimming, email linking, DN guessing or silent local-account conversion.
- Native missing-uid/mail skips, first attribute values, repeated UIDs and their
  ordering remain. Manager conflicts remain nonfatal and preserve affected old
  edges, as documented in [the native import contract](../LDAP-MANAGER-MAPPING.md).
- This boundary neither promotes directory roles nor repairs old LDAP-managed
  admins, missing people, deactivation or existing relationship problems.
- **Intentional Laravel policy difference:** local/unknown-source collisions
  refuse the whole import. Native `admin.php`, `login.php` and
  `lib/ldap_import.php` remain byte-for-byte unchanged and still have their
  documented legacy conversion risk. This new method must not be described as
  protecting those native paths or as authorizing their use for recovery.

## Still required before an endpoint or activation

A trusted completed transport, guarded Laravel admin service/controller and
CSRF/throttle/session stack remain separate work. So do private configuration
storage, admin-selected intake policy, explicit identity linking, local recovery,
directory lifecycle/revocation, actual HTTP/browser acceptance and deployment.
The existing ordinary-LDAP login provider is not a sync/provisioning endpoint.
No new dependency, PHP extension, mail service, host or trust change is introduced.

One coarse invented disposable SQLite journey accompanies this source packet:
local collisions including the admin, current actor revocation, collision-free
import and a late-trigger failure. This is not an LDAP protocol, real-data,
configuration, login, browser, parallel-load or production acceptance claim.
