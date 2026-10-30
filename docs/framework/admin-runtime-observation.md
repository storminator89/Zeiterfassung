# #66 / #79 — explicit local-Admin operational observations

Source basis: actual Main `bc1ed8d041226ee4aa5d9617d6088e6deba5dadc`, tree
`f200fe53a3982b5c55fdd2d1acc107d4120df0f7` (30 October 2026).

The existing Laravel Administration gains a collapsed existing-style card.
An explicit link observes free storage on the **already open main database's
volume**. A second explicit link additionally observes enabled terminal contact
freshness. Ordinary Admin GET and POST/error redisplay do not perform these
observations. There is no background request, refresh timer, new route, asset,
service, configuration flag, scheduler, write, repair or admission change.

The optional `AdminRuntimeObservationReads` capability takes only a boolean for
the bounded terminal aggregate. It accepts no account, device, path, backup,
threshold or repair selector. `LegacyChronoReads` uses the existing named idle
SQLite connection and canonical read snapshot, including its unchanged current
source/password/provider/generation checks. Within that snapshot the actor must
still be **local Admin**. Members, supervisors and directory Admins cannot read
the observations. Directory Admins keep their ordinary business controls but do
not see the card. A stale cached role cannot authorize the new read.

## What the card actually observes

- Free bytes come from `disk_free_space` on SQLite's already-open `main` database
  directory, never a supplied path. The path itself never leaves the helper.
  An in-memory/unknown/unmeasurable volume stays **unknown**, not zero or healthy.
  The fixed 128 MiB threshold matches the existing private CLI default; it is
  only an observation threshold, not a new writer/storage/admission rule.
  Bytes are displayed as exact decimal integers, without JavaScript or floating
  point formatting. The filesystem observation is not atomic with SQLite.
- Only the terminal-selected link executes `SELECT last_seen` for enabled
  devices, ordered by ID and limited to 1001 rows. At most 1000 are aggregated.
  No device ID, name, site, user, credential, pairing value or raw contact text
  is projected. The existing 90-second contact recognizer is unchanged. The
  existing private CLI aggregation is mechanically moved into one shared pure
  function so both consumers retain the same states/counts/valid-contact ages.
  Its SQL, thresholds, report shape, codes and exit policy are unchanged.
- Recent/stale/missing/invalid counts and newest/oldest valid ages concern only
  scanned devices. More than 1000 is explicitly **incomplete**; missing/future/
  invalid values never get an invented age. Zero enabled devices is stated as
  such, not proof of expected inventory or hardware function. Without terminal
  selection no terminal query executes.

The page labels these as observations, **not overall healthy/readiness**.
The UTC observation time is server-produced, not a support/request ID or an
operation receipt. No correlation ID is displayed or generated here. The
previously refused general About-dialog support-ID feature is neither copied
nor implemented: layout, common chrono dictionaries and correlation middleware
remain untouched.

## Boundaries and navigation

Selectors are exact raw GET strings: `runtime_observation=1` with optional
`runtime_terminal=1`. Arrays, whitespace, missing primary selection and other
values return a fixed 400 before measurements. The existing protected Admin
route/middleware remains the only entry. Local source is checked before any
optional read; canonical authority is rechecked inside it. Lost authentication
or authorization retains the protected failure, not cached observations.

A missing optional adapter, refused read or malformed receipt renders generic
**unavailable** with HTTP 503, never empty/healthy values or exception details.
The read helper requires its caller's query-only snapshot and never owns or
joins another transaction. The DTO validates the bounded aggregate, derives
storage consistency and discards unknown fields. Responses remain no-store.

The collapsed panel can be opened/closed without executing anything. Each
selected link/Reload/Back reads a fresh state: not a frozen multi-request report.
Links retain the chosen language but intentionally do not carry user search,
device/path or mutation inputs. Existing forms, outcomes, CSRF, credentials and
Admin operations remain unchanged. Other Admin sections are separate reads,
not one atomic whole-page report. No Native compatibility page is changed.

The card explicitly leaves backup age/restore integrity, login, writer, API,
LDAP, TLS, log delivery, hardware and operational readiness **unassessed**.
Use the existing private `docs/framework/operational-diagnostics.md` runbook and
operator CLI for their separately allowed observations. Nothing is automatically
deleted, replayed, re-paired, activated or reset after a warning. No new backup
file/input, host policy or alarm delivery is introduced. #66/#79 remain open.

## Bounded evidence and remaining acceptance

PHP 8.5.12 / SQLite 3.46.1 / locked Laravel 13.38.0: one coarse journey uses the
actual original-schema migrators and anonymous Memory data, canonical Reader,
actual AdminController/DTO and compiled **whole existing Admin view** in DE/EN/ZH.
It checks idle/no-scan, strict selectors before read, storage-only/no-device-query,
enabled-only mixed contacts, local/directory/member/supervisor scope, fresh role
loss, caller-owned transaction preservation, missing optional adapter, generic
malformed/faulted receipts, protected failures and POST redisplay/no-observation.
Over-1000 data stays bounded/incomplete, unknown fields/raw private canaries do
not escape, exact byte formatting includes integer maximum, source rows/schema/
audit remain unchanged by reads. The extracted aggregation's results are exactly
equal to the original CLI block for mixed, boundary, empty and truncated inputs.
An additional empty isolated throwaway SQLite file in the same journey exercises
actual free-volume measurement without path disclosure; it is removed afterwards
and is **not** a backup or runtime-admitted installation.

The first harness run used a generic PDO subclass incompatible with the existing
PHP 8.5 fixture UDF API. Correcting the executor-only subclass to `Pdo\\Sqlite`
made the unchanged source journey pass; the initial failure is retained in the
private support evidence. No product workaround or additional test suite.

The guard is a fake RequestGuard and the connection a fixed named fixture;
unchanged production provider/generation composition is source-reviewed, not
newly executed. No complete application/NEW-V5 admission, login/CSRF/HTTP,
browser/visual, concurrency/load, real account/credential/key/data, backup,
hardware, alarm, host/Docker/TLS or production/cutover acceptance is claimed.
The browser/operating acceptance remains separate; no manual CI dispatch.
