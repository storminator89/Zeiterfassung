# Laravel Docker operator contract — explicit preparation, not activation

The root Dockerfile and root Compose Web service are now the **Laravel standard**;
start with the [NEW local V5 standard installation](laravel-default-installation.md).
The detailed profiled compositions below remain explicit alternatives/operator
tools, not a native-data cutover, old-data importer or deployment acceptance.
The standard NEW-V5 path now includes post-setup [local API administration](api-admin-ui.md):
no API-key CLI or API overlay is needed for that path. The older explicitly
admitted V4/V5 API overlay/NEW-only producer below remains an alternative, not a
requirement for the standard UI flow.

The original native build is preserved as `deploy/native/Dockerfile`. Never
point both applications at the same database. Publishing this packet creates
**no instance, secret, account, listener, TLS certificate, host setting or service**.

## What is supplied

- `deploy/laravel/Dockerfile`: the same digest-pinned official PHP **8.5.12**
  Apache runtime already selected by the repository; Composer **2.10.3** is
  downloaded from its official release URL and verified by the published SHA256.
  Dependencies install from `framework/composer.lock`, not `update`. Only the
  maintained autoload/package-discovery hooks run. No seed/migration/key/cache
  optimization hook is added. A build needs registry, Debian package, Composer
  and locked dependency downloads; no build credentials are requested.
- The Dockerfile-specific ignore file excludes local vendor, `.env*`, runtime
  caches/storage, databases, keys, receipts and unrelated native entrypoints.
  The image contains framework/public assets and the existing nonpublic
  `lib/`, `languages/`, schema map and LICENSE needed by the adapters. Only
  `/opt/chrono/framework/public` is served; `/opt/chrono` is denied.
- The explicit `deploy/laravel/Dockerfile` image command is `php artisan list --raw`, **not a web server**. The root image instead defaults to the same fail-closed Web entrypoint used by Web Compose; no automatic provisioning.
  `compose.yaml` contains only the profiled one-off operator, no ports/network
  and no web dependency. Listing commands does not provision an installation.
- `compose.web.yaml` separately adds the web service. It requires a real TLS
  certificate/key and private App-Key environment file, and binds only
  `127.0.0.1:<explicit host port>`. There is no HTTP fallback, fake HTTPS,
  forwarded-header trust, public bind or automatic restart. The application
  still enforces its canonical HTTPS origin, exact selected instance identity,
  auth/session/CSRF and policy gates. Native endpoints are not copied into the webroot.

## Supported initial operator boundary

Use one **Linux Docker daemon on the same trusted host**, a local filesystem
with supported SQLite/flock semantics, and the **same non-root numeric UID/GID**
for preparation and every web process. The existing host parent must already be
canonical, operator-owned0700, outside the checkout/webroot, with the trusted
ancestor ownership/mode rules from [Fresh4](fresh-application-files.md). Compose
uses `create_host_path: false`; it never creates/chmods/chowns/adopts a host path.
Do not switch to root, relax guards or repair existing permissions on failure.
Rootless remapping, Docker Desktop sharing, NFS, remote volume drivers and
multiple web replicas have not been accepted by this packet.

The bind target is always `/var/lib/chrono-laravel`. **Prepare through this same
image/mount**, not a host PHP process: the admission manifest binds the container
absolute path/device/inode. The host source path and container target need not
have the same spelling. Never edit a manifest to compensate for a moved mount,
identity change or failed admission. Backup/restore/adoption are separate flows.

Docker daemon administrators, root and the same runtime UID are trusted. The
web worker can inherently read files owned by its UID; the private operator
receipt is not made a web resource. The App-Key is supplied with Compose's
`env_file`, not an image ARG, chat message, shell argument or inline Compose
value. **Docker inspection/config rendering can reveal that environment**; keep
daemon/config access private and never paste rendered config or inspect output.
This is not a new secret vault or guaranteed process-memory erasure.

## Phase 1 — build only (does not initialize data)

From the repository root:

```sh
docker build -f deploy/laravel/Dockerfile -t chrono-laravel:local .
```

The root Dockerfile also builds Laravel, with Web execution as its default.
The explicit `-f` image above remains command-only; its own ignore file is used
for that path, while root `Dockerfile.dockerignore` isolates the standard build. No real `.env`, volume,
TLS key or operator receipt belongs in the build context or source control.
There is no preconfigured/default account and no automated installation test.

