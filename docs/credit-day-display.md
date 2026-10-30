# Own credit-row day label

Refs #55 / #79; a bounded existing-display correction, not completion of either issue.

The existing own time-record list groups records by the canonical Europe/Berlin
start day. Modern absence-credit rows previously labelled their day by taking
the first ten raw timestamp characters instead. An offset-bearing timestamp
near midnight could therefore show a different day in the row and group header:
`2026-10-29T23:30:00Z` belongs to Berlin `2026-10-30`, not `2026-10-29`.

`report_time_record_detail()` now reuses its already-calculated canonical day for
that label. Native and Laravel consume this same existing helper. The original
raw fallback remains exact if no start day can be calculated; no date or instant
is invented. Existing modern-credit recognition, legacy/work labels, invalid
warnings, raw data, duration/credit values, totals, group ordering and pagination,
export, parsers, writers and four-eyes rules are unchanged. No new UI or route.

## Evidence and limits

Base Main `bb980b1f2af4d34aab8fa5d40f34c70103f18f90`, tree
`9104f0851fc3e6b1838e170bf890317bbfaa3c8a`. One executable line plus this note.
PHP 8.5.12 / Laravel 13.38.0, unchanged Composer lock: one fabricated display
journey through the actual old/new helper, grouping, extracted Native row
renderer and actual compiled DE/EN/ZH Blade. Midnight changes in both directions,
spring/autumn transition days, canonical/legacy/work and unreadable/open/end-
invalid rows preserve all other detail/group/total/render bytes after reversing
the day-label change. Three process timezones give the same canonical day;
inputs and native session/timezone globals remain unchanged. Necessary PHP syntax
and diff checks pass. No new repository test suite or manual CI dispatch.

This is not a DB/admission/bootstrap/auth/HTTP/browser/visual/production acceptance,
mapping or repair. No real account, credential, operational data, runtime, host,
security setting or deployment was used or changed. Existing issue-level limits
and held/denied packets remain untouched; #55 / #79 stay open.
