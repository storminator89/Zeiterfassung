# Manual security review — 2026-10-25

Repository: `storminator89/Zeiterfassung`; original commit `54c0adb36c08c09de42a4e34b599e903315fdcef`; local branch `security/manual-hardening`.

This is a manual source review with local HTTP regression tests, not a completed Codex Security plugin scan. The user explicitly authorized manual review after the plugin resources proved unavailable. No production data or remote target was tested. At completion of the original local review, no GitHub changes had been published; later integration is recorded by Git history.

## Findings and changes

| Severity | Original behavior / evidence | Implemented change |
| --- | --- | --- |
| Critical | `download.php` has no authentication. Locally reproduced HTTP 200 containing SQLite bytes without login. Direct `assets/db/timetracking.sqlite` also returned the database. | Private data directory outside application web root; refuse legacy public file; authenticated admin-only consistent snapshot; Apache and local-router file restrictions. |
| Critical | `api.php` signs/verifies JWTs with literal `your_secret_key`. Locally reproduced a forged token for user 1 accepted by `/api.php/users`. | Per-installation private random key, constant-time signature validation, strict algorithm/claim/expiry checks, one-hour expiry, live account and password-version checks. |
| High | Administration, settings, time mutation and database import lack CSRF checks. A local POST without token actually inserted an admin department. | Shared server validation before mutations; hidden tokens on forms and same-origin CSRF headers for fetch/XHR. Logout is POST plus CSRF. |
| High | `header.php` assigns arbitrary GET language to an include path before authentication; `settings.php` similarly accepts arbitrary POST language. PHP files outside `languages` can be included via `../`. | Fixed language allowlist for both paths and session values; include paths no longer derive from unrestricted input. |
| High | LDAP sync assigns every imported account `defaultpassword`; login copies LDAP passwords into reusable local hashes. | Random non-reusable local hashes, rejection of old default password, explicit directory-account marker, no local fallback for marked LDAP accounts. Existing LDAP users require re-sync during migration. |
| High | Stored API values reach HTML: raw pause in time tables, location/username in supervisor template literals, user/work-entry values in extensions. Attribute encoding alone was also insufficient in the admin department inline JavaScript handler. | Escape at HTML/JavaScript output boundaries; safe JSON in inline scripts, API type validation, corrected department argument encoding. HTTP tests verify escaped output; no browser exploit execution was claimed. |
| Medium | Every API caller can list all users/emails/roles; extensions incorrectly pick the first user. | `/users` preserves its array shape but returns only the authenticated profile. |
| Medium | Session roles persist after demotion/deletion; API tokens remain usable after password change/deletion. | Refresh current role/account each request; bind sessions and JWTs to current password hash. Verified immediate demotion and password-change invalidation. |
| Medium | LDAP passwords use a fixed source-visible encryption key; LDAP credentials may travel without TLS; LDAP filter/DN use unescaped username. | Private random encryption key, authenticated AES-GCM for new values, DN/filter escaping, mandatory LDAPS/StartTLS. Real directory connectivity remains untested. |
| Medium | Database restore accepts any upload named `.sqlite` and replaces the live file. | Size/integrity/schema checks, reject triggers/views, transactionally copy approved tables; failed validation preserves data. Tested valid restore, invalid bytes and malicious-trigger backup. |
| Medium | No login throttle and unrestricted first-admin setup. | Shared persistent IP/account attempt counters; private setup token, minimum initial password length, serialized first-admin creation. |
| Low–Medium | User-provided CSV cells can become spreadsheet formulas. | Prefix dangerous spreadsheet cells; verified `=1+1` exported as text. |

Additional hardening: HttpOnly/SameSite session cookies, strict session IDs, HTTPS-dependent Secure cookies/HSTS, no-store, nosniff and anti-framing headers, generic uncaught PHP error output, 366-day bound on bulk leave insertion. The Dockerfile originally attempted to chown a nonexistent database file; it now builds and starts with a separate data volume. An orphan HTML block before authentication was removed. Optional holiday fetch failures now time out and log instead of aborting the page.

## Coverage and evidence

Reviewed all original top-level PHP files for entry points, includes, authentication, role/record ownership, dynamic SQL, session handling, file access, API routing and output; reviewed application JavaScript and both extension popup/manifest flows. Languages, static resources, Swagger configuration and deployment files were inventoried. Most database operations already bind parameters; dynamic update column names use explicit allowlists. Supervisor queries scope staff to the requesting supervisor. No claim is made that source inspection proves the absence of all SQL injection or logic flaws.

Validation uses PHP 8.3 CLI server and the built PHP 8.3 Apache image on loopback only. Main suite covers protected resources, malformed/forged JWTs, CSRF, record isolation, login, working pages, API profile/create, admin changes, backup download, CSV/HTML output, account revocation and rate limiting. Separate tests cover backup import and first-admin setup. PHP syntax and changed JavaScript syntax are checked. Final results: 54 main HTTP assertions passed on both the PHP development server and Apache; four additional import assertions passed on both; three private-setup assertions passed on a separate fresh instance. All application/language/test PHP files passed syntax checks, as did the three changed JavaScript files. See test scripts for reproducible assertions.

## Remaining limits and operational risks

- Bundled/minified third-party libraries and externally fetched CDN scripts have not received a complete CVE/SBOM or supply-chain audit. No Composer/npm lockfile exists for the application. Pin/self-host dependencies and remove runtime Tailwind CDN compilation as follow-up work.
- CSP currently blocks framing, objects and hostile base URLs; it does not yet forbid inline scripts. Existing inline handlers and CDN dependencies require a separate frontend migration for a strict script CSP.
- No real LDAP directory, production reverse proxy, production HTTPS, browser-extension installation or full graphical browser interaction was tested. UI escaping was checked in responses/source, not by running an exploit in a browser.
- Directory synchronization/migration must be verified on actual deployment data. Runtime LDAP extension is not present in the base image and is reported explicitly when needed.
- Existing code still has presentation/date warnings for empty datasets; errors are not displayed to visitors. Broad time-accounting/business-rule correctness was not part of the security remediation.
- Single-node SQLite and application throttling are not volumetric DoS protection. Production should add proxy request/body limits and independent backups.
- No assurance of complete security and no conclusion about past compromise. If the original instance was public, database/password/LDAP exposure must be assessed separately.

## Follow-up: mandatory attendance, schedules and audit history

The optional terminal implementation is extended with mandatory web/API attendance and actual breaks, effective-dated weekday schedules, schedule-based absence credit, approved cancellations, and a database-enforced append-only journal. Prior baseline rows are labelled as such; past edit history cannot be reconstructed. Direct editing/deletion and partial browser restore are disabled for every installation. Security regression expectations have been updated to require these restrictions. See [docs/PRAXISFUNKTIONEN.md](docs/PRAXISFUNKTIONEN.md) for exact migration behavior, test coverage, and the trust boundary of the audit journal. This extends the earlier security review rather than claiming certification or production deployment validation.

Local follow-up validation: the final Apache build passed 54 security, 57 terminal, 46 practice and four database backup/restore assertions. PHP clock/migration tests passed six and nine assertions respectively (176 total). Chromium additionally passed web attendance/absence/history/model/correction flows and mobile layout checks. These tests use isolated synthetic data, not a production practice installation.
