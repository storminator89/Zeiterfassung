# Inactive Laravel Admin LDAP sync orchestration

This source-only service slice is based on an explicitly **local prerequisite
composition**, not a claim that the policy core is already on Main:

- observed actual Main `e372faab83fb5eb0703af6cbece37858c719b09f`, tree
  `0af581032f705b804046800c81bbcbe18a9c2f2f`, already including the collision
  boundary and transport/cipher source;
- only additional prerequisite: corrected independently reviewed fresh V4 policy
  core `3e813d6e6e13471bcb3a0098bfda815be0aaa526`, not held `324eb231`.

No controller, route, provider, UI, config, key source, directory, account or
runtime admission is connected here. In particular this is not finished Admin
sync or effective global intake-policy enforcement. The separate fresh V4 profile
must be admitted before the new policy queries can succeed; existing V2/V3 fails
closed rather than using a config/session fallback.

## Service before any external work

`LegacyLocalWebLdapSync` implements `LocalWebLdapSync` without a default binding.
Its three zero-argument factories are trusted lazy operation composition, never
request fields. The normal Laravel session/CSRF/throttle stack remains mandatory
for a later controller. The server guard must supply a local Admin model and
nonempty standard DB password hash. Only the native six sync field names are
accepted, scalar strings, with exact flag `sync_ldap=1`. Service-bind DN is exact,
nonempty, control-NUL-free, at most 4096 bytes; bind password is nonempty, at most
65536 bytes and never trimmed/decoded/normalized. No target/key/actor callbacks
or policy decisions can be submitted.

On the existing idle named `chrono` read connection, one stock Laravel DEFERRED
snapshot reloads current source, role and password hash for the guard ID and
checks `IntakeMethod::LdapSync`. A stale/deleted/source-changed/password-changed or
nonadmin principal refuses before transport/key/writer resolution. This uses the
standard guard's existing DB hash as operation context, not a new credential,
auth-version column or session engine. The snapshot yields only private actor ID,
SHA256 hash context and policy revision. It is closed before any external work.

`LdapSyncTransport::target()` now returns the pure `LdapSyncTarget` from the same
immutable URI/BaseDN profile used by `collect()`. This reuses canonical target
validation without opening CA files or the directory. The three submitted
target fields must exactly repeat that admitted URI, canonical port text and
BaseDN; they never select another host or CA. The existing collector still owns
extension/CA/security-option checks, verified StartTLS, service bind, complete
bounded result and successful release/unbind before returning. Its previous
TLS/search/cleanup behavior is unchanged; URI shape validation has one pure owner.

Only after collection succeeds does the service resolve the existing-root cipher,
encrypt the newly supplied bind password in native v2 format and create the
materialized stage. Directory entries/passwords/roots are not returned, flashed,
serialized or placed in views. The result is only the native authorized manager
plan. Unexpected source/transport/SQL errors become one fixed unconfirmed error,
with no previous exception, retries or asserted rollback/success.

## Writer after slow transport

The new additive `AttendanceWriter::importLdapUsersWithIntakePolicy()` enters the
same existing IMMEDIATE/single-attempt/audit scope. Before any import write it:

1. reuses the existing `assertExpectedWebPrincipal` check for current local Admin
   and the guard-derived DB-hash context (fixed invalid-session 401 on change);
2. requires the current persisted LDAP-sync flag and **exact same revision**, even
   if a changed policy still allows LDAP;
3. checks the entire stage for local/unknown-source collisions.

Only then does the unchanged native locked importer execute. An admin/credential/
policy change while LDAP was in flight or a local collision refuses all settings,
department and account changes. Native transaction/audit/model/manager semantics
are retained. No nested import transaction or retry is introduced. The earlier
`importLdapUsers()` remains an inactive precursor without a policy contract and
must not be the endpoint path; this service exclusively calls the new method.

## Explicit remaining gates

The shared Writer also receives separate setup/terminal additions: integrate
serially against the then-current actual Main and independently review the
combined file. This packet must not publish prerequisites as if they were Main,
overwrite their source, bypass the PR #197 external-merge HOLD, or enter its Home
controller/document paths.

Fresh V4 runtime/admission, admin-controlled policy UI and **all three** intake
gate wiring, private source admission, endpoint/CA configuration, session/CSRF/
rate-limit/controller wiring, existing Admin UI, synthetic successful protocol
and HTTP/browser acceptance remain separate. No real LDAP, key, account,
configuration, application, listener, browser or remote action is performed here.
Supplied evidence uses only a single invented disposable lifecycle with a fake
server guard and completed transport, an invented in-process key, and the actual new service,
policy and admitted writer. It does not prove LDAP/TLS, a real login, deployed
cookies, production concurrency or end-to-end Admin sync.
