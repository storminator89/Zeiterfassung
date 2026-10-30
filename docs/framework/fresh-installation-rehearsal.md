# #79 / #82 — NEW-V5 installation Kernel rehearsal

This is one coarse, explicit developer journey, not an installer or a deployment
command. It exercises the real NEW-file producers and current Laravel providers,
routes, middleware, encrypted file sessions, account/auth services, admitted
file-backed SQLite writer and readers. No Memory-only auth/writer substitute,
session injection, precreated user or CSRF exemption is used.

## Concrete startup failures corrected

On actual Main `3a5cef6dd4e1980745dce70ce806b280101ae95e`, the first complete
FreshWeb boot refused the already published `main.js`: its exact integrity pin
was stale. After that pin was aligned, the Dashboard registrar likewise refused
the already published `chrono-calendar.js`. Default Web composes Dashboard before
registering the login route, so this also prevented NEW first-user setup.

Only the two fixed SHA-256 values are aligned to the existing reviewed Main
blobs; no JavaScript, validator or admission rule changes:

| Asset | Current source / public identity | SHA-256 |
| --- | --- | --- |
| `main.js` | Native and Laravel copies both Git blob `78ac29b5d02ed7a1327e06cfbcde391efcd9d669`; [merged #367](https://github.com/storminator89/Zeiterfassung/pull/367), 9767 bytes | `2f9ed4299e2acca7b8b472c3099f5b14e499ed221d33b4be77ecabfade9f5ae3` |
| `chrono-calendar.js` | Existing Laravel renderer Git blob `5871f9be966b6c22d31e235e929c6f6d6f282659`; [merged #386](https://github.com/storminator89/Zeiterfassung/pull/386) | `67a0e4ce674c06297e2ae2603d0a681f21290d56b0c52c300de89c7e19ea39e9` |

All other shared, FullCalendar and Chart.js asset hashes, regular/readable-file
and symlink refusals stay exact. No dynamic hash discovery, mutable allowlist,
fallback, source copy, external download, library upgrade or real profile
activation is introduced. The journey also confirms that an altered private
copy of `main.js` still refuses boot before routes or a database connection.

## Deliberate disposable invocation

Use the supported PHP 8.5 / locked Composer installation in an isolated developer
checkout, with default-closed profiles and no real key/cache environment:

```sh
cd framework
php tools/rehearse-fresh-installation.php --disposable-new
```

The flag is required. There is no target/instance/source/account/key argument.
The tool refuses root, inherited runtime/integration profiles and key or cache
overrides. It does not load the checkout's `.env`. It allocates its own random
0700 temporary parent, redirects only scratch storage/manifests and environment
lookup there, then runs actual `chrono:prepare-fresh --local-browser-first-admin`
and `chrono:application-key` against that NEW empty cohort. The generated key
and random chosen synthetic password are used only in this disposable process;
they are never printed, placed in command arguments or copied into the checkout.
No existing account, data file, operator config, key or TLS material is accepted.

The one 21-request Kernel journey covers:

1. Login redirects to NEW first-user setup; rendered session CSRF is used.
   A tokenless POST refuses with 403; valid setup creates the first Admin and
   closes setup, without a token or autologin. Closed setup returns 404.
2. Normal password login, actual protected Home, and Start with existing optional
   metadata. Repeating the same committed event returns the same receipt;
   fresh status supplies the exact binding for Pause / Continue / End.
3. Protected History and CSV, logout, then unauthenticated status 401. A fresh
   application and cookie jar can log in again against the persisted cohort.
4. Closed admitted reads confirm one Admin, four events, one completed session
   and pause, no open session, foreign-key/integrity checks, and unchanged key
   and admission metadata. Every request boots a new original application.

Only bounded JSON counts/booleans/versions are output, not response bodies,
cookies, credentials, exception details or private paths. Connections end before
the exclusively owned fixture tree is removed. Failure or unconfirmed cleanup
has nonzero exit and does not claim a pass; do not reinterpret it as a confirmed
rollback, permission to reset an installation or automatic retry instruction.

## Limits

These are real file-backed, full-Kernel requests with a simulated local HTTPS
request origin, **not** network HTTP, trusted TLS, Apache/Docker/mount identity,
JavaScript/browser/mobile/UI, OS-worker restart, concurrency/crash/restore,
LDAP, API or hardware acceptance. Secure-cookie attributes are observed, not a
certificate/device reachability proof. No listener, real deployment, Host or
security configuration is changed. The original routes, owner/role/generation,
session locks, CSRF, writers, formulas, schemas and default-off flags stay intact.
The existing [Docker installation](laravel-default-installation.md),
[SQLite writer proof](sqlite-write-proof.md) and all remaining #79/#82 gates
remain separate. No held/denied package is included and neither issue is closed.
