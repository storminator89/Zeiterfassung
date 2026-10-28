# Ordinary local self-registration (explicit opt-in)

This is real account creation for `role=user`, `auth_source=local`, not native
first-administrator setup. Native `register.php` and its `APP_SETUP_TOKEN` remain
unchanged. No first visitor becomes admin: the existing operation-scoped writer
checks for a current **local administrator inside its IMMEDIATE transaction**.
A database without one refuses ordinary signup without inserting anything.

## Trusted composition and retained UI

Both `chrono_registration.enabled === true` and
`LocalWebRoutes::register(..., includeRegistration: true)` are needed. The shipped
configuration is false; default routes remain inactive. The controller and service
also refuse a disabled profile, including a stale compiled route. No environment
or request field enables it. Login displays its existing registration link only
for the trusted enabled profile. The form uses the existing login card, logo,
styles and three locales, not a second UI.

GET/POST `register.php` use the existing encrypted file-session / session-blocking
composition and `LegacyFormCsrf`; signup POST additionally uses the independent
persistent `chrono-web-register` limiter: 10 attempts per IP per hour and 100
attempts per profile cache per hour. Counters contain hashed IP identifiers, not
passwords, usernames or email addresses. The synthetic composition rebinds this
limiter after selecting its own private persistent cache. This is not a production
capacity, proxy trust, abuse-prevention or distributed-lock acceptance claim.

Only scalar username, email, password and password confirmation are accepted.
Username/email are explicitly trimmed, password is never trimmed. Username is
1–100 UTF-8 bytes without controls, email is at most 254 bytes with syntax
validation. Password is 12–72 **bytes**, no control characters; confirmation must
match and the existing refused `defaultpassword` value is not allowed. The
configured Laravel bcrypt hasher runs before acquiring the writer lock. These
finite signup constraints do not retroactively change existing login passwords.

The writer hardcodes user/local, NULL token/department/supervisor, 8 daily hours
and zero carry-in. It does not use `LegacyUser` mass assignment or impersonate an
administrator. Existing model and audit triggers run in the same transaction;
the fixed audit actor is `web:registration`, reason `Self-registration`. No new
password/hash audit fields, LDAP conversion, team membership or permission grant.

Duplicate username **or** email is checked inside the same writer lock. Both a
new insert and a duplicate return the same 303 redirect and generic login notice;
no field-specific availability response or account identifier is returned. This
is not a claim that timing or the existing login interface are enumeration-free.
A missing local admin gives a generic setup-not-ready 503. Invalid shape uses a
generic 400; infrastructure failure stays generic 503 /
unconfirmed, never success or a promised rollback. There is no automatic retry,
autologin, session identity switch, JWT issuance, credential flash or old-input
rendering. Never collect passwords in chat or enter them through a model-visible
automation surface.

Email is **not verified** and no email is sent. There is no SMTP dependency,
password reset, activation email or production identity-verification claim. The
ordinary user sees their own permitted application data only; administration and
team assignments stay with separately authenticated administration.

## NEW disposable registration profile

The existing preparation command gains only an explicit `--registration` mode.
It cannot be combined with LDAP preparation, select an existing DB/key/target,
upgrade an old manifest, reset a password or start a server. Existing v1 and LDAP
v2 paths, metadata and account-admission branches remain unchanged.

The new private `local-registration-web-fixture-*` directory has its own kind,
cookie, key and pristine source/backup. Its two fixed seed identities are
`registration.seed.member` and `registration.seed.admin`, distinct from v1/v2.
Their independent random password hashes are never returned or placed in the
manifest/logs. In particular the public source-constant fixture password cannot
log in as its seed admin. The descriptor contains the signup URL and **no seed
login credentials**. A real person's seed-admin control/bootstrap remains a
separate unsolved handoff, not silently granted by signup.

Pristine admission requires exactly those two fixed local seed identities. Target
admission additionally permits only ordinary local accounts with NULL privileged
assignments/token. It does not claim that a private-file row proves which endpoint
created it. Old fixtures are not retagged or weakened to accept extra accounts.

After separate action-time permission to create this disposable runtime, a user
can open the supported cloud-browser signup form, choose a username/email and
password privately, then sign in through the normal login form. BrowserAuth or a
supported same-browser user takeover must be used for reusable credentials; iOS
browser-only operation needs no terminal. No actual preparation, credential,
account, listener, browser signup/login or deployment is performed by this source
packet. Production enablement, first-admin setup and protected HTTP/browser
acceptance remain separate gates.

## Bounded source evidence

One invented disposable SQLite registration/duplicate/role/late-audit-failure
journey and syntax/static-preservation evidence accompany the packet. They do not
prove real HTTP headers, browser password-manager behavior, mobile rendering,
production TLS/proxy/session settings, concurrent load or end-to-end signup/login.
