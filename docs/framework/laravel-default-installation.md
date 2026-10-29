# Laravel default installation — NEW local V5

`docker build .` now builds the Laravel 13 application; root `compose.yaml`
selects its HTTPS Web service by default. The root image's normal command is the
existing fail-closed Laravel Web entrypoint, not Artisan help or native PHP.
This is a **source/default selection**, not a deployment or automatic installer.
No account, password, key, certificate, existing database or host setting is
created/changed by publishing these files.

## Prerequisites that remain real

- Supported trusted single-host Linux Docker/SQLite/flock environment, same
  non-root numeric UID/GID for the operator and every worker. No NFS, replica,
  rootless/Desktop mount-identity or arbitrary-restore acceptance is implied.
- A new canonical, operator-owned 0700 private host parent outside the checkout,
  with the existing [Fresh file/ancestor contract](fresh-application-files.md).
  The bind target remains `/var/lib/chrono-laravel`; prepare through the same
  image/mount. No automatic mkdir/chown/chmod/adoption or native-data import.
- Existing matching TLS files and a certificate already trusted by the browser
  for the exact HTTPS origin. No HTTP fallback, certificate generation/warning
  bypass, proxy/DNS/firewall change or public bind. Actual device reachability,
  including iOS access to another host's localhost, is a separate prerequisite.

The registering user has **no terminal or setup-token step**. An authorized
installer still prepares the private instance/App-Key/TLS before starting Web;
source review/build does not authorize executing those real provisioning actions.

## One standard path

From the repository root, build the default image (no data/credential creation):

```sh
docker build -t chrono-laravel:local .
```

Copy `deploy/laravel/local.env.example` **outside the checkout**, edit privately,
and record its absolute path as below. Fill UID/GID, existing private parent,
canonical local HTTPS origin, unused HTTPS port and existing TLS file paths.
The template has no secret values; default V5 shared budget=true, session lease=0,
positive wait=10 and token setup=false are intentional. Keep them for NEW V5;
they do not authorize an old V4 profile or bypass its different lock contract.
Never paste rendered Compose/inspect output: the Web env_file contains the key.

```sh
OPERATOR_CONFIG=/absolute/private/chrono-local.env
# Explicitly create one NEW empty V5 cohort + nonsecret browser-first-user policy.
# No account/password/token/key/listener is created by this command.
docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml run --rm operator chrono:prepare-fresh --private-parent=/var/lib/chrono-laravel --new-only --local-browser-first-admin
```

Put only its exact returned basename into `CHRONO_LARAVEL_INSTANCE` in the
private config. Do not guess/reuse/edit a manifest or retry an uncertain result.
With all workers stopped, and **only after the separate real key-creation
permission**, create the App-Key using the existing producer:

```sh
INSTANCE=/var/lib/chrono-laravel/chrono-fresh-REPLACE_WITH_EXACT_RETURNED_SUFFIX
docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml run --rm operator chrono:application-key --instance="$INSTANCE" --new-only --stopped
```

No API-key preparation or API environment overlay is needed for the standard
path. After registration, local administration can explicitly prepare and switch
the API in **Administration → API verwalten**, with current-password verification
and separate key-creation confirmation. The signing key is never displayed. See
[API administration](api-admin-ui.md); real key creation still requires the
authorized administrator's deliberate action. LDAP remains a separately admitted
operator integration, not implied by the standard Web start.

Do not run `chrono:setup-token`, `chrono:first-admin`, `key:generate`, seed/migrate,
route/config cache or an old/native initializer for this path. Leave API, LDAP
and terminal off through first registration. The standard enables API UI
*capability*, not effective API access; that remains off until a confirmed local
Admin action after closure. LDAP/terminal profiles are not implicitly enabled.

After source review, trusted TLS, real mount/UID/lock behavior and intended-device
reachability are accepted, deliberately start the **root default Web service**:

```sh
docker compose --env-file "$OPERATOR_CONFIG" up -d
```

There is one unprofiled service, `web`, no init/operator dependency and no native
fallback. It binds only `127.0.0.1:<chosen port>:8443`, with original read-only,
non-root, dropped-capability, no-new-privileges and no automatic-restart policy.
Without the prepared private cohort/key/TLS/settings, config or startup refuses;
`docker run` with no configuration is not a working or auto-initialized app.
A running container or `/up` is not setup/login/operational acceptance.

Open the exact trusted HTTPS origin in the browser. Login/Register opens the
existing card: accept/edit `admin`, enter email and choose/confirm your own
password. The first successful transaction becomes Admin, then setup closes and
normal login follows. No shared password, token or autologin. **Any other person
who can reach an unfinished shared local installation could register first.**
Local address checks are not identity verification; complete setup in the
trusted local setting, never through public forwarding. See
[browser-first-user boundaries](browser-first-user-admin.md).

## Operations and native compatibility

The [full operator/Web contract](installation-docker.md), immutable admission,
CSRF/encrypted Secure-cookie/login-budget guards and
[V5 non-expiring session locks/stopped recovery](v5-nonexpiring-session-locks.md)
remain unchanged. Complete private backups must retain closure and file identity;
arbitrary copied/moved backup adoption is not opened by this default switch.
Unknown commit/closure/recovery outcomes are not permission to reset/retry.

Native root PHP files and existing data are not migrated, rewritten or opened by
Laravel. For an existing native installation, keep its old image/config/data
until an independently accepted migration exists. Its original Docker build is
preserved explicitly, from the same root context:

```sh
docker build -f deploy/native/Dockerfile -t chrono-native .
```

Use its original native `.dockerignore`, private volume and
[native security/upgrade instructions](../../SECURITY.md#native-compatibility-operation).
Never point native and Laravel processes at the same database. The old explicit
`deploy/laravel/Dockerfile` still provides the command-only operator image, and
its profiled Compose files remain available for the detailed alternative V4/V5
operator workflows; they are not automatic native migration paths.

## Verification limits

Source/YAML/entrypoint/context and unchanged-runtime checks do not constitute an
actual Docker image build, daemon/mount, Apache/TLS, real provisioning, browser,
login or disaster-recovery pass. No such activation is performed by this source
change. Existing bounded component/Kernel evidence is not a deployment
certificate for this new default composition. The user retains browser/UI
acceptance; no complete production cutover is claimed.

Primary configuration references: [Docker build-context/ignore precedence](https://docs.docker.com/build/concepts/context/),
[unprofiled Compose services](https://docs.docker.com/compose/how-tos/profiles/),
[Laravel public entry point/deployment](https://laravel.com/docs/13.x/deployment).
