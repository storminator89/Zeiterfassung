# Secure operation and migration

This application stores staff identities, work records, password hashes and optional LDAP credentials. Administrators may manage accounts and restore backups. Users may access only their own records; supervisors may view their assigned staff. Treat the database, backups and private keys as confidential.

## Start with Docker

Build with `docker build -t chrono-hardened .`. Use a persistent private volume:

```sh
docker run -d --name chrono -p 127.0.0.1:8080:80 \
  -v chrono-data:/var/lib/chrono chrono-hardened
```

A new database has no default accounts. To create the first administrator, temporarily pass an independently generated random `APP_SETUP_TOKEN` via a protected environment file (`--env-file`). Open `/register.php` and enter that token, a valid email and a password of at least 12 characters. Recreate the container without `APP_SETUP_TOKEN` after setup, retaining the same volume. Registration closes after the first account. Do not put setup tokens or data files in source control.

For a local PHP installation (PHP 8.5, PDO SQLite, SQLite3, mbstring, OpenSSL, ctype, fileinfo and iconv):

```sh
APP_DATA_DIR=/absolute/private/chrono-data php -S 127.0.0.1:8080 router.php
```

Always use `router.php` with the development server; it is not a production server. The Docker image uses Apache and enables `.htaccess`. Other web servers must deny dotfiles, database/key/backup files, `tests/`, `languages/`, and direct requests to config/security/functions/router PHP files. Disable directory indexes. The web process needs write access only to private data and PHP sessions, not application source.

Terminate production HTTPS at a trusted reverse proxy. Set `APP_HTTPS=1` only when all browser access actually uses HTTPS and plain HTTP is redirected at that proxy. This enables Secure cookies and HSTS. Do not trust arbitrary forwarded headers. Login throttling uses the actual peer IP, not untrusted X-Forwarded-For; a shared reverse proxy will share its IP budget (60 attempts/10 minutes), in addition to 15 attempts/account/10 minutes. Configure upstream rate limits for larger installations.

## Upgrade an existing installation

1. Stop application writes and make an offline database backup. Keep the old version available for rollback; do not run it publicly during migration.
2. Move `assets/db/timetracking.sqlite` and any SQLite sidecars outside the web root to `APP_DATA_DIR` (Docker: `/var/lib/chrono`). The app refuses startup while the legacy database remains in its old public location. Ensure it is no longer downloadable through any old deployment, alias, backup or proxy cache.
3. Start the new version with that private directory. The small `users.auth_source` schema addition is automatic. Private random JWT/encryption keys are generated there, never in source. Existing sessions and old API tokens are intentionally invalidated; extensions need a fresh login, and tokens now last one hour.
4. Re-enter LDAP settings: encryption previously used a public constant. Old encrypted LDAP values are intentionally not decrypted with that constant. Use LDAPS or successful StartTLS and install PHP's LDAP extension before enabling LDAP; the supplied base Docker image does not include LDAP. Re-synchronize LDAP users so `auth_source='ldap'` prevents local-password fallback. The known old `defaultpassword` login is rejected. Directory-managed accounts cannot use local-password API login; an administrator can still generate their own API token. Verify your directory workflow before enabling it for users.
5. If the old application was internet-accessible, investigate access to `download.php` and `assets/db/`. Rotate LDAP service credentials and reset potentially exposed user passwords; source fixes cannot undo past disclosure.

Back up the entire private data directory offline, including keys. The browser database download is a consistent SQLite snapshot and requires a current administrator session, but deliberately excludes private keys. A database-only restore from another installation cannot decrypt that installation's LDAP values. Partial web import is disabled because it would discard the audit trail. Restore the complete private directory offline as described in [docs/TERMINAL.md](docs/TERMINAL.md).

## Verification

Tests are destructive **only to disposable test fixtures**. Never point them at production data.

```sh
APP_TEST_MODE=1 APP_DATA_DIR=/private/test-data php tests/seed.php
python3 tests/security_http.py http://127.0.0.1:8080 /private/test-data/timetracking.sqlite
python3 tests/import_http.py http://127.0.0.1:8080 /private/test-data/timetracking.sqlite
```

The seed script refuses populated databases. Fixtures create `auditadmin`, `audituser`, `auditother`, with password `LocalAudit!Password2026`; use only in isolated local tests. Set the same `APP_DATA_DIR` on the server and seeder.

See `SECURITY-REVIEW.md` for review scope, findings, validation and remaining limits.

## Optionales Terminal-Modul

Die Terminal-API verwendet separate signierte Geräteanfragen und keine Browsersitzungen. Einrichtung, Freigaben, Sperren, Grenzen und vollständige Backup-/Rollback-Schritte: [docs/TERMINAL.md](docs/TERMINAL.md). Der alte partielle Browser-Import ist immer gesperrt; die gesamte private Ablage muss gemeinsam wiederhergestellt werden.
