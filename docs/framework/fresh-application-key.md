# First-installation Laravel application key — local operator workflow

This is source preparation, **not an executed key generation or installation**.
The command is registered for CLI only and never runs at boot/request time. No
key, token, password or account was generated/read to validate this source.

## Existing contract / wrong default to avoid

`config/app.php` reads only `FRAMEWORK_APP_KEY` for the framework AES-256-CBC
key. FreshWeb requires a supported32-byte independent key, no previous-key list,
production/debug-off hosting and explicit default-off configuration. It does not
read a new keyfile or generate a fallback. Native encryption/signing/LDAP/setup
keys and fixture keys must not be reused.

Stock `php artisan key:generate` writes `APP_KEY` (or prints a value with `--show`),
not this application's `FRAMEWORK_APP_KEY`. It is therefore **not** the matching
first-installation command. Do not use secret-printing commands/config dumps,
`printenv`, `phpinfo` or screenshots/chat/model-readable logs to transfer keys.

## New files, before activating FreshWeb

1. Prepare the separately reviewed NEW empty Fresh4/V4 instance. Keep all web
   workers stopped/drained and FreshWeb/runtime read/write opt-ins **false**.
2. The local selfhosting operator may explicitly run:

   ```
   php artisan chrono:application-key --instance=/private/chrono-fresh-INSTANCE --new-only --stopped
   ```

   The path is a placeholder for the generated private instance, not a secret.
   Flags are operator prerequisite assertions, not process discovery/stopping.
   If FreshWeb was enabled before providing a key, disable it through your trusted
   deployment configuration first; an enabled incomplete profile fails at boot.
3. The command checks canonical EMPTY V4 state via the existing admitted read-only
   source/metadata and anonymous canonical references. Non-seed tables are counted
   empty before row digests. Cold-file verification hashes the private database
   without exposing bytes; no account/credential values are returned/used and no
   target DB writes occur. This is not adoption, account recovery or key rotation.
   **Stopped-only lifecycle:** every external writer must already be stopped and
   drained before either raw cold-file snapshot. The dedicated read helper closes
   and releases each existence/COUNT statement, rolls back, disconnects and releases
   its PDO/reader; its complete void scope ends before the post-read cold hash,
   file reservation or entropy. Nulling PDO variables alone is insufficient while
   a PDOStatement can retain the connection. Any cleanup failure stops preparation.
   This separation does not make raw hashing safe for online databases or prove
   that other processes are stopped; `--stopped` remains an operator assertion.
4. A fixed **`framework-app-key.env`** is reserved with exclusive `x+b` **before**
   CSPRNG invocation. Any existing object, including symlink, hardlink or racing
   creator, fails without reading/truncating/replacing its contents. No arbitrary
   key path/value, `show`, overwrite, force, rotate, reset or previous-key option
   exists. Only a NEW independent32-byte key is encoded to one EnvironmentFile
   line with variable name `FRAMEWORK_APP_KEY` and standard `base64:` value.
   No actual key sample is included in this document.
5. The generated file is0600/single-link/current-UID under an own0700 instance
   outside checkout/webroots. Every ancestor is root/current-UID owned and not
   group/other writable except sticky; links/aliases are rejected. Stable instance,
   admission bytes and held file identity are checked around creation/write.
   The new file is flushed/fsynced and closed, without reading its key back.
   Return/CLI output is status and path only, never key/digest/value. Existing
   files/directories are not chmod/chowned. Concurrent creators cannot both win.
6. The operator separately binds the private file using their **existing trusted
   service/container secret/EnvironmentFile mechanism**, then starts/reloads the
   intended workers with `FRAMEWORK_APP_KEY` available. A systemd EnvironmentFile
   directive or container `env_file` reference uses only the private pathname;
   do not put the value on a command line or public website. This source does not
   edit service units, compose files, `.env`, process environments or host settings,
   install a secret manager or activate any runtime flag. Actual binding/start is
   a separate authorized deployment action. Restrict access to the intended UID;
   this packet makes no cross-UID permission adjustments automatically.
7. Provision the separate operator setup token, enable the reviewed FreshWeb/setup
   profile with HTTPS/private session/cache/locks, create the first admin through
   the normal protected form and log in normally. Close/remove the setup opt-in
   afterwards as described in `setup-token-provisioning.md`.

## Retention, failure and limits

Keep the same private app-key file across normal restarts. Never regenerate it
per request/restart or treat a missing file as permission to replace a key of an
existing installation. This command refuses existing accounts/canonical state and
the existing file; there is no recovery/rotation path. Losing/changing the app key
can invalidate/decrypt-fail encrypted cookies/sessions and any framework-encrypted
state. It is not a password reset or database-encryption feature. Keep restricted
backup/restore procedures for this key; a DB-only snapshot is not a full system
backup containing this external file. Actual backup/recovery is separately gated.

Failure never chains native key/DSN/SQL/path diagnostics. Cleanup removes only the
held exclusively NEW identity while the complete private path remains admitted;
uncertain/replaced/closed-handle files remain private for operator inspection.
No automatic retry, overwrite, existing-key read or recovery is attempted. File
`fsync` is not certified parent-directory/power-loss durability. No secure memory
erase is promised by unsetting PHP variables or unlinking a partial file.

The filesystem boundary trusts root/current UID, their permission/mount changes
and the host; it is not `openat`-atomic hostile-same-UID isolation. Sticky ancestors
prevent unrelated-UID replacement of trusted-owned components, not denial of
service. Runtime locks/races, disk-full/crash/recovery, actual key provisioning,
private EnvironmentFile binding, HTTPS/proxy/CSRF/session/browser acceptance and
the complete V4/Setup/FreshWeb source composition still need separate review and
applicable action-time authorization. No generator, keyfile lifecycle, DB access
or actual runtime is executed as source evidence; only lint/static boundaries.
