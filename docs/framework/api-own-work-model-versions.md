# #68 — discover own stored work-model versions

Source basis: actual Main `2a07311bf5a79b1e88f7d146ba30978eab24ae9c`, tree
`02dca66b0bafd3cb900e809107745b36002fae85`. The existing `GET /api.php/work-model`
returns one effective version for a known date. `GET /api.php/work-models` now
lets a client discover stored historical/future change dates without guessing
a date, reading other people, or using an administrative model list. It is a
functional Native and optional Laravel read route, not a writer or activation.

## Bounded own-version contract

- Bearer identity supplies the owner. Admin/Supervisor tokens also see only
  their own versions. No owner, user, role, model ID or date selector is accepted.
- Raw PHP query accepts only the existing `limit` and `offset` pagination:
  default limit 100, maximum 1000, zero allowed; offset 0–2147483647. Omitted
  offset is metadata null and skips zero. Leading-zero decimal strings retain
  the existing pagination contract. Invalid arrays, whitespace, fractions,
  overflow, final LF or unknown fields produce JSON 400 before the read.
  Repeated names retain PHP `parse_str` behavior, not a new duplicate policy.
- `data` is a list of `{model_id, valid_from, weekday_minutes}`, newest stored
  `valid_from` first, then exact ID descending. IDs are positive signed64 decimal
  strings. Seven integral minutes are Monday through Sunday, each 0–1440.
- The exact existing stored-row parser is shared with the single-date route:
  valid canonical date, original plain integral lexemes (including `.0` from
  the original trigger), no float rounding/coercion, raw JSON/reason/author/
  reviewer/owner/account/audit fields. The single-date selection, outputs and
  errors remain unchanged. Future rows are discoverable, not activated early.
  Same-date versions are all stored facts; the effective route still chooses
  only the greatest exact ID. No invented end date or activation history.
- `meta`: `total`, `filtered`, `limit`, `offset`,
  `order:valid_from_desc_id_desc`, `time_zone:Europe/Berlin`, `scope:own`,
  `holiday_adjustment:not_applied`, `legacy_fallback:not_returned`.
  Count and page share the existing read snapshot. The count includes all own
  stored versions, but only selected rows are validated. A malformed selected
  row refuses the entire page with JSON 503 `Work-model versions unavailable.`;
  no partial page or exception details. A malformed nonselected row is not
  certified by a successful page, and limit zero validates no stored row.
- Empty list means no selected stored versions, not no work, zero target,
  entitlement or a calculated legacy fallback. No target/holiday/absence/balance
  calculation, schedule rotation, new work model, notification or approval.
- Responses are no-store. Another poll reads a fresh current snapshot; inserts
  between pages can shift offsets. This is not an immutable export, audit feed,
  complete history or source/completeness/operational attestation.

## Existing boundaries and limited evidence

Native retains its JWT/password-version guard and owns a read transaction.
Laravel adds a separate optional read capability next to unchanged `ChronoReads`
and `OwnApiWorkModelReads`, using the same existing named authorized snapshot.
An older adapter without the new capability returns 503, never an Admin fallback.
Existing Laravel API admission/generation/operating-policy, guard restoration,
raw method/URL/OPTIONS/HEAD handling and routes' common middleware stay intact.
No provider, writer, schema, migration, dependency, UI/asset, runtime, account,
key, host/security or deployment changes. #49's conditional shift-planning
request is not implemented or declared authorized by this read route.

Coarse PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0 evidence uses one anonymous
query-only Memory journey, original schema/migrators/initial-model trigger/audit,
actual helper, Reader/controller/router/wire/RequestGuard with a deliberate
identity double, and the extracted actual Native handlers. Own user/Admin/
Supervisor, original integral trigger minutes, historical/future/same-date
versions, exact large/MAX IDs, page bounds/zero/offset/unknown raw selectors,
normalizing Request bag, no private-field leakage, empty/no-source cases,
selected malformed-row all-or-nothing failure, unchanged effective-version
contract, fresh later poll, generic errors and read-only source/schema/counters/
guard/transaction/session/timezone preservation are checked. Necessary PHP/JSON
syntax and complete-tree inverse/replay/cold bundle preservation are separate.

This is not a real JWT/password/generation/API operating-policy/positive V5
admission, full App/bootstrap/HTTP/CSRF/browser/extension, concurrency/large-data/
production/performance acceptance. No real accounts, credentials, keys, file DB,
new repository testsuite/manual CI dispatch, GitHub Approval review, runtime
activation, deployment or notification service. #68 remains partial/open;
scoped outgoing notifications/dedupe/retry and actual integration remain separate.

The first temporary harness attempted fixture cleanup with DELETE; the original
append-only model trigger correctly refused it. Only the harness was corrected
to use distinct additional fixture append IDs. No product trigger, writer or
append-only boundary was changed or disabled.
