# #29: explicit local LDAP session revocation — default-off Admin source action

Source anchor: `a0af8524830b79c1ecaf2b423ac7626c16734a9a`.
This is a small Laravel source workflow in the existing Quodara-Chrono Admin
page, **not account deactivation, directory administration or production
activation**. #29 browser/real-revocation acceptance and all of #30 remain OPEN.

## Stable login, deliberately scoped revocation

The unchanged `ExistingAccountUserProvider` delegates persisted ordinary LDAP
users to `ExistingLdapUserProvider`. Successful LDAP login and forced rehash do
not save a directory password or rotate the persisted random placeholder. Two
Laravel sessions therefore retain stock `SessionGuard` / `AuthenticateSession`
bindings across another successful login. There is no new provider, guard,
session engine or auth-version schema. Native `login.php` remains unchanged and
still rotates its placeholder on login. Concurrent Native/Laravel operation and
Native cutover are not approved by this packet.

The optional action is visibly named **“LDAP-Sitzungen beenden”**, never “disable
account.” A later valid LDAP login remains possible. Remote password/group
changes are not polled during an existing session. Terminal access, credentials
and grants are independent and remain unchanged.

## Explicit source capability, protected existing UI

`LocalWebRoutes::register(..., includeLdapSessionRevocations: true)` requires its
existing Admin page, non-debug mode, the explicitly admitted writable V4 runtime
and writer. It installs only a lazy scoped facade; route registration opens no
DB, directory or key file. A second/replacement facade is refused. The separate
`Interactions(..., ldapSessionRevocations: true, adminPauseSettings: true)` display
capability must also be selected. Both flags default false; FreshWeb does not
select either automatically. No config/env default, listener, account, key,
host/security setting or native route is created or activated.

The action shares existing `POST admin.php` and its complete unchanged protected
stack: raw native form handling, Laravel session/cookies, `LegacyFormCsrf`,
identity refresh, `auth:web`, stock `auth.session`, and configured session blocking.
The controller requires V4, both capabilities and the facade. Mixed Admin
actions/extra fields are rejected before dispatch. Manual/LDAP-intake flags do
not gate revoking an already-persisted account's local sessions.

The existing ten-row table, navigation, colors, typography and dialog patterns
are retained. Only eligible ordinary LDAP rows gain a checkbox naming the row
and a native-style action. The checkbox is required server-side as exact `1`;
JavaScript additionally asks for explicit confirmation and blocks repeated
submissions. Cancellation performs no request. Without JavaScript, the explicit
required checkbox remains the deliberate confirmation. All labels and ticket
attributes are Blade-escaped. No source/password/token column is added to the
existing `get_user.php` projection.

## Short-lived confirmation and same-lock compare

`confirmations()` accepts only at most ten server-projected integer IDs. On the
already-admitted named read connection, one fixed snapshot rechecks the local
Admin's current source, role and password fingerprint before selecting eligible
`auth_source=ldap`, `role=user` targets with nonempty placeholders. It returns
only opaque tickets keyed by ID, never hashes/fingerprints or account models.
The snapshot closes before standard Laravel authenticated encryption; there is
no new crypto protocol, key source or secret file. Tickets use the existing
framework encrypter, a fixed purpose, actor and target IDs/versions and a ten-minute
expiry. They are not login/session/terminal credentials and are not saved in the
session or logs. The existing private no-store/nonce-CSP Admin document protects
the form. A source/key/clock change invalidates outstanding forms; reload rather
than retry or infer an old result.

The POST has exactly four scalar strings (plus framework/native CSRF fields):
`revoke_ldap_sessions=1`, canonical positive `user_id`, opaque `confirmation`,
and `confirm=1`. Actor/source/role/hash are never request authority. Malformed,
expired, tampered, different-actor or different-target tickets cannot resolve a
writer. Under the writer's **one existing IMMEDIATE transaction**, its existing
principal check revalidates current local Admin source/role/hash. It then
revalidates the target and compares its current placeholder fingerprint with the
confirmed target version. Only then does it replace `users.password` with a hash
of fresh inaccessible random material. It neither sets nor changes any directory
password. No target name, role, source, identifier, key, setting, relationship,
terminal state or historical row is changed. Existing user audit triggers add
one credential-redacted update with fixed actor/reason; no manual audit insert.

A replay of the same confirmation after a committed rotation fails **409** before
another random value/update/audit. Concurrent confirmations from the old version
therefore cannot rotate twice. This is compare-and-swap protection, not a global
idempotency receipt or proof of a previously lost response. A newly loaded page
can deliberately authorize another rotation. An uncertain commit never permits
automatic retry. Inspect the original result first; do not keep resubmitting.

The writer keeps its existing admission, no-reconnect/no-retry, COMMIT/ROLLBACK
and unusable-on-uncertainty lifecycle. On the next authoritative check, existing
Laravel sessions fail through the unchanged refresh/stock `AuthenticateSession`,
already-issued hash-bound legacy JWTs fail through `LegacyApiIdentity`, and old
LDAP writer contexts fail their existing same-lock principal check. An already
committed operation is not undone, and an open browser is not remotely pushed
out. Success uses no-store **303 PRG only after confirmed COMMIT**. Later response
failure is explicitly distinguished from mutation failure; no new ticket or
automatic retry is generated on the POST.

## Evidence and limits, 28 October 2026

PHP **8.5.12**, Laravel **13.38.0**. One coarse invented component journey uses
actual stock SessionGuards, two independent **array** session stores, a fake LDAP
verifier and a disposable adopted known-V2 SQLite fixture. It checks stable
parallel sessions, strict actor/form/ticket expiry/target validation, same-lock
stale Admin rejection, exactly one placeholder/audit change, replay rejection,
old session/JWT/writer denial and successful stable subsequent fake LDAP login.
All other user fields, schema, directory settings, terminal/history state and
original/backup disposable fixtures stay exact. No operational directory errors
fall through to local-password verification.

Two isolated capability profiles check route prerequisites/defaults, complete
protected middleware, unchanged raw form handling, actual CSRF middleware with
missing/wrong/valid synthetic tokens, and actual controller dispatch/extra-field
rejection/303 response with a counting facade. Their pure nonexistent V4
metadata is **not runtime DB admission**; the mutation journey's V2 fixture is
not a deployed V4/HTTP workflow. Changed PHP and compiled Blade syntax are checked.
An independent review covers the complete assembled source, not only the older
unbound backend.

Not performed: two real browsers/cookies/HTTPS, full HTTP kernel/V4-instance
admission, genuine LDAP/TLS/CA/password/group revocation, session files, real
accounts/keys/operating data, parallel request/commit-failure acceptance,
activation, GitHub writes/new CI, merge or deployment. These gates stay explicit.
A placeholder rotation is not the central activation/departure lifecycle in
[the separate #30 implementation scope](account-lifecycle-v5-scope.md).