## Phase 2 — explicit NEW-only preparation, web stopped

Only an authorized operator performs these commands. They change private local
files, so a source review/build is not permission to execute them on a user
installation. First create/choose a **new** private host parent and a separate
private config file outside the checkout using your normal trusted local
operator tooling. Start from `deploy/laravel/operator.env.example`; fill UID,
GID and `CHRONO_LARAVEL_PRIVATE_PARENT`. It contains **paths/settings only**.
Do not put any actual App-Key, setup token, password or TLS-key bytes in it.

For the following examples, set this to that existing config's absolute path:

```sh
OPERATOR_CONFIG=/absolute/private/laravel-operator.env
# No data/secret creation: show available commands, then exit.
docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml run --rm operator list --raw
# Explicitly creates one NEW empty instance, with metadata-only receipt:
docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml run --rm operator chrono:prepare-fresh --private-parent=/var/lib/chrono-laravel --new-only
```

Record the returned **basename only** (`chrono-fresh-` plus24hex) as
`CHRONO_LARAVEL_INSTANCE` in the private config. Do not guess it, reuse another
installation or retry an uncertain preparation automatically. The returned
`activated:false`, `credentials_created:false`, `server_started:false` are
boundaries, not a declaration that the UI is usable.

### Explicit producer alternatives; unflagged command retains V4

The unflagged operator command above retains the original V4 profile; this is
not the root standard installer, which now selects local browser V7 explicitly. For a separately reviewed
complete NEW-only V5 source composition, use the following **instead** of that
command, never both and never on an existing instance:

```sh
docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml run --rm operator chrono:prepare-fresh --private-parent=/var/lib/chrono-laravel --new-only --dormant-account-lifecycle-v5
```

Record only its exact returned basename. This prepares dormant files, not
Runtime activation, credentials, migration or source acceptance. A pre-NEW-V5
image still refuses through its original Runtime hold.

### Local NEW V7 installation with browser-only first registration

For the explicitly selected **local** first-user-Admin policy, the installation
process uses this single NEW preparation command instead of either command above:

```sh
docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml run --rm operator chrono:prepare-fresh --private-parent=/var/lib/chrono-laravel --new-only --local-browser-first-admin
```

It prepares canonical empty vacation-workflow V7 files and a bound nonsecret
`local-first-user-setup.json` policy. No account, password, token, key or server is
created. It cannot target/reopen an existing installation; do not add this file
by hand. The application user has **no terminal or token step**: after the normal
installation/key/TLS gates below, opening Login or Register leads directly to
the existing first-setup card. The first successful registration becomes Admin.
See [browser first-user setup](browser-first-user-admin.md) for the local-network
risk and the exact boundary; local reachability does not prove personal identity.

Before Web setup on an admitted NEW V5/V7 generation-bound source, explicitly set
`CHRONO_LARAVEL_SHARED_LOGIN_BUDGET_ENABLED=true` in the private **operator
configuration**, and keep it true afterwards. V5 requires the existing shared
budget even with API off. Web Compose passes this existing boolean selection;
default false preserves V4. False or a nonboolean does not bypass V5 admission.
Do not append flags to generated App/API key files. The operator-only base
service remains unchanged and does not inherit this Web opt-in. For NEW V5 also
set `CHRONO_LARAVEL_SESSION_LOCK_SECONDS=0` and choose a positive
`CHRONO_LARAVEL_SESSION_WAIT_SECONDS`. V5 requires the locked stock FileStore
non-expiring lease; positive V5 leases are refused. V4 keeps its original
positive lease contract. Before changing a running V5 image/configuration, stop
and drain all users and exclude restart; never mix old positive-lease workers
with new zero-lease workers. The source does not detect such an old process.
See [V5 session locking and stopped recovery](v5-nonexpiring-session-locks.md).

For the older explicit API-overlay alternative only (not the root standard UI flow):
do not use `compose.api.yaml` for first-Admin setup: that optional overlay
deliberately requires setup=false. If API is planned, prepare its separately
authorized NEW key while empty and **before** setup-token preparation; select
the API overlay only after deliberate setup closure.

