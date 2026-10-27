# PHP 8.5 runtime upgrade

Scope: [#58](https://github.com/storminator89/Zeiterfassung/issues/58). The existing UI, routes, authentication, booking rules, database schema, audit triggers and API responses are retained. There is no Laravel migration in this change.

## Runtime and dependencies

The application now targets **PHP 8.5**. Docker and the existing CI fixture use **8.5.12**, the PHP security release published on 21 October 2026, from the official `docker.io/library/php` images:

- Apache: `php:8.5.12-apache-trixie`, index digest `sha256:7cd25eb48f9763dfeabe8fda3939897aca2001802fadeafa1b602f26c735ef96`.
- CLI: `php:8.5.12-cli-trixie`, index digest `sha256:635a4b998025ab269194639a5549c6fdcd9b4f88ec8cd761b092de8653d40663`.

Both references are digest-pinned in configuration. Review and update the version and digest together for subsequent PHP security releases. No third-party package repository is needed. Required extensions are PDO SQLite, SQLite3, mbstring, OpenSSL, ctype, fileinfo and iconv. The official images provide these. LDAP remains optional and is not included in the base images; an actual directory integration must be checked separately before enabling it.

PHP 8.3 is no longer supported by this configuration. `PDO::connect()` is available from PHP 8.4, but the declared and verified target is PHP 8.5. For a future framework adapter, preserve the `Pdo\Sqlite` connection subclass: a generic `new PDO(...)` does not expose `createFunction()`.

## Necessary compatibility changes

- Use `PDO::connect()` and `Pdo\Sqlite::createFunction()` for the existing `audit_actor` and `audit_reason` functions. The old PDO aliases are deprecated in PHP 8.5. Their callbacks, transaction handling and SQL triggers are unchanged. Existing migration/booking fixture connections use the same factory.
- Pass the CSV escape argument explicitly, keeping the previous backslash escape, delimiter, enclosure and output bytes. This removes the PHP 8.4+ default-argument deprecation without changing export format.
- Remove duplicate same-value German/English date constants and pass an empty string for nullable department/supervisor labels. Labels and empty-field output are unchanged. These are bounded warning fixes related to [CH-33 / #39](https://github.com/storminator89/Zeiterfassung/issues/39), not a claim that the entire issue is resolved.

## Verification and upgrade checklist

Before any deployment:

1. Keep the prior image/revision available, stop writes and make an offline backup of the **entire** private `APP_DATA_DIR`, including keys and SQLite sidecars. Follow [SECURITY.md](../SECURITY.md); do not run smoke checks against real staff data.
2. Build the new image with the existing Docker command. Check `php -v` and the required extensions in that image. No separate OPcache extension installation is needed for PHP 8.5.
3. Start against a disposable copy first. Check application start, login and core pages, a complete booking/pause/end flow with a safe retry, a known work-time calculation, owner/admin access boundaries, and consistent backup/audit/SQLite integrity. Do not add wording, selector or pixel assertions for this runtime upgrade.
4. Compare pre-upgrade records and keys, and restart once more to confirm idempotent startup. Do not accept warnings or partial booking persistence.
5. For rollback, stop writes, restore the prior image/revision and the matching full private-directory backup. Never overwrite a live SQLite database or keep two versions writing to the same volume. This runtime patch introduces no database migration of its own.

Local verification used the SHA256-verified official PHP 8.5.12 CLI image binary with isolated libraries, a private disposable database and sessions. All PHP files passed syntax checks. Coarse HTTP checks passed for startup, three account logins, core/admin page loads, CSRF and owner/admin boundaries, booking/pause/resume/end plus idempotent retry, the known 07:30 calculation/export, audit guards and readable consistent backup. Repeated PHP 8.5 startup preserved the database and both keys created with PHP 8.4.27 byte-for-byte. LDAP/hardware integrations and exhaustive suites were not run. A Docker client/daemon was unavailable locally, so a complete Apache image build/run is still required before deployment; the CLI proof is not an Apache-build claim. No new CI run was manually triggered and nothing was deployed.

## Primary sources

- [PHP 8.5.12 security release](https://www.php.net/releases/8_5_12.php) and [release metadata/checksums](https://www.php.net/releases/index.php?json&version=8.5.12).
- [PHP 8.5 incompatibilities](https://www.php.net/manual/en/migration85.incompatible.php) and [deprecations](https://www.php.net/manual/en/migration85.deprecated.php).
- [`PDO::connect()`](https://www.php.net/manual/en/pdo.connect.php), [`Pdo\Sqlite::createFunction()`](https://www.php.net/manual/en/pdo-sqlite.createfunction.php), and [`fputcsv()` escape parameter](https://www.php.net/manual/en/function.fputcsv.php).
- [Official PHP image source](https://github.com/docker-library/php/tree/f6048b5f6e3c2202ee082665a4ccc0d90de291a3/8.5/trixie) and [official-image tag catalog](https://github.com/docker-library/official-images/blob/master/library/php).
