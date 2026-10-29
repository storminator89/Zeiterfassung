# Local first administrator — NEW V5, no setup token

This is an explicit local operator command, not a seeded/default credential,
public first-visitor setup, existing-account reset or executed installation.
No live account, password, token, key or web process is created by source review.
Actual user-specific credential creation still needs its action-time authorization.

## Simple local flow

Prepare the NEW V5 files and the App-key using the existing authorized operator
commands in [Docker installation](installation-docker.md). Complete any selected
API/LDAP key preparation while still empty; this command cannot make those steps
possible afterwards. Stop and drain all web workers/requests. In the operator's
own interactive terminal run:

```sh
php artisan chrono:first-admin --instance=/private/chrono-fresh-INSTANCE --new-only --stopped
```

Use the exact returned canonical directory, not the placeholder. The networkless
Docker operator invocation is in the installation guide. `--stopped` asserts the
operator's prerequisite; it does not discover or terminate processes.

1. Accept `admin` or choose another username.
2. Enter the administrator email.
3. Choose and confirm your own password, entered invisibly.

There is no initial password to remember or token to copy. No password/token
argument, environment input, file import, noninteractive mode or visible password
fallback is accepted. There is no auto-retry, force/reset/reopen or server start.
The same server-side username/email/password validation is shared with the
original browser setup, including 12–72 password bytes and matching confirmation.

## Admission and account write

The operator must own the canonical `0700` instance and its `0600` single-link
private files outside the checkout/webroots. The existing persistent operator
lock serializes this path with token/API/LDAP provisioning and closure. It accepts
only an exact `account-lifecycle-v5` manifest bound to
`<instance>/application.sqlite`; V4, native, adopted, renamed, malformed and
nonempty sources are not an alternate path. The existing Runtime and canonical
fresh-state/physical-login-budget checks are used, not a new PDO opener.

Existing setup token/profile/closure objects refuse this NEW-only operation.
The already prepared private `framework-app-key.env` must have its canonical
32-byte-base64 line; it is checked internally but never activated, returned,
fingerprinted in output or used as an administrator password. No key is generated
or replaced. Metadata/manifest/key binding and stopped DB bytes are rechecked
after the terminal prompts, before hashing/writer resolution.

The existing `AttendanceWriter::firstLocalAdministrator` performs its unchanged
single transaction, rechecking **all users=0** under its writer lock and inserting
one local active administrator with the original audit/default-model behavior.
No existing account, role, password, auth generation or native install is changed.
The ordinary configured bcrypt hasher runs before that writer lock.

## Closure, errors and ordinary login

Only after a confirmed account COMMIT does the command create `setup-closed`.
It never creates a token/profile. The success receipt contains no credential or
account identity, and does not claim a running or accepted web application.
Keep `CHRONO_LARAVEL_SETUP_ENABLED=false`. After all other deployment gates are
met, start the separately authorized closed-setup web profile and use normal
password login. CSRF, login/session/shared-budget and V5-lock protections are not
changed or bypassed by local operator initialization. No autologin occurs.

An explicit input-validation refusal occurs before hashing/writing and reports
that it created no account; correct those inputs before a manual new attempt.
Any other late error can follow a committed account or an uncertain outcome. The
command returns only a generic unconfirmed result, never raw exception/SQL/input,
and makes no rollback claim. Keep web stopped and inspect the saved state locally;
do not automatically retry, regenerate keys, reset the DB or remove a closure
marker. A missing closure marker prevents the existing closed-mode web entrypoint
from starting. The existing separately authorized revoke-only close command can
finish closure after the operator has established the account state; this new
command never supplies a reset/recovery bypass. Preserve closure in full backups
and restores; no irreversible consumption across restoring an old empty entire
instance is claimed.

The original browser-token workflow remains available separately and unchanged.
Do not mix it into this token-free path or enable its web profile after local setup.

## Bounded source evidence

One disposable journey uses public fixed dummy bcrypt/App-key values, not a
credential generator. It exercises the real NEW-V5 Runtime and first-admin writer,
model/audits and closure, plus missing/malformed/changed key, input, mixed-token,
V4, repeat and populated-state refusals. All owned fixtures are removed. Separate
console discovery, noninteractive refusal and original browser-service/view
checks pass. Real hidden-TTY input, configured-password hashing/login, deployment,
closure-write failure, crash/COMMIT-loss and restore acceptance are not claimed.
