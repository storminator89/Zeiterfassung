# Optional API for a NEW empty Laravel installation

This is **source preparation, not an executed installation or API acceptance**.
No key, database, account, token, server or runtime configuration was created or
activated to prepare this packet. Existing/native installations and original
keys remain a separate admission/cutover workflow. Do not attach an old signing
key to a new database or claim old-token/account compatibility from this flow.

## Reuse the admitted API, do not install another API

The existing API registrar, wire/method/CORS/error boundary, maintained JWT
implementation, account/password-version checks, own-data scopes and attendance
writer remain unchanged. The optional profile uses the existing **same persistent
Web/API budget** and the same ten-field `ExistingApiKeyAdmission` validator and
lazy read-only `ExistingApiFileSigningKey`. No new key reader, guard, cache
budget, endpoint or token format is introduced.

`chrono:api-key` fills the missing NEW-only preparation step. Only for a canonical
EMPTY, budget-ready Fresh V4 instance, before setup preparation, it creates:

- `jwt.key`: a NEW independent32-byte random value in the native64-lowercase-ASCII-
  hex signing format. Key bytes are written only here, never to an environment
  file, CLI response, metadata, config or log. No existing key is opened/read.
- `framework-api-key.env`: private metadata transport only. Its single
  `FRAMEWORK_FRESH_API_KEY_ADMISSION` value is canonical Base64 of canonical JSON
  containing the existing exact ten-field key/parent identity, content fingerprint
  and canonical **same DB-profile digest**. Base64 is NOT encryption. Never print,
  paste, inspect/render into logs or commit this file/profile/fingerprint.

Both files are0600/single-link/current-UID under the own0700 instance, outside
checkout/webroots, with canonical trusted ancestry. Both identities are reserved
exclusively with `x+b` BEFORE entropy. Writes/flush/fsync/close must confirm;
partial/uncertain files are retained privately, never overwritten, deleted,
repaired, adopted or retried automatically. An existing key, metadata file,
symlink, alternate file type or race fails closed. No rotate/reset/force/show,
arbitrary key path/value, APP_KEY/encryption-key reuse or permission repair exists.

The command shares the persistent `setup-operator.lock` with setup/LDAP
preparation. It refuses any setup profile/token/closed artifact. It requires
uncached config/routes and every FreshWeb/setup/runtime-read/runtime-write/API/
shared-budget selection off. `--stopped` is the operator's assertion that every
worker/request is stopped and drained, not worker discovery or process control.

Before any allocation/entropy, the admitted read-only DB and exact canonical
Fresh V4 reference must show empty non-seed tables and a physically PRESENT,
canonical EMPTY `login_attempts` table via `requireFreshLoginBudget()`. No DDL,
counter reset, account query value, migration or admission rewrite is performed.
The complete reader/PDO/statement/reference scope ends before the second cold
snapshot; instance/manifest identities remain fixed throughout preparation.
Only status booleans are returned, not paths, key material or the profile.

## Operator sequence — requires separate execution/activation authorization

Follow [installation-docker.md](installation-docker.md) first: same trusted Linux
Docker host, image, **nonroot UID/GID and canonical mount namespace** for operator
and web, with supported local SQLite/flock. Never prepare on host PHP then move
files into the container: the recorded path/device/inode must match the later
web process. The existing Docker parent remains mounted at
`/var/lib/chrono-laravel`; no new mount/host/TLS/listener is introduced here.

1. Build/review the candidate image and explicitly prepare one NEW empty instance.
   Set the private `OPERATOR_CONFIG` and exact returned container `INSTANCE`
   values using the existing installation instructions. Keep all workers stopped.
2. After separate key-generation authorization, BEFORE `chrono:setup-token prepare`:

   ```sh
   docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml run --rm operator chrono:api-key --instance="$INSTANCE" --new-only --stopped
   ```

   The unchanged `chrono:application-key` remains separately required. Neither
   command starts a server or creates an account. Refusal/uncertainty means stop
   and inspect privately; never remove artifacts or generate replacement keys as
   an automatic recovery. A new clean instance needs new explicit authorization.
3. Prepare first-admin setup and perform its separately authorized private
   token/password handoff using the existing Web-only installation flow. Confirm
   normal login, stop/drain workers, close setup and select closed setup mode.
   Do not enable the API during the first-admin setup window. Initial setup/Web
   login may have used the old file throttle; do not treat a profile switch as a
   verified old-budget migration. Before API selection, the operator must establish
   a cold quiet transition: strictly MORE than600 seconds since the last old
   file-throttled login attempt, with every old worker stopped/drained and no
   intervening requests. Do not clear counters/cache or reuse a populated/native
   instance to make this gate appear satisfied. This NEW-only quiet-window rule
   is not an existing-counter migration or a proof of concurrent transition.
4. Only after final integrated source and physical-admission/operational review,
   explicit activation authorization and the existing HTTPS/session/request-
   lifetime gates, select the **additional** API overlay when recreating web:

   ```sh
   docker compose --env-file "$OPERATOR_CONFIG" -f deploy/laravel/compose.yaml -f deploy/laravel/compose.web.yaml -f deploy/laravel/compose.api.yaml up -d web
   ```

   This is a future operator command, **not run or authorized by this source
   packet**. The overlay appends only the private metadata EnvironmentFile,
   explicitly enables the existing shared-budget and admitted-API flags and
   forces setup off. The unchanged web entrypoint therefore requires the existing
   closed-setup marker; this is not a new proof that an administrator exists. The
   normal web profile retains its App-Key environment file, TLS, loopback bind,
   UID/capability/read-only restrictions and no automatic restart. The base
   operator does not load either private environment file. Do not render Compose
   config, dump process environment, use `config:show`/`config:cache`, route caches,
   `optimize`, `printenv`, `phpinfo` or secret-bearing diagnostics.

Without the overlay, both capabilities remain default off. Off API config does
not parse metadata. On config accepts only bounded, canonical Base64/JSON; the
existing profile then validates exact typed fields, same DB manifest and service
identities. Missing/stale/conflicting/unsafe metadata or wrong schema/mount/UID
must fail closed, never discover/adopt/repair a key or fall back to another store.
The secret remains lazily read by the existing reader only for actual JWT work.

## Acceptance still outstanding

After separately authorized activation, a real user/private client journey must
prove login → own entries/status → start/pause/end/repetition, malformed/expired/
revoked token and cross-user refusals, shared Web/API quota and confirmed429,
HTTPS/origin behavior and representative Firefox/Edge compatibility. Do not put
passwords, bearer tokens, keys or full responses containing credentials into
screenshots, chat, source or logs. A running container or `/up` proves none of this.

This packet checks only PHP syntax, coarse pure metadata transport/composition
and exact source preservation plus independent bounded source review. It does
not run the provisioner, entropy, PDO/SQLite/reference creation, key-file reads or
writes, Compose/Docker/Apache, HTTP/browser/extensions or a new GitHub CI run.
Physical identity/durability/race/commit/proxy/client acceptance, quiet budget
transition, verifier nbf/revocation-race differences, #197 and session #29/#30,
cutover/rollback and complete #77 remain open. V5/account-lifecycle sources and
existing-key/data migration are not silently admitted by this V4-only package.
