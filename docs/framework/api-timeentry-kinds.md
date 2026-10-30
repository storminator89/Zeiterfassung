# Own raw time-entry kind filter — partial #68

The existing authenticated `GET /api.php/timeentries` accepts optional exact
`entry_kind=work` or `entry_kind=absence`. The subject remains the existing trusted
own actor for every role; query/body user IDs cannot select another account.
There is no new endpoint, UI, action, writer or authentication path.

## Classification, not workflow authority

The unchanged `booking_is_absence()` is the sole classifier, called through a
connection-local SQLite callback. Existing non-empty modern `absence_kind` and
exact legacy descriptions `Urlaub`, `Krankheit`, `Feiertag` remain absences;
PHP's existing empty-value semantics are preserved, including stored `'0'`.
Unknown non-empty modern codes are not repaired/reclassified. Lowercase or
extended legacy descriptions do not acquire new recognition.

This selects stored raw rows only. Open or invalid rows can still match, just as
the original endpoint returns them; neither class proves a valid duration,
approved leave, entitlement, complete credit or work actually performed. Existing
date filters can exclude unplaceable starts by their unchanged existing rule.
No new time calculation, calendar/model policy or data-quality admission gate.

## Query and response

- Omitted kind preserves the complete existing default response bytes/metadata.
- Only exact decoded `entry_kind` is the parameter name. Its value is lowercase
  `work` or `absence`, not empty, padded, numeric, another enum or an array.
  Known duplicate/array/NUL-suffixed names or bad values return fixed JSON 400:
  `Invalid entry_kind. Use work or absence.` No raw value is reflected.
- The raw field is extracted independently of normalized bags/max_input_vars;
  a late explicit kind cannot silently turn into an unfiltered response. Existing
  parsing/error precedence for pagination and dates stays untouched. Unknown
  query fields retain their previous meaning/ignore behavior.
- Date predicates, own scope and kind intersect before the existing count/order/
  limit/offset logic. No day splitting/clipping or pagination/cursor change.
  Successful opt-in appends `meta.entry_kind`; existing raw fields, numeric/ID
  representation and other metadata remain unchanged.
- Laravel requires optional `OwnApiTimeEntryKindReads`; absence returns fixed
  JSON 503 `Time-entry kind filtering unavailable.`, never unfiltered fallback.
  General `ChronoReads` and `ApiTimeEntriesQuery` remain unchanged. Both supported
  paths use the existing read core. Native's pre-existing lack of an atomic
  count/page transaction is not changed or promoted to a snapshot guarantee.

## Evidence and limits

One PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0 anonymous query-only Memory journey
uses actual old/new extracted Native handlers, actual old/new Laravel validators,
controllers and named readers with an isolated identity double. User/supervisor/
Admin own scope, default/error/date wire parity, canonical classification through
actual SQLite types, kind/date/page/count intersection, limit 0, large ID, raw
malformed/duplicate/array input before read, normalized-bag resistance, late raw
kind and missing capability are covered. Source/schema/audit/change counters,
query-only, read transaction cleanup and native globals stay unchanged.
Necessary PHP syntax/JSON/diff checks pass; no new repository test suite.

No complete bootstrap/JWT/login/auth/router/HTTP/CSRF/browser/production,
concurrency/performance/real-data or operational acceptance. Date syntax semantics
are intentionally unchanged, including the separate open #383 anchor correction;
no held/denied packet is transported. No real account/credential/key/file DB,
writer, schema/dependency/runtime/security/host setting, manual CI/GitHub Approval
or deployment. #68 stays open; notifications/workflow integrations remain separate.
