# Prepared local V5 Admin capability — Runtime remains closed

Source basis: Main `d5dc0a7a997bff5a4379f7e9a8f138619c65bc1a`, tree
`33a8ca32c1b57deb8c96be096ef98d5376a06301`. This closes one missing source
connection in the existing prepared Admin flow. It is not an operating
admission, installation, migration, credential provisioner or Runtime activation.

## Existing capability, not another lifecycle implementation

The already reviewed `LocalWebAccountLifecycle` interface, its
`LegacyLocalWebAccountLifecycle` implementation, Admin controller, confirmation
dialog and locked writer were present, but no application provider bound the
optional interface. Consequently the existing Admin controls would still be
absent even after a future separately approved complete V5 Runtime admission.

`FreshWebServiceProvider` now prepares that one scoped binding only for its
explicit local V5 composition and only **after** the existing
`LocalWebRoutes::register()` has returned. Its original V5 Runtime check is
unchanged and still throws before any V5 Web route or this binding is installed.
Default-off and V4 compositions do not bind the interface. A pre-existing V5
binding is refused rather than overwritten. No new profile/configuration switch,
route, controller action, UI, policy, schema, source opener or service is added.

The factory rechecks the existing immutable V5 Runtime and generation-aware
Web identity. It constructs the existing facade with the current Auth factory,
named database manager and Encrypter. Its writer closure resolves a fresh
`AttendanceWriter` only when the existing validated command calls it, never
during binding or facade construction. The named read and write lifetimes remain
separate. No singleton writer, raw PDO callback, new event or automatic retry.

The facade still checks the current local Admin and captured generation; its
bounded current-target snapshot, purpose/actor/version/generation-bound expiring
confirmation, existing CAS, audit, local-recovery invariant, uncertain-outcome
handling and confirmed redirect/self-logout remain authoritative and unchanged.
The existing controller's optional-capability predicate is unchanged. Other
profiles and explicitly injected component compositions retain their contracts.

## Evidence boundaries

The existing credential-free V4/held-V5 FreshWeb provider journeys are reused.
They must retain ordinary V4 routing, the exact V5 Runtime refusal before routes,
no resolved writer/database-manager connection, no lifecycle binding, unchanged
source/manifest bytes and no token/key/account artifacts. Nonlocal/incomplete V5
metadata remains refused; default application boot remains unbound.

A separate bounded component check may construct only the actual scoped factory
with public synthetic metadata: same instance within a scope, a new instance
after scoped cleanup, no writer/PDO resolution, and refusal with older-profile
identity. It is **not** a successful V5 provider/gate/HTTP admission. Existing
facade/controller/writer component evidence remains in
[`account-lifecycle-v5-admin-flow.md`](account-lifecycle-v5-admin-flow.md); those
unchanged business paths are not reimplemented by this connection.

No reflection/private opener, rewritten Runtime hold, alternate admitted source,
secret generation, real account, Native bootstrap, LDAP/API/terminal activation,
host/security setting, deployment or production data is used or changed.
PHP/Composer/source preservation and cold transport checks are source evidence,
not real login/session/CSRF/browser or recovery acceptance.

## Still required before any V5 operating decision

1. Native refusal before keys/session/DDL and a known-good Laravel replacement.
2. Complete physical local V5 Runtime/FreshWeb/provisioning composition, keeping
   LDAP/API/terminal capabilities separately closed unless explicitly admitted.
3. A proven usable local recovery Admin, matching private DB/key backups and an
   authorized stopped restore/re-admission procedure. A structurally valid hash
   or remaining Admin row does not prove that a person can log in or restore it.
4. Real setup/login/session/CSRF/browser, filesystem/worker ceilings,
   concurrency, lost-COMMIT/crash/restore and human recovery acceptance.

The existing Runtime hold must not be removed in isolation. #30/#82 remain open.
