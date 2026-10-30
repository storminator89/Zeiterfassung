# Calendar: a missing stored end is not the half-hour display block

Partial read-only correction for #55 / #79, prepared against actual Main
`d213531db6e93a07a2d884050a370edbd6899ed6` (tree
`8cfef884fd044b6d0f661aa6c710361ee243ec60`). Only the existing Laravel dashboard
calendar renderer, its existing template options and DE/EN/Traditional Chinese
copy change. Native TOAST is a separate path and is not changed or accepted here.

The existing PHP adapter intentionally keeps a timed event's real missing end
as `null`, alongside a half-hour `displayEnd` used for calendar geometry. The
previous renderer printed that half-hour time as the end in both the event and
detail modal. It could therefore look like a stored completion, even for an old
open entry. Both text surfaces now say **No end stored** in the selected language.
This describes the snapshot only: it does not assert that the person is currently
working, and does not complete or alter any entry.

The half-hour visual block remains unchanged. Completed event labels/modal dates
and times, all-day inclusive modal/exclusive grid dates, Berlin formatters,
source/end/geometry values, status, navigation, styles, layout, text-node escaping,
repeat modal close, data selection, authorization and accounting stay unchanged.
No SQL, parser, writer, route, provider, schema, dependency, runtime/configuration,
host or security change, no backfill, invented historical end, clock-based update,
automatic request/retry or deployment. Other old open records may still need
manual clarification through the existing correction workflow. The change does
not constitute a complete data check or #55/#79 acceptance.

## Evidence and limits

One coarse fabricated callback journey uses the actual unchanged PHP
`calendar_record_event` / `CalendarEvents` adapter and actual old/new JavaScript,
with a small fake DOM and FullCalendar constructor. PHP 8.5.12 / Node 24.22.1:
72 event callback cases (DE/EN/ZH × UTC/Berlin/Los Angeles process timezone × 8
records), including null/empty stored ends, both DST changes, completed clocks,
all-day exclusive end, safe text, empty week and repeated open/close. The old
invented end reproduces in 36 cases. Completed/all-day label and modal output is
byte-identical; all other options/fields and source input/global values are
preserved. This is not the actual FullCalendar implementation or a browser.

Necessary JavaScript, three PHP copy files and compiled actual Blade syntax pass;
Blade uses preexisting dependencies with an identical Composer lock. No repository
test suite, manual CI dispatch or fixture server is added. Natural exact-head/Main
CI is followed separately. No full App/bootstrap/DB/auth/CSRF/HTTP/browser/visual,
native TOAST, operational, production or deployment acceptance; no actual accounts,
credentials, keys or personal data. Browser acceptance remains with the user.

Frozen local evidence is under
`/tmp/chrono-calendar-open-end-maind213531d-support/`: events.json, coarse.json,
coarse.php/coarse.cjs, compiled template, proof.json, candidate.patch and the
independent review. The evidence directory is not shipped as a test battery.
