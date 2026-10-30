# #41/#79 — Stored holiday inventory in Laravel

Source basis: Main `931505f0633757a60bb82b9c8194bef51911668f`, tree
`184e6a4aec6ee37da017828a41e383a17db0e5bf`.

The Native dashboard already has a compact yearly inventory and an explicit
new-tab link before absence credits. Laravel reuses the exact existing
`holiday_inventory_year` and `holiday_inventory` helpers, not an importer or a
new holiday/calendar/accounting implementation. The optional read capability
delegates inside the unchanged canonical authenticated Read snapshot; current
actor/source/version/V5 generation and named-connection gates remain intact.
The calendar's already-readable stored global holiday data is not a personal
or arbitrary-subject selector. No credentials or account objects leave the read.

## Small display, no completeness claim

A collapsed details panel offers years 1–9999, at most 366 escaped stored
date/name rows, and the same Native notices. A 367th row or malformed dates
marks the display as limited. A missing year means no stored entries, not no
holidays. Plausible entries do not attest completeness, correct region or source.
The historical Baden-Württemberg source note is not provenance for every row.
Invalid year input has a visible fallback to the current server Berlin year.
A failed read is unavailable, never an empty/clean result; auth failures retain
the protected response. Nothing imports, repairs, refreshes, writes or confirms
the selected year. Existing incomplete holiday data remains an open #41 concern.

The ordinary GET year form preserves the selected calendar week. A UI-only
`holiday_inventory=1` marker opens the default-year panel without inventing a
year in the template; it grants no read/write permission. Existing calendar
navigation, events, metrics, mount/fallback/JS/assets, DashboardPage and all
providers/routes/Runtime/writers stay unchanged. The inventory is its own
authorized snapshot, not a claim that all existing dashboard reads are atomic.

The link from the existing absence form is shown with the existing Dashboard
navigation capability, opens a new tab with `noopener noreferrer`, and performs
no submit/reload of the original form. No form field, state, handler or client
storage is changed. Actual browser/multitab acceptance is not inferred merely
from these source attributes. All eleven existing Native labels are copied
exactly in DE/EN/zh; one additional fixed unavailable label is display-only.

## Boundaries and evidence

The stable ChronoReads interface and Runtime remain unchanged; no optional read
capability is an operating admission. No external source call, schema, secret,
role, CSRF, Native or calculation change. #60 Own-Open and #16 quality-warning
changes are preserved by the additive fresh-Main reanchor, as are all four
Calendar fallback labels and their existing mount flow.

Coarse actual-helper/read-adapter and compiled-Blade evidence uses invented
read-only Memory rows and fake transport, not real login/session/CSRF/HTTP/browser,
regional source validation, concurrency, production or positive V5 admission.
No new repository test suite/workflow, dependency, deployment or host/security
switch. Form values remain in the original document; no automatic replay.


## Bounded stored-year discovery (partial #41)

The same shared inventory helper now also returns up to the 20 newest recognized
stored year prefixes with row counts and an explicit truncation flag. Native and
Laravel place this inside the existing collapsed inventory panel. Fixed
Dashboard links preserve the selected calendar week; the original year input
still permits older/unlisted years. DE/EN/ZH labels are matched across both UIs.

These are counts of stored rows with a four-ASCII-digit nonzero year prefix,
not valid-date/region/source/completeness attestations. Malformed dates inside a
recognized prefix count as stored rows; other malformed prefixes and nontext
values are omitted and this limitation is visible. Empty means no recognized
stored years, not no holidays or clean data. Failed reads propagate, never
silently become an empty overview. The old selected-year rows/diagnostics and
read/write authority are unchanged. Two additive fields only; older optional
readers that do not supply them retain the old view without an overview.

All auth/source/version/generation/snapshot/connection, reader interfaces,
controller, route, import/refill/calculation and writer code remain unchanged.
The additional grouping SELECT runs in the existing Laravel read snapshot;
Native's already-documented lazyimport behavior is not repaired or newly
introduced. No new external call, confirmation, permission or runtime activation.

Source basis and bounded shared-helper/compiled-view evidence are documented in
`docs/native-holiday-inventory.md`. They are not actual App/auth/CSRF/HTTP/browser,
production, performance, concurrency or regional data acceptance. #41 stays
partial/open; no new repository test suite or manual CI dispatch.

## Omitted malformed year prefixes — partial #41

On actual Main `d213531db6e93a07a2d884050a370edbd6899ed6`, tree
`8cfef884fd044b6d0f661aa6c710361ee243ec60`, the shared read-only helper adds
`unassigned_year_rows`: a COUNT of rows outside the exact recognized text-prefix
predicate (0001–9999 followed by `-`). The existing inventory panel shows a
localized count-only notice when positive. It never exposes malformed originals,
guesses a year, repairs data or changes accounting. The count covers all years,
not only the selected year or the 20 listed years; invalid dates *inside* a
recognized year still use the old year's diagnostics. NULL/BLOB and 0000 remain
unassigned. An absent field in an older optional Reader DTO shows no new notice.

The unchanged authorized snapshot owns the extra fixed SELECT; all old DTO values,
queries, reader/service/controller/auth/version/generation, source gates, writers,
schema, routes and Runtime stay unchanged. A read error propagates, never becomes
zero or healthy. No regional/source/completeness/valid-calendar attestation.
See the Native inventory note's corresponding section for exact coarse evidence
and limits. #41 remains partial/open; no real-data inspection, new testsuite,
manual CI dispatch, runtime activation, browser or production acceptance/deployment.
