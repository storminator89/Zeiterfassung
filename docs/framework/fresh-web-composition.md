# Fresh Laravel web composition (explicit opt-in, not activation)

This source composes the existing Laravel UI with a **NEW-only** private V4
instance. It is not an old-data migration, installer, activation command, native
PHP bridge, or proof that a deployed installation works. Default is disabled.
It does not prepare files, produce/print/read raw setup tokens, generate/adopt
keys, create accounts, change disk settings/permissions, start servers, change
host/proxy/TLS settings, or enable a public API/terminal/LDAP transport.

## Required source and operator gates

The corrected policy core, full authoritative V4 intake gates, runtime Admission,
Fresh4 file creator, Setup/AuthC5, fixed setup-profile source, and exact public
asset packet must be independently reviewed **together on the final Main base**.
The old pre-gate V4 runtime lacks the mandatory immutable profile capability and
is refused before private instance reads. A method/capability check does not
replace the independent source review of the complete writer and all intake paths.
No `approved=true` config bypass is offered. No incomplete gate composition may
be activated or described as runtime-ready.

Native PHP must not be routed to the same V4 database. Only `framework/public`
may be exposed as the webroot; source/config/private files must not be served.
Native guard/cutover, protected browser acceptance, TLS/proxy trust, actual worker
request ceilings, filesystem lock behavior and deployment ownership are separate
operator/review gates. They are not established by this source or a successful
lint. Existing PHP-FPM/ordinary Laravel request lifetime is the initial supported
model; no Octane, RoadRunner or other long-lived application container claim.

## Trusted input contract

`config/chrono_fresh_web.php` only describes inputs; loading it opens nothing.
All inputs come from trusted operator configuration, never HTTP:

| Input | Default / requirement when enabled |
| --- | --- |
| `FRAMEWORK_FRESH_WEB_ENABLED` | exact boolean `false` |
| `FRAMEWORK_FRESH_INSTANCE_DIRECTORY` | absent; exact canonical directory returned by the NEW-only creator |
| `FRAMEWORK_FRESH_SETUP_ENABLED` | separate exact boolean `false` |
| `FRAMEWORK_FRESH_SESSION_LOCK_SECONDS` | absent; explicit positive integer/canonical decimal string |
| `FRAMEWORK_FRESH_SESSION_WAIT_SECONDS` | absent; explicit positive integer, no greater than lock TTL |

The existing separate `FRAMEWORK_CHRONO_READ_ENABLED` and
`FRAMEWORK_CHRONO_WRITE_ENABLED` must both explicitly be true. This profile does
not switch them on. It loads only the fixed private V4 admission manifest into
runtime configuration; RuntimeDatabase's existing file/schema/ledger guards and
the writer's current principal/profile/locked-policy checks remain authoritative.

A separately supplied valid `FRAMEWORK_APP_KEY` is mandatory (AES-256-CBC, no
previous keys in this fresh initial profile). This code never generates, saves,
prints, or reuses a native key. Production environment, debug false, stock local
Eloquent `LegacyUser` session authentication and bcrypt are required. Existing
LDAP login is independent of LDAP enrollment, but activating that separate auth
profile is not included here. Terminal HTTP ingress must remain disabled.
`APP_URL` must be a canonical lowercase ASCII-host HTTPS **root** origin (optional
nondefault port; no credentials, subpath, query, fragment, alias or HTTP fallback).
IPv6 origins are outside this initial narrow profile. Request scheme/host/port
must exactly match; no proxy trust or URL is inferred from request input.

## Private state and initialization

Fixed metadata is `<instance>/runtime-admission.json`, exactly the canonical
JSON encoding emitted by Fresh4, eleven V4 fields including the distinct
V2/V3/V4 signature chain. Database path must be exactly
`<instance>/application.sqlite`, with the admitted device/inode. Metadata reads
are bounded, regular 0600, single-link, owned by the runtime UID, with stable
lstat/fstat identity and whole-metadata hash. Canonical encoding rejects duplicate
JSON keys. No application database bytes/whole-file hash or PDO are read by the
composition guard; mutable business contents remain behind RuntimeDatabase.

The instance and existing `sessions`, `cache`, `locks`, `compiled` directories
must be canonical, writable 0700 directories owned by the runtime UID. No links
or aliases; every ancestor must be root/current-UID owned and non-group/other
writable except sticky ancestors. Paths must be outside checkout/public roots.
Fresh4's provisioning UID and runtime UID must match. No permission repair,
ownership transfer, mkdir, cleanup, lock deletion, session reset or file adoption
occurs on a web boot. Replaced instance directories/files fail closed.

FreshWebServiceProvider registers before LegacyRuntimeDatabaseServiceProvider
and sets final private paths **before** cache, RateLimiter, session, encrypter,
view/compiler or authentication resolution. Already-resolved/conflicting state
is refused; there is no late `forgetDriver`, cache/budget reset, replacement
RateLimiter, or alternate writer/PDO. Stock FileStore supplies persistent cache
and session locks; session data is encrypted JSON, Secure/HttpOnly/SameSite=Lax,
host-only/root-path/nonpartitioned cookies, with a per-instance nonsecret cookie
and cache namespace. Compiled views use the private existing directory.

