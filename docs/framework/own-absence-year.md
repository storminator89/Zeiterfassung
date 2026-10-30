# Own recorded absence-credit year review (#43 / #79)

Source basis: Main `64776b2fdc67d7634ea3834a0f28f8d1f6dc489b`, tree
`c0d69d3325182608ec4ad039887a8b460ec1634f`. This is a complete bounded **read
journey for current stored credits**, not a leave entitlement/approval account
or closure of #43/#79.

## User contract

The existing Native and Laravel Dashboard details/table patterns expose an
explicit “load yearly review” link. No extra year read occurs before that action.
`absence_year` selects 1–9999; `absence_page` is a positive decimal page number.
Invalid shapes show a fixed error and no invented empty/current result. Pages
contain at most25 records, clamp to the last page, and allow every selected row
to be reached. Year changes reset the page. Independent calendar/holiday
selections are retained; existing cards, calendar, forms and exports are not
filtered or changed by this review.

Vacation, sickness, public-holiday and other stored credit projections are
separate. The explicit stored absence kind takes precedence, with the existing
legacy description fallback. Neither raw notes nor unknown stored kind text is
rendered. Dates are Berlin report start dates, only after the existing strict
legacy timestamp assessment. Existing canonical completed **net report minutes**
are used, not `planned_minutes`, a new model lookup or an entitlement formula.
Zero/cancelled credit rows remain visible but do not count as positive credit
days. Day counts mean distinct dates, not full-day/half-day entitlement units.

“Through today” and “after today” are recorded-date groups, **not taken versus
approved leave**. There is no approval promise, pending-request model, remaining
balance, carryover, expiry, half-day conversion, legal rule or source rewrite.

## Quality, scope and data minimization

Full own context precedes year/page filtering. The existing work/absence
conflict detector is reused; multiple positive credit projections on one day
also require clarification, never silent double counting. Unsupported/ambiguous
or nonexistent local times, invalid/open credits, oversized interpretation
fields or unassessable work context withhold all year minute/day totals.
Invalid work spans are conservative across all years: a bad prior-year end
cannot be silently assumed to stop before the selected year.
Individual assessable report durations remain source facts, not final totals.
Unplaceable credit dates appear conservatively in every selected year with an
explicit warning, not a fabricated year. Raw record counts remain counts.

Native uses the already-authorized full own header snapshot. Laravel has a new
optional read interface; its existing named read snapshot refreshes current
identity/role/lifecycle before a fixed `WHERE user_id=?` query. The owner never
comes from a form/query; even Admin sees only their own records. Authorization
loss is rethrown, not hidden behind cached own data. Unsupported dormant readers
do not gain a new requirement or implementation obligation.

Laravel materializes bounded interpretation columns only: ID, owner ID,
start/end25bytes, pause20bytes, kind32bytes and a fixed legacy-kind allowlist.
Bounded TEXT/BLOB timestamps remain original PDO strings: the same strict
PHP timestamp assessment used by Native decides assessability, without SQL
text coercion, a chosen date or an invented NULL for valid bounded bytes.
No note, location, original JSON, account data, token or PDO escapes. The DTO
contains fixed kind/state keys, dates, decimal record IDs and counts/minutes.
All Native/Blade strings are escaped. Existing own record-selection correction
links are reused; no queue-record-ID filter or denied correction patch is added.

Rows returned to the page are bounded, **not total SQL scan/CPU/history memory**;
full own conflict context remains necessary. No new persistence, index, schema,
writer/receipt, session rule, API, route, external service, host/runtime flag,
asset or dependency is introduced. All held #66/queue-filter/schema/other
packages remain excluded.

## Evidence and limits

A small coarse synthetic snapshot/render journey and actual disposable NEW-V5
Kernel own-year navigation are required before publication, together with
necessary syntax/diff/source-preservation checks and independent review.
Their actual outcomes, runtime and checked commit/tree are recorded in the PR.
This document is not itself a test pass. No browser/TLS/proxy/hardware,
production data, concurrency or legal/betriebliche acceptance is implied.
Current projections may change after separately authorized corrections; this
view is not a reproducible month closure or immutable receipt.
