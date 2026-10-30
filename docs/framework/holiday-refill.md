# #41 — Controlled BW holiday refill in the existing Laravel Admin page

Source basis: actual main `e8f642ca675d6c48fec1db55a7050104ef303ba1`, tree
`e2ac408f593fabb0d33cb7d28a0521dba895b395`. The existing yearly holiday inventory
remains unverified. This addition is a usable, explicit Admin POST operation,
not a dormant helper, new page, importer on GET, external service or runtime switch.

## Operator flow and deliberate scope

1. Local Administration opens the compact **Feiertagsbestand ergänzen ·
   Baden-Württemberg** section in the existing `admin.php` layout.
2. Inspect the stored year using the already-existing Dashboard inventory.
3. Independently download and check the full BW JSON response at
   `https://feiertage-api.de/api/?jahr=JAHR&nur_land=BW`, paste it, select its year,
   and explicitly confirm BW applicability and calculation effects.
4. The existing CSRF-protected POST admits only this fixed command. Under the
   original operation-scoped IMMEDIATE writer lock, current local Admin authority,
   captured web source/version and V5 generation are rechecked. No arbitrary role,
   PDO, path, SQL, URL, source fetch or account selection comes from the form.
5. Compare the entire existing selected-year inventory before any insert. Every
   existing date must match one incoming statewide date **and name** exactly.
   A conflicting name, extra/malformed date or duplicate stored date refuses the
   whole operation for manual review. Nothing renames, deletes, replaces or fixes
   originals. Only absent dates are inserted in one transaction.
6. Confirmed commit redirects to the same Admin card with a minimal actor-bound
   one-use receipt and link to the saved year. No raw JSON, hint, source name or
   draft is echoed/flashed. Unknown outcomes show fixed 503 guidance, not a safe
   rollback claim or automatic retry. Identical payloads after success are no-ops.