**Lock TTL is not a proven request ceiling.** The operator must establish a real
bounded wall-clock request lifetime below the chosen lock TTL and a supported
single-host filesystem/process topology. PHP execution-time settings alone do
not prove a bound on blocking I/O. No test/config checkbox substitutes for actual
lock/concurrency/TLS/proxy acceptance. Web writes remain withheld until those
separate deployment gates and complete source prerequisites are accepted.

## Config/route caches are deliberately unsupported initially

Enabled FreshWeb refuses an existing configuration **or** route cache before
private reads. The current explicit LocalWebRoutes registrar establishes raw-form
callbacks, exception renderers and middleware priority in addition to routes;
skipping it for a route cache would lose behavior. This narrow packet does not
refactor that shared registrar or run cache commands. Do not run `config:cache`,
`route:cache` or `optimize` for this profile; generating a cache does not make it
supported and the next enabled boot refuses it.

Configuration caches are deployment state: merely changing an environment flag
never invalidates a cached configuration. Stopping/draining workers and removing
obsolete compiled state belongs to the separately authorized deployment process,
not this source. Fresh routes carry their own enabled/profile/HTTPS middleware
before cookies/session/auth, so an accidentally retained fresh route is closed
when the effective configuration is disabled. Normal default-off routes and the
old synthetic fixture are not registered/relabelled as this profile.

## Mounted routes and known boundary

The existing registrar retains CSRF, session blocking, local-account refresh,
auth:web/auth.session, role authorization, form parsing, event IDs and writer
contracts. Route matching additionally rejects encoded/trailing aliases before
session work. Setup checks use matched **controller identity**, covering every
router-accepted setup variant rather than only an un-decoded path string.

The profile mounts existing login/logout, attendance/status/transition,
corrections, absence transport, work models, history, supervisor, dashboard, CSV,
settings and admin pages. Registration is a display/route capability only; current
persisted policy determines availability and the locked writer still decides.
Setup is mounted only when its separate live setup window is initially valid.
Terminal-device listing and secret-bearing database download are deliberately
not mounted; native JWT API, terminal keys/protocol and LDAP key/transport
admission remain separate and disabled/unbound here.

**#197 remains separately HOLD.** No held attendance-alias delta is included or
repackaged. Existing `/save.php` with `action` still returns410; direct
id/update/delete still returns409. Normal attendance.js transitions already use
`attendance.php`; the existing pure absence form uses save.php with
urlaubStart/urlaubEnde/beschreibung. This packet does not claim complete native
URL parity, a completed Laravel migration, or runtime acceptance.

The six public shared assets are fixed-byte validated against the separately
reviewed existing-source asset packet (see `public-shared-assets.md` and root MIT
LICENSE). Local pinned Chart.js/FullCalendar admission remains in LocalWebRoutes.
AuthC5's stricter meta-CSP and inline auth style partial are unchanged. Dynamic
responses gain no-store/private, nosniff, no-referrer, frame denial and a minimal
non-script CSP only when no CSP header exists. Existing stricter CSP is never
replaced. Protected layouts still use external CSS/fonts and reviewed inline
scripts; neither fully CDN-free/offline UI nor strict script CSP is claimed.

## First-admin setup window

Web code only reads fixed digest metadata, never `operator-setup-token.txt`.
With separate setup opt-in, the fixed `setup-profile.json` has exactly version,
runtime_admission_sha256, token_sha256, expires_at. Its admission hash binds the
profile transitively to this exact admitted instance. The pure
`FirstAdminSetupFiles::configuration` API projects only enabled/token_sha256.
Any existence of `setup-closed` (including symlink/wrong type) disables setup
**before** profile reading. Closed, expired, missing or malformed setup metadata
disables only setup; ordinary login remains available.

Private instance/manifest integrity, closure and expiry are checked again before
**every** setup request and the existing setup service receives the refreshed
digest configuration. Setup is not governed by manual/LDAP/registration intake
flags. Its writer independently enforces the empty first-admin condition under
the existing operation lock. No new credentials, LDAP keys, native sessions or
API authentication methods are introduced.

Provision/close uses a separately reviewed local CLI with a persistent
`setup-operator.lock`; web code neither deletes it nor reads the raw receipt.
Operators must stop/drain web workers before prepare/close. Expiry/closure checks
are admission checks, not instant revocation of already-admitted in-flight POSTs,
and there is no backup-rewind resistance or automatic reopen/rotation claim.

## Evidence limits

This packet is source-only. Lint and a bounded direct-helper synthetic proof do
not establish actual application boot, private instance preparation, DB writes,
accounts/passwords/keys/tokens, HTTP/session/CSRF login, LDAP/network/host/TLS,
protected browser behavior, cutover, performance or deployment readiness. No such
operational action, CI run, publication or real setting change is performed.