With all web workers/requests stopped and drained, provision secrets only after
separate explicit authorization. The unchanged existing commands print no
secret bytes, and refuse existing secret files (no reset/rotation/reopen):

```sh
INSTANCE=/var/lib/chrono-laravel/chrono-fresh-REPLACE_WITH_RETURNED_BASENAME_SUFFIX
docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml run --rm operator chrono:application-key --instance="$INSTANCE" --new-only --stopped
```

**Only for the older explicit API-overlay alternative, with key creation separately authorized**, run the
original NEW API-key producer now, before any setup profile/token or user:

```sh
docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml run --rm operator chrono:api-key --instance="$INSTANCE" --new-only --stopped
```

For the standard NEW-V5 UI flow omit that command; API stays off until the post-setup Admin action. Complete any other selected NEW
key preparation while the installation is still empty. Then choose exactly one
first-administrator path:

### Recommended local NEW V5: first registration in the browser

For the NEW local-browser policy above, do not run `chrono:first-admin` or
`chrono:setup-token`. Prepare the already required App-key and any selected keys
while empty, keep API/LDAP/terminal off during first setup, and leave
`CHRONO_LARAVEL_SETUP_ENABLED=false`. Start Phase 4 only after its existing gates.
The canonical HTTPS origin must be localhost/`.localhost` or a loopback/RFC1918
IPv4 address, with a certificate already trusted by the browser; no public-origin,
HTTP or certificate-warning fallback is added.

The user opens the installed local address in the browser, accepts/edits `admin`,
enters email and chooses/confirms their own password. There is no token or
separate operator approval per browser. First successful commit creates the only
initial Admin and closes setup; subsequent users cannot become Admin through it.
An unknown result is not permission to repeat/reset. **Anyone able to reach an
unfinished local installation could be first**: complete it on a trusted computer
and network, never through public forwarding. Browser/device reachability still
has to be established for the actual installation; an iOS chat surface alone
does not prove access to the host's localhost address.

### Optional operator CLI alternative, no token

Keep all web workers stopped. In the operator's own interactive terminal run:

```sh
docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml run --rm operator chrono:first-admin --instance="$INSTANCE" --new-only --stopped
```

Accept or edit the prefilled `admin` username, enter the email, then choose and
confirm your own password. Both password prompts are hidden, with no visible
fallback. Never put credentials in command arguments, environment/config files,
chat, logs or automation input. Noninteractive/piped execution is refused.

This explicit local command admits only canonical empty NEW V5 files with an
already prepared private App-key, uses the existing first-admin transaction,
and closes setup after confirmed account creation. It creates **no setup token**,
opens no browser/listener, and never changes an existing account. On a confirmed
success receipt leave `CHRONO_LARAVEL_SETUP_ENABLED=false`; after the other
Phase 3 gates, start Phase 4 directly in closed-setup mode and use normal login.
The token-handoff gate and Phase 5 token closure do not apply to this path.
If anything is unconfirmed, keep web stopped and inspect locally; never rerun
automatically or delete `setup-closed`. See [local first-admin details](local-first-admin.md).

### Alternative browser setup (original V4/V5 token path)

Only for this alternative, provision the short setup token after the selected keys:

```sh
docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml run --rm operator chrono:setup-token prepare --instance="$INSTANCE" --stopped
```

The placeholder is not a valid instance; replace the whole `INSTANCE` value with
the exact returned container directory. `--stopped` asserts your prerequisite;
it does not stop/discover workers. Never run `key:generate`, seed/migrate,
`config:cache`, `route:cache`, `optimize`, an old-data initializer or a generic
native startup here. No target/reset/resume option is supplied.

## Phase 3 — independent gates BEFORE selecting the web profile

1. Final integrated source review must accept the selected V4 or NEW-only V5
   Runtime/writer/intake/FreshWeb composition. The budget setting does not remove
   a Runtime hold or admit another source profile. A build or `/up` is not that review.
2. Supply **existing, operator-managed** matching TLS files and a browser-trusted
   certificate for the chosen origin. This packet issues no certificate, bypasses
   no warning and changes no DNS/proxy/firewall/host configuration. Its narrow
   loopback binding is not public hosting. To use it from another machine requires
   a separately reviewed/authorized routing design, not changing the bind to0.0.0.0.
