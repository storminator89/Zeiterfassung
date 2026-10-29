# Separate Laravel Docker installation — candidate, not activation

This is an executable **separate installation composition**, not a cutover of the
native application, an old-data importer or proof that deployment works. The root
Dockerfile/SECURITY.md still describe native PHP. Never point both applications
at the same database. This packet itself creates **no instance, secret, account,
listener, TLS certificate, host setting or external service**.

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
- The default image command is `php artisan list --raw`, **not a web server**.
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

Do not use the native root Dockerfile for this Laravel image. The Dockerfile's
ignore file is used only with this explicit `-f` build. No real `.env`, volume,
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

### Explicit NEW V5 alternative; V4 remains the default

The command above selects the original V4 profile. For a separately reviewed
complete NEW-only V5 source composition, use the following **instead** of that
command, never both and never on an existing instance:

```sh
docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml run --rm operator chrono:prepare-fresh --private-parent=/var/lib/chrono-laravel --new-only --dormant-account-lifecycle-v5
```

Record only its exact returned basename. This prepares dormant files, not
Runtime activation, credentials, migration or source acceptance. A pre-NEW-V5
image still refuses through its original Runtime hold.

Before Web setup on an admitted NEW V5 source, explicitly set
`CHRONO_LARAVEL_SHARED_LOGIN_BUDGET_ENABLED=true` in the private **operator
configuration**, and keep it true afterwards. V5 requires the existing shared
budget even with API off. Web Compose passes this existing boolean selection;
default false preserves V4. False or a nonboolean does not bypass V5 admission.
Do not append flags to generated App/API key files. The operator-only base
service remains unchanged and does not inherit this Web opt-in.

Do not use `compose.api.yaml` for first-Admin setup: that optional overlay
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

**Only if API is selected and its key creation separately authorized**, run the
original NEW API-key producer now, before any setup profile/token or user:

```sh
docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml run --rm operator chrono:api-key --instance="$INSTANCE" --new-only --stopped
```

Otherwise omit that command and leave API off. In either case, provision the
short setup token only after the selected keys above:

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
4. Establish a real maximum **wall-clock request lifetime below the session-lock
   TTL** and confirm single-host lock/SQLite behavior. Apache `TimeOut`, request
   read timeouts and PHP `max_execution_time` are useful limited controls, but
   **do not establish that whole-request bound**, particularly for blocking I/O.
   This composition has no new hard watchdog and does not claim this gate passed.
   Do not enable web writes until that external operational gate is resolved.
5. Establish the supported **private operator-token handoff** before creating an
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

Only after the gates above, set `CHRONO_LARAVEL_SETUP_ENABLED=true` for the short
first-admin window. The key env file is loaded only by the additional web compose
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

Open the exact configured HTTPS origin's `/setup.php` through the supported
private flow. The user chooses their password privately; submit only with the
applicable account-creation authorization. Confirm normal password login after
setup; no autologin/session injection. If anything fails or is uncertain, do not
regenerate tokens, reset the instance or assume a database operation rolled back.

## Phase 5 — close setup and return to normal local-admin mode

Stop/drain **all** requests and verify workers exited; a stop timeout/SIGKILL is
not proof every operation committed or rolled back:

```sh
docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml -f deploy/laravel/compose.web.yaml stop web
# After confirmed normal admin login and confirmed worker shutdown:
docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml run --rm operator chrono:setup-token close --instance="$INSTANCE" --stopped
```

Require confirmed closure, keep the monotone marker even on partial cleanup, and
set `CHRONO_LARAVEL_SETUP_ENABLED=false` before recreating the web container with
the Phase4 command. The entrypoint refuses closed-mode startup without a closure
marker; the marker alone is still **not** proof an admin exists. No key rotation,
backup deletion, database adoption or native-host switch occurs. If closure is
unconfirmed, keep web stopped and inspect locally.

LDAP, JWT API, device protocol/keys and shared-login-budget selection remain
separate, default-off capabilities; this local-admin installation does not
configure/test/activate them. The existing per-file login/setup throttles remain,
not a new capacity or reverse-proxy acceptance claim.

## Evidence for this source packet

Checks are bounded source syntax/config inventory checks. No Docker daemon is
available in the preparation environment: an actual image build, Apache/TLS
startup, mount identity, provisioning, account creation or browser functional
acceptance **has not been run**. No GitHub CI/test run, publish or activation.
The separate synthetic20-request Kernel proof on earlier Main is not a Docker
or browser acceptance certificate for this composition.

Primary references: [Laravel deployment/public entry point](https://laravel.com/docs/13.x/deployment),
[official PHP image/arbitrary user](https://github.com/docker-library/docs/blob/master/php/README.md#running-as-an-arbitrary-user),
[Composer release hashes](https://getcomposer.org/download/),
[Compose profiles](https://docs.docker.com/compose/how-tos/profiles/),
[Apache request-read timeouts](https://httpd.apache.org/docs/2.4/mod/mod_reqtimeout.html).