The [source's primary usage documentation](https://feiertage-api.de/) defines
`jahr` and `nur_land`; BW is its Baden-Württemberg selector. Its
[actual BW response shape](https://feiertage-api.de/api/?jahr=2019&nur_land=BW)
contains both dated and null-dated conditional entries. The historical application
policy remains: nonempty hints are omitted and counted, not interpreted as general
statewide holidays. A null date is accepted only for an omitted hinted entry.

## Validation, originals and accounting

- Raw JSON is bounded to 128 KiB, depth 8, and 366 outer entries. It must be a
  nonempty object of named `datum`/`hinweis` objects, no unknown fields. Imported
  dates must be canonical valid dates in the explicitly chosen year 1–9999.
- Duplicate/escaped-equivalent JSON properties and duplicate imported dates
  refuse the request, rather than accepting decoder last-value-wins. The lexical
  scan uses possessive tokens and fails closed on PCRE failure. Names, hints and
  field types are bounded; all ordinary HTML output remains Blade-escaped.
- Full validation is before resolving a writer and repeats inside its lock.
  Existing conflict inspection completes before INSERT; plain INSERT rather than
  IGNORE/REPLACE ensures constraint/audit failures roll back the whole delta.
- Existing original rows and IDs remain exact. Original `Feiertage` INSERT audit
  triggers capture trusted actor, timestamp and row values. The scoped fixed audit
  reason also includes BW/year and SHA256 of the **operator-supplied raw bytes**.
  That hash is correlation, not proof of transport, origin or full-year coverage.
  An all-existing no-op inserts no new audit row or import receipt in the DB.
- All freshly evaluated target calculations, including historical reporting
  periods, use the newly stored calendar; displayed targets/balances may change.
  Existing stored absence-credit minutes are **not** recalculated or repaired.

## Limits: #41 remains partial and open

No durable region/source/completeness/error-status model, regional selection,
other-state import, automatic source transport/retry, legal calendar attestation,
manual conflict editor or prior-credit recalculation. An operator can paste an
incomplete or false but structurally valid response; explicit confirmation does
not make it authoritative. The existing table is global and cannot represent
multiple regions. The inventory continues to label even imported rows unverified;
this does not certify the year or its original rows. Native `functions.php` and its
known lazy current-year import behavior remain unchanged. No schema, migration,
Runtime, source admission, session/auth policy, API/terminal, route, host, key,
account or deployment change. Existing Admin business/security controls stay exact.

## Coarse evidence and explicit exclusions

PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0: one ten-phase invented Memory journey
with original v1/v2 migrators/triggers, actual helper, actual Admin controller,
actual Reader and compiled existing Admin/partial templates. Future-year partial
refill, originals/other year/schema/audit preservation, identical no-op, whole-year
conflict, rollback on late INSERT failure, malformed/year/region/duplicate cases
(including 100 KiB duplicate value), source-shaped conditional null/dates, CSRF
form markup, postcommit receipt, role/mixed-field refusal and actual service's
pre-writer negative gates pass. PHP lint and compiled Blade syntax are checked.

The identity is a fake RequestGuard, the controller service delegates to the core
on Memory, layout is inert and session is ArraySession. This is **not** positive
writer-constructor/Runtime/V5 admission, real login/session/CSRF/HTTP/browser,
real upstream/year/region/legal validation, concurrency, TLS, load or production
acceptance. No file DB, real account, credential/hash/key/token generation,
external call, new repository test suite/workflow, CI dispatch, real-data import or
deployment was performed. Browser acceptance remains with the user.

## Non-saving precheck in the same card — partial #41

Prepared against actual Main `3efbd9134ae511c30a5fce657e858fb333a3e55c`, tree
`394064b77453486f012ca835ea2182deded3a850`. Local Administration can now choose
**Nur vorab prüfen · nichts speichern** before the existing Save action. The
original form/checkbox/CSRF and Save transport remain unchanged. Without JavaScript
or the optional read capability, the preview stays absent/disabled and existing
Save remains usable; no operating flag, route, provider or CSP policy is changed.

Only an explicit preview click sends the same-origin existing Admin POST with
`preview_holidays=1`, year/BW confirmation/raw JSON and CSRF. Its copied FormData
has `refill_holidays` removed; the actual form/confirmation are not changed.
The old action allowlist rejects mixed commands and arbitrary fields. Same current
local-Admin and authenticated named-read snapshot gates apply, including a role
change after a cached guard read. The new JSON branch never calls `page()`, issues
other Admin forms, consumes notices, updates preferences/session state or resolves
any writer. Standard HTTP/session/CSRF middleware itself remains unchanged.

The exact existing `chrono_holiday_refill_stage` validates the original raw JSON.
One fixed selected-year-prefix SELECT uses the old refill bounds/order and 367-row
ceiling. Only bounded date/name representations are materialized for comparison;
oversized originals cannot equal a canonical staged row and are counted as extra/
conflicting evidence, never echoed or repaired. The response lists at most 366
canonical **staged** dates/names as missing, identical, conflicting or not-compared; additional
examined existing rows and scan limitation are explicit. If the scan is limited,
unmatched staged dates are **not compared**, never claimed missing: an identical
canonical row may exist beyond the ceiling. Missing means established only by a
complete selected-prefix scan; counts are not global absence/completeness facts. Existing raw corrupt names/
dates, IDs, account data and the raw JSON are not returned in the result.

Counts describe this one source/selected-prefix snapshot, not all stored years or
unassignable values. `has_conflicts=false` is **not** write authorization, provenance,
BW applicability, legal validity or completeness. Even a structurally valid source
may be incomplete/false. The view never disables/enables Save from a preview or
turns a result into a write token. Save still receives/revalidates the raw inputs
and current originals independently under the original writer lock; intervening
changes can make it refuse. No old credit or stored calendar is changed by preview.

The result uses existing card/table/button classes and textContent-only DOM nodes.
Nothing submits on load, edits form values, persists a checkpoint, calls an external
source or retries automatically. Changed inputs invalidate old output; repeated,
late, detached-document and invalid responses cannot overwrite a newer preview.
The existing page nonce is reused without relaxing CSP. A blocked/unavailable script
leaves the non-submit preview button disabled, not a hidden write fallback.

### Coarse evidence and limits for this read step

PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0: one eleven-phase anonymous query-only Memory
journey with original fixture/audit, actual reader/controller and compiled card.
Missing/identical/conflicting/extra/oversized rows, original stage failures,
368-row scan limit and canonical existing row behind 367 malformed-prefix rows,
local/member/LDAP/deleted/stale role, mixed commands and absent
optional adapter are covered. Preview succeeds even without a Request session;
source/schema/change counters, session/notices and native globals stay unchanged,
with zero writer-port calls. Only a separate explicit old Save reaches its stub
once with unchanged fields. A broken read gives fixed 503, not a page or an empty/
clean result. The old optional view preserves its old Save markup apart inter-tag
whitespace. A real first candidate return-type defect on the new JSON branch was
fixed; an external BLOB fixture update was correctly refused by the original JSON
audit trigger and removed from the harness, never by changing product triggers.

Node 24.22.1 runs the actual compiled script in one invented-DOM/FormData/fetch
journey: explicit preview-only payload, no initial fetch/submit/form mutation,
escaping by textContent, input/newer/programmatic/detached stale responses, failures,
no retries and disabled fallback. PHP/compiled Blade/JS syntax and whole-tree
preservation/inverse are separate checks. No new repository suite/manual CI or
GitHub Approval review. No real accounts/credentials/file DB/server/configuration.
RequestGuard, Save port and browser transport are deliberate doubles: no full App,
SessionGuard/V5 admission, HTTP/CSRF/browser/CSP/layout, concurrency/performance,
upstream/region/completeness/legal or production/deployment acceptance. #41 stays
partial/open. Evidence: `/tmp/chrono-holiday-preview-main3efbd913-support/`.

Independent review caught an initial limited-scan false-missing assertion; the
amended comparison now returns not_compared for unseen staged days when capped.
The exact reproduced canonical-after-limit case and its client label are included
in the same coarse journeys (eleven server phases, nine client phases). No writer
or original was changed to satisfy this regression.
