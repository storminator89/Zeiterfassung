# Protected first local administrator — source, not provisioning

A separate `setup.php` form creates only the first local administrator of an
**empty** admitted installation. It is not ordinary self-registration, an account
reset or an automatic privilege grant. Native `register.php`/`APP_SETUP_TOKEN`,
ordinary registration, LDAP admission, existing accounts and schema remain exact.

## Simple first-installation administrator preset

The first-admin form now starts with the editable username **`admin`**. This is
only a visible form preset, not a seeded account. The user must choose and confirm
their own password during initial setup, before any account can be created. No
shared/default password, automatic sign-in or password-change exemption is added.
The existing server-side password rules remain authoritative. The normal login,
registration and existing-account settings forms are unchanged.

This source-only simplification creates no account or credential, changes no
existing installation, and does not enable setup or start a NEW-V5 instance. The
operator authorization below remains required; the preset is not proof that the
browser belongs to the installation owner.

## Local NEW-V5 alternative: no setup token

The explicit interactive `chrono:first-admin --instance=... --new-only --stopped`
operator command collects `admin` (editable), email and a hidden chosen password
plus confirmation directly in the operator's terminal. It admits only the
canonical empty private NEW V5 cohort, uses the existing first-admin writer,
then writes the monotone setup closure marker after confirmed COMMIT. It neither
generates a token nor enables browser setup, starts a server or resets an existing
account. The App-key and any selected other keys must be prepared while empty,
before this command. See [local first-administrator setup](local-first-admin.md).

The sections below describe the unchanged **alternative browser-token path**;
its token is not required by the local command.

## Explicit browser operator authorization

`chrono_setup.php` ships as `enabled=false`, `token_sha256=null`. No environment
variable, native setup token, signing/encryption key or request is inherited.
Only separately authorized trusted provisioning may supply the lowercase SHA256
digest of a cryptographically unpredictable operator-owned setup token and enable
this profile. The source neither generates a live token nor delivers one to a
browser. An admitted transient AttendanceWriter and persistent session/cache
composition must already exist; only then explicitly call
`LocalWebRoutes::register(..., includeSetup: true)`.

The controller/service require the enabled profile, exact 64-hex digest,
`APP_DEBUG=false`, configured bcrypt hasher and the stock local user provider.
Disabled/malformed/foreign-auth profiles fail closed with 404. No DB is opened
by this pure check. GET exposes no user count, token/digest or account metadata.
The existing login card shows its setup link only when the enabled named setup
route is actually present. Default application routes remain inactive.

The user submits an operator-provisioned token through the form **body**, never a
URL. Only 32–256 printable non-space ASCII bytes are accepted. The service compares
SHA256(submitted token) with the configured digest using `hash_equals`, before
password hashing or writer resolution. The token must be provided through a
supported private credential flow; no token/password in chat, shell arguments,
logs, screenshots read by the model or ordinary automation input. There is no
public token display, query-token path, known/default token, public first-visitor
admin switch or preauthenticated/session-injection fallback.

## Existing protections, one fixed transaction

GET/POST use the existing encrypted session stack and `LegacyFormCsrf`; raw
setup fields are not globally trimmed/coerced. POST has its own persistent
`chrono-web-setup` limiter: 5 attempts per IP in 15 minutes and 20 total per cache
namespace per hour, including normal retry headers. Identity/token/password do
not appear in counter keys. Setup composition must use the same final persistent
cache as this limiter; any later cache replacement must rebind its definition.
The current fixture router/launcher do **not** activate setup in this package.
These limits are not a production concurrency, proxy or abuse-capacity proof.

Only username, email, password, password confirmation and setup token are
accepted. Username/email are trimmed explicitly; passwords never are. Username
is 1–100 UTF-8 bytes without controls; email syntax/254-byte maximum; password is
12–72 bytes without controls, confirmation must match, and the existing refused
`defaultpassword` is rejected. Bcrypt hashing uses the configured Laravel hasher
before the SQLite lock. Password length is validated server-side in bytes; the
new form does not impose a conflicting HTML character-based password length.
No submitted/old values are rendered or flashed. `setup_token` is added only to
the existing exception-handler `dontFlash` list; logging/handler policy is not
otherwise changed. No email is sent or verification implied.

`AttendanceWriter::firstLocalAdministrator` uses its unchanged dedicated
IMMEDIATE/5000/single-attempt lifecycle. Inside the acquired writer lock it
rechecks **COUNT(all users)=0**, not zero admins. Any existing account, including
an ordinary or LDAP account with no administrator, refuses this setup operation
without changing data. Recovering such an installation is a separate authorized
operation, not permission to take it over through this page.

The one parameterized INSERT fixes `role=admin`, `auth_source=local`, NULL
token/department/supervisor, 8 daily hours and zero carry-in. Request values cannot
choose any of these. Existing initial-model/audit triggers run in this same
transaction. Fixed audit actor/reason: `setup:first-local-admin`,
`First administrator setup`; no fabricated current-admin identity, raw secret or
hash audit. Late failure follows the existing rollback/commit-uncertainty rules;
there is no automatic retry or new claim that a failed confirmation rolled back.

Accepted initialization and an already nonempty installation produce the same
303 redirect/generic login notice after a valid token, not a user/role listing.
Invalid token is generic 403, malformed form 400, infrastructure failure generic
503/unconfirmed. No autologin, native session bridge, identity injection, JWT or
remember-me action occurs. The administrator must use normal login afterwards.

## Required closing and provisioning gates

**Disable the setup profile and remove its operator token immediately after
confirmed setup.** COUNT(users)=0 is a current-database guard, not a durable
consumed-token ledger. If all users are later deleted or an empty DB is restored
while the profile/token is still admitted, the guard could become true again.
No irreversible one-use, restore, recovery, token-rotation or multi-node-consumption
claim is made; durable consumption would need a separately reviewed schema and
restore policy. The native setup has not been broadened to solve this.

A complete fresh installation, empty-runtime admission, secure token creation/
delivery, same-cloud-browser user handoff and closing the setup configuration are
**not integrated or executed** by this packet. In particular iOS browser takeover
does not prove native-terminal access, and a user cannot enter an unknown token.
BrowserAuth can securely collect a user-known token/password for the visible
form; it cannot provision an unknown token. No cloud-shell/credential workaround
is supplied. Actual first-admin creation requires the applicable user-specific
action-time confirmation and supported private provisioning/handoff first.

## Bounded evidence

One invented disposable SQLite empty-state journey covers token refusal before
writer resolution, late-audit full rollback, one successful first-admin creation
with trigger model/audit and a repeated/nonempty attempt without another account.
Only fixed invented proof inputs are used; no application boot, listener, real
setup token, persistent user access, session, credential handoff or browser login.
Source/PHP/compiled-Blade and three-locale checks supplement it, not a full suite,
HTTP/mobile/TLS/deployment or operational acceptance.