3. Fill canonical HTTPS root origin, explicit unused port and reviewed lock/wait
   bounds. The origin must be reachable by the intended browser and exactly
   match its actual scheme/host/port. No aliases/subpath/proxy assumptions.
4. Confirm the supported single-host lock/SQLite behavior. **V4 retains its
   positive lease and requires a real maximum whole-request wall-clock lifetime
   below that TTL.** Apache/PHP I/O/CPU limits do not prove it. **NEW V5 instead
   requires exactly0 for the stock non-expiring FileStore session lease**, plus a
   positive wait. It cannot expire while an old request is still executing;
   per-route overrides are refused before session work. A worker exit without
   release intentionally leaves the session blocked. Accept and rehearse the
   verified-stopped recovery procedure below; never automatically clear a lock
   because a wait timed out. There is no new watchdog or production acceptance.
5. **Only for the alternative browser setup**, establish the supported
   **private operator-token handoff** before creating an
   administrator. The token is only in private `operator-setup-token.txt`0600,
   valid for3600seconds. The operator reads/enters it personally through an
   established private credential flow; no token in chat/URL/log/screenshot,
   automated browser fill, source response or server-side form injection.
   BrowserAuth can collect a **user-known** token/password but does not import
   this generated file. Cloud browser takeover does not itself prove private
   native terminal access. If that handoff is unavailable, **stop here**; this
   Docker source does not fix or bypass that product capability gap.

The empty/setup guard is not irreversible consumption across deleting users or
restoring an old snapshot. Preserve setup-closed with complete private backups.
Actual setup, normal login, browser JavaScript/Secure-cookie/CSRF, protected UI,
interruption/retry, concurrency, closure and restore acceptance remain operational
work, not something performed by preparing this packet.

## Phase 4 — deliberately start the separate TLS web candidate

Only after the gates above, keep `CHRONO_LARAVEL_SETUP_ENABLED=false` for the
NEW local first-user browser profile, or after successful `chrono:first-admin`
setup. Only the alternative token-browser path
sets it to `true` for its short first-admin window. The key env file is loaded only by the additional web compose
file; the base operator never loads it. Start just this candidate explicitly:

```sh
docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml -f deploy/laravel/compose.web.yaml up -d web
```

The entrypoint refuses root, missing key/TLS/instance, config/route caches and
an impossible setup/closed selection before Apache starts. It never repairs
files, opens an alternate HTTP listener, creates a database/account or runs a
provisioner. Remaining deep admission is enforced by FreshWeb on requests.
There is **no automatic healthcheck/readiness assertion**; a running container
or `/up` does not mean setup/login or a usable installation succeeded.

For a NEW local first-user browser profile, open `/login.php` or `/register.php`;
the existing setup card appears automatically, without a token/terminal step.
After successful registration, use normal password login. For the optional
operator CLI path, open the exact configured HTTPS origin's `/login.php` and
sign in with the username and own password just chosen; there is no setup token
to enter or change. For the alternative browser path, open the origin's
`/setup.php` through the supported
private flow. The user chooses their password privately; submit only with the
applicable account-creation authorization. Confirm normal password login after
setup; no autologin/session injection. If anything fails or is uncertain, do not
regenerate tokens, reset the instance or assume a database operation rolled back.

## Phase 5 — close setup and return to normal local-admin mode

The local browser-first-user and `chrono:first-admin` paths close setup
automatically after confirmed account creation; do not prepare a token
or reopen setup. Keep its closure marker and use normal password login. The
following stop/close sequence is only for the alternative browser-token path.

Stop/drain **all** requests and verify workers exited; a stop timeout/SIGKILL is
not proof every operation committed or rolled back:

```sh
docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml -f deploy/laravel/compose.web.yaml stop web
# After confirmed normal admin login and confirmed worker shutdown:
docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml run --rm operator chrono:setup-token close --instance="$INSTANCE" --stopped
```

