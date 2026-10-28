# FreshWeb: local Admin plus existing ordinary LDAP login (default off)

This narrow source composition reuses the existing **stock SessionGuard** and
`ExistingAccountUserProvider`; it is not a new guard/provider, LDAP sync,
provisioning endpoint, native session bridge or activated directory. No source
changes to those auth/guard/read/write contracts, Setup, UI, schema, API, key
sources or sync settings are part of this packet. Defaults remain local-only.

## Explicit trusted login metadata

The existing `chrono_ldap` config gains only four explicit operator inputs:

| Environment input | Default |
| --- | --- |
| `FRAMEWORK_CHRONO_LDAP_LOGIN_ENABLED` | boolean false |
| `FRAMEWORK_CHRONO_LDAP_LOGIN_URI` | null |
| `FRAMEWORK_CHRONO_LDAP_LOGIN_BASE_DN` | null |
| `FRAMEWORK_CHRONO_LDAP_LOGIN_CA_FILE` | null |

These are **login-only** operator settings, never request fields, admin-sync
settings, discovery from the DB/environment, service-bind credentials or a key
admission. No actual values are supplied/changed by this source packet. No
fallback from native LDAP config, sync target/CA, encryption.key, bind password or
browser data is installed. Config loading performs no network/CA/extension work.
An enabled FreshWeb profile reads the strict boolean before auth resolution;
disabled LDAP metadata does not require any directory or CA validation.

Enabled login metadata must be exactly those four config keys, with nonempty
scalar strings, one explicit `ldap://host:port` URI, a fixed base DN, and a
canonical absolute CA-path spelling. The unchanged verifier supports mandatory
verified **StartTLS**, not LDAPS, AD/UPN discovery, failover or arbitrary mapping.
This composition checks syntax only. It deliberately does NOT resolve/open/stat
the CA, test the optional LDAP extension, bind, search or probe liveness at boot.
Missing extension/CA/TLS/network failure matters only when an actual LDAP user's
password is verified; local Admin login does not contact the directory.

## Separate setup mode and cold profile selection

First complete ordinary Eloquent-local setup and verify normal local-Admin login.
With workers stopped/drained, close setup through the existing monotone operator
flow and remove the separate Fresh setup opt-in. Only then select the LDAP login
profile. `chrono_fresh_web.setup_enabled=true` and LDAP login together are
refused. The fixed `setup-closed` marker must exist under the already-admitted
private instance; any existence closes setup, including wrong type/symlink.
Its removal is refused on subsequent profile admission as well. The marker
proves only closed mode, NOT that an Admin was actually created; no account query
or credential check is manufactured at boot. The operator's prior ordinary
local-Admin check is a separate mandatory operational gate.

`FirstAdminSetup::tokenDigest` and all first-admin guards remain byte-identical:
they are not loosened to accept a directory provider. Closed/disabled setup does
not become an alternative account-creation route. No automatic source conversion,
reset/reopen, secret receipt read, login or role assignment occurs here.

FreshWeb still requires the exact stock initial auth config. Before any auth,
hash, session, cache or limiter resolution it selects only
`['driver'=>'chrono-existing','model'=>LegacyUser::class]` when the login flag is
true; otherwise it retains Eloquent. The already-registered lazy auth factory
constructs the existing wrapper and verifier without directory I/O. Per-request
admission snapshots the exact selected provider/settings and closure state. No
`forgetGuards`, provider replacement after resolution, session reset, limiter
reset, new cookie/token or live config mutation is used to switch profiles.
Use ordinary request lifetime with uncached routes/config, as required by
[FreshWeb composition](fresh-web-composition.md). Do not alter existing sessions
or operating settings as a side effect of source preparation.

## Existing account and permission semantics retained

- Existing local user/supervisor/Admin accounts keep their normal local hasher,
  remember=false and disabled automatic rehash. A local password rejection never
  falls through to LDAP. Directory availability is not a local break-glass probe.
- Only an already-persisted `auth_source=ldap`, `role=user` account is eligible
  through the wrapper. LDAP supervisor/Admin roles are refused, not demoted or
  relabelled. Never select the broader direct `ExistingLdapUserProvider` instead.
- Unknown users are not provisioned. Matching local names are never converted,
  linked or claimed by LDAP. Username equality is not identity-link authority.
- LDAP credentials are never checked against/stored as a local password hash;
  operational LDAP errors remain the existing unconfirmed503, not local fallback
  or confirmed bad credentials. No remember-me or credential output is added.
- Existing source marker and stock password_hash_web middleware bind sessions to
  current source/role/DB-hash. Reader and own attendance/absence/correction writer
  checks remain same-snapshot/same-lock; LDAP review/Admin/model/snapshot/terminal
  rights are NOT broadened. Local Admin's policy/manual/registration rights remain
  local and the authoritative V4 intake gates stay intact.
- LDAP **login is independent** of the `ldap_sync` intake flag. Disabling sync
  does not silently disable existing LDAP login; enabling login does not admit
  sync/transport/keys or new users. Manual and ordinary local registration may
  coexist under their separately persisted policy flags; no UI or writer changed.

## Explicit unresolved session/deactivation gates

The existing Laravel LDAP login is read-only and **does not rotate** the random
DB placeholder on successful login. Native login does, producing a Last-Login-
Wins effect for its hash-bound sessions/JWTs. This packet neither reproduces that
side effect nor quietly calls it identical. It leaves both
[#29 stable auth version / deliberate single-session policy](https://github.com/storminator89/Zeiterfassung/issues/29)
and [#30 central account deactivation](https://github.com/storminator89/Zeiterfassung/issues/30)
OPEN. Current source/request role/source/DB-hash revocation does not mean a remote
password/group change immediately revokes a session, or that changing a local
placeholder/password centrally disables future LDAP login. No invented active
field, schema/auth-version patch, periodic bind or fake approval toggle is added.

Real activation requires separate approval of this scoped profile's lifecycle
limits or the reviewed corresponding lifecycle implementation, plus real
LDAP/TLS/CA/worker and protected browser acceptance. It is not native/V4 cutover,
API/JWT/terminal revocation or production readiness. No issue is closed by this
small composition packet; no real account/directory/key/setting/host is touched.

## Bounded evidence

Syntax, exact four-path/reverse preservation and one coarse pure metadata plus
invented model/fake-hasher/fake-directory contract journey are appropriate here.
They do not authenticate anyone or open an account DB, resolve an actual guard,
hash a real password, invoke LDAP, start a listener, inspect a CA/key/receipt,
or enter browser credentials. No real login/LDAP/HTTP/session/CSRF/cutover/parallel-
session/remote-deactivation claim follows. Other source packets (including
Denial3 routes and AppKey4) still need separate serial mapping/review; #197 and
all operational/browser/native gates stay unchanged.
