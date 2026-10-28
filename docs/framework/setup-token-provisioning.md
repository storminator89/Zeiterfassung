# Local selfhosting operator: first-admin token files

This source adds a local operator workflow, **not an executed installation**.
No live token/key/account is created by merely loading/registering the classes.
No unauthenticated provisioning endpoint, public first-visitor admin, native token
fallback, session injection, autologin or email delivery is supplied. Existing
native `register.php`/`APP_SETUP_TOKEN` remain separate and unchanged.

## Prepare, locally and only once

After the separately reviewed Fresh4/V4 files exist, stop and drain every web
worker/request. The explicit local command is:

```
php artisan chrono:setup-token prepare --instance=/private/chrono-fresh-INSTANCE --stopped
```

The placeholder is a canonical generated Fresh4 path, not a token. There is no
password/token/key/expiry input, `--force`, reset, rotate, show or reopen command.
`--stopped` is the operator's prerequisite assertion, not process discovery or a
claim the command has stopped any service. Console registration never runs it.

The Unix operator must own the0700 instance outside checkout/webroots. Every
ancestor, including `/`, must be root/current-UID owned and not group/other writable
unless sticky. Each component is `lstat`-checked as a directory, never a symlink.
Trusted sticky1777 ancestors protect the existing trusted-owned child against
unrelated-UID rename; foreign-owned/nonsticky-writable ancestors are refused.
The same-UID/root/host/mount/permission-changing actor is trusted; PHP paths are
not `openat`-atomic against such actors. No existing directory is chmod/chowned.

The fixed private `runtime-admission.json` must be a stable0600 single-link file,
exact eleven-key V4 admission bound to this instance's `application.sqlite`.
Cold-file identity/hash, current admitted **read-only** snapshot and canonical
EMPTY V4/initial policy are checked. Populated non-seed tables are refused by
COUNT before reference row-digests; no account/credential values are returned or
used. Cold identity verification hashes the private file without exposing its
bytes. There are no target DB writes (canonical references are anonymous memory
only), user-password operations or actual LDAP/key operations. The source never accepts an existing user
installation as a new setup target. Raw before/after file hashes are strictly
**stopped-only**: all external writers/requests must already be drained. A separate
void read helper closes and releases every existence/COUNT statement, rolls back,
disconnects and releases the reader/PDO; its entire scope ends before the post-read
cold hash or any token/profile generation. A PDOStatement may retain a connection
even after PDO variables are nulled, so that alone is not sufficient. Cleanup
failure stops preparation rather than continuing to raw hashing. Own-handle
cleanup neither stops nor proves absence of external writers and is not an online
consistency/recovery mechanism. The command does not claim an atomic filesystem/DB
transaction or protect against uncoordinated other writers; setup's writer still
rechecks zero users under its own lock before any administrator insertion.

A fixed private empty `setup-operator.lock` uses a nonblocking kernel `flock` for
the complete local prepare/close operation. It is never deleted/replaced, avoiding
an intentional lock-file ABA split. Existing locks must retain stable private
single-link identity; busy/foreign/altered files fail, no retries.

Only an explicitly executed `prepare` generates a cryptographic256-bit token.
It is written exclusively to **`operator-setup-token.txt` mode0600**, never returned
by the service or printed. The caller receives paths/status only. The operator
personally reads this file locally and uses their trusted private channel to the
intended administrator, or enters it themselves in the normal HTTPS setup form.
Do not paste it into chat, argv/environment/URLs/logs/screenshots/model-readable
outputs or an ordinary automated browser fill. No token file is a web asset.
The web composition must never read/serve the raw operator file. This is software
separation, not a claim that a compromised same-UID web process cannot read it.
Unsetting a PHP variable/unlinking a file is not certified memory/secure erase.

**`setup-profile.json` is written last**, exclusively0600, with exactly:

```
version
runtime_admission_sha256
token_sha256
expires_at
```

Version is integer1; admission hash is SHA256 of the exact stable admission-file
bytes, transitively binding path/dev/ino/signature chain without a second metadata
format. Token is represented only by its SHA256 digest. Expiry is an integer epoch
fixed to generation time plus3600seconds; clock correctness is an operator trust.
No executable PHP config or `.env` is created. Partial failures may leave private
files and block a repeat; only confirmed newly owned completed files are cleaned.
There is no overwrite/automatic repair, and no secret-bearing native diagnostics.

## Web contract — separate default-off composition

The separate FreshWeb profile explicitly opts into setup and stably reads only
the private admission/profile files. It checks `lstat(instance/setup-closed)`
**before** reading the setup profile: **any existence**, including an unexpected
type/symlink/partial marker, means setup is closed. It never follows that marker.
Absent/closed/expired setup does not block normal login.

The pure `FirstAdminSetupFiles::configuration(profile, admissionHash, closed, now)`
checks the exact four-key bound profile and yields only the existing
`chrono_setup.enabled/token_sha256` values. Closed wins even over an absent/broken
profile; expiry returns disabled/null. Malformed/unbound open profiles fail closed.
The web gate must refresh closure/profile/binding/expiry before **every setup
request**, before session/auth handling, not trust config cache or only worker
boot. Normal PHP per-request hosting is the initial supported boundary; no
Octane/long-running-app support or instant revocation of an already admitted
request is claimed. Existing local auth assets, CSRF, persistent setup limiter,
bcrypt, zero-user writer check and normal subsequent login are still required.

## Close after confirmed setup

After creating the admin and confirming normal login, stop/drain workers again:

```
php artisan chrono:setup-token close --instance=/private/chrono-fresh-INSTANCE --stopped
```

`close` needs no DB/admission read: revocation must work even if the database is
broken. It exclusively writes **`setup-closed` first**, flushes it, and NEVER
removes it, even on partial failure. Any existence closes the web gate. Only then
does it unlink the confirmed private operator token file without reading its
contents. The nonsecret digest profile and persistent lock remain. Missing token
file is acceptable on repeats; unexpected objects produce failure/inspection,
not deletion. No reopen/rotation/reset is implemented. Keep workers stopped if
closure/cleanup is unconfirmed; retain the marker even if receipt cleanup fails.

Remove the trusted setup opt-in, clear/rebuild any cached deployment configuration
and restart/reload the intended workers before normal operation. The filesystem
marker plus zero-user guard is not irreversible consumption across a full restore
of an earlier whole instance that omitted the marker. Backup/restore must preserve
closed state; no auto-restore/recovery policy is invented here.

## Still separate / evidence

The independent Laravel app key is NOT generated here. `config/app.php` uses
`FRAMEWORK_APP_KEY`, whereas stock `key:generate` writes `APP_KEY`; do not silently
reuse native signing/encryption keys or claim that command provisions this profile.
Private app-key provisioning, TLS/host/config activation, effective intake-policy
gates, real first-admin/password creation, availability and browser acceptance are
separate reviewed and action-authorized steps. This operator-local flow does not
prove iOS terminal access or a private cloud-user token handoff.

Source validation is limited to lint/static NEW-only/output/path boundaries and a
pure invented digest-profile/expiry/closure journey. Neither provisioning command,
CSPRNG generator, DB read nor actual secret/file lifecycle is executed as evidence.
No account, app key, real setup token, runtime server or remote action is created.