Require confirmed closure, keep the monotone marker even on partial cleanup, and
set `CHRONO_LARAVEL_SETUP_ENABLED=false` before recreating the web container with
the Phase4 command. The entrypoint refuses missing closure unless the separately
selected NEW local-browser policy exists; that policy is deeply rechecked by
FreshWeb. A marker alone is still **not** proof an admin exists. No key rotation,
backup deletion, database adoption or native-host switch occurs. If closure is
unconfirmed, keep web stopped and inspect locally.

LDAP, JWT API, device protocol/keys and shared-login-budget selection remain
separate, default-off capabilities; this local-admin installation does not
configure/test/activate them. The existing per-file login/setup throttles remain,
not a new capacity or reverse-proxy acceptance claim.

## V5 worker-exit lock recovery — explicit, stopped and isolated

An ordinary V5 request releases its non-expiring lock in Laravel's existing
`finally`, after session persistence. If a worker exits without that path, the
lock intentionally remains; a later request waits only its configured positive
bound and fails closed. A timeout is not proof of a dead owner or a failed DB
commit. Normal `cache:clear` does not remove the separate lock store.

Use only the original private instance configuration, same image/mount/UID and
complete Fresh profile. Stop **all** web/API/CLI/other users of that store and
exclude any restart for the entire operation; the example `stop web` alone does
not discover or prove that global drain. Preserve the current full private state
and reconcile any uncertain booking by its original stable receipt/ID. Never
infer rollback, delete sessions or generate replacement events/keys to unblock.

After independently confirmed shutdown and restart exclusion:

```sh
docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml -f deploy/laravel/compose.web.yaml stop web
# Verify ALL users exited; keep restart excluded. --stopped is only your assertion.
docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml -f deploy/laravel/compose.web.yaml run --rm --no-deps web --clear-stopped-cache-locks --stopped
```

This one-off mode runs **all the unchanged web-entrypoint preflights first**,
including non-root, instance/setup/cache and TLS syntax checks. Then the original
application must boot its exact admitted private Fresh FileStore before the
existing `php artisan cache:clear file --locks` command runs. No entrypoint
replacement, default-off operator-store guess or extra API overlay is used. It
starts no Apache/HTTP listener, publishes no `run` service ports, creates no
credential and does not reopen setup. Unknown/missing/extra arguments refuse.

The command removes **ALL cache locks in that exact store**, not just one session
lock. It does not clear ordinary cache/session records, DB rows, setup closure,
account generations or keys. This is safe only behind the verified global drain;
neither the flag nor the command proves it. If any preflight, profile or cleanup
is unconfirmed, keep the service stopped and inspect privately; do not force an
owner/path, relax a gate or run the normal web command as fallback. After confirmed
success, deliberately restart the chosen normal profile and obtain current
status/login; no automatic booking retry. This source and its component fixtures
are not a Docker/real deployment, arbitrary-backup or disaster-recovery pass.

## Evidence for this source packet

Checks are bounded source syntax/config inventory checks. No Docker daemon is
available in the preparation environment: an actual image build, Apache/TLS
startup, mount identity, provisioning, account creation or browser functional
acceptance **has not been run**. Local preparation triggers no manual CI or real
activation; natural repository CI is separate regression evidence, not a Docker
or deployment pass. Source publication does not perform provisioning.
The separate synthetic20-request Kernel proof on earlier Main is not a Docker
or browser acceptance certificate for this composition.

Primary references: [Laravel deployment/public entry point](https://laravel.com/docs/13.x/deployment),
[official PHP image/arbitrary user](https://github.com/docker-library/docs/blob/master/php/README.md#running-as-an-arbitrary-user),
[Composer release hashes](https://getcomposer.org/download/),
[Compose profiles](https://docs.docker.com/compose/how-tos/profiles/),
[Apache request-read timeouts](https://httpd.apache.org/docs/2.4/mod/mod_reqtimeout.html).

### Existing standard V5 vacation-workflow upgrade

New browser-first-user installs now prepare V7. Existing closed V5 instances are
not reinstalled or silently disabled: the explicit same-cohort offline
`chrono:vacation-workflow-v7` command preserves users, policy, keys and bookings,
then rebinds the known admission metadata. See the maintenance prerequisites,
channel metadata and interrupted-handoff boundaries in
[vacation-requests-standard.md](vacation-requests-standard.md). No deployment,
key rotation, arbitrary restore or Native-data cutover is implied.
