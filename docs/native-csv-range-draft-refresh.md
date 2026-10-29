# #53 — Keep the current Native CSV range while refreshing the own list

Source basis: actual Main `8032f3fc79f390a64d9196a2aaa588d029adc77b`, tree
`01894602696fdb3e056c6fdefcdad24837d33120`, after PR #364.

The Native own CSV range form is inside `timeRecordsContainer`. A successful
list refresh, pagination or review-filter change replaces that container's
HTML, discarding the chosen From/To dates and closing the range details. The
Laravel range form is outside this replaceable fragment and has no such need.

The shared existing updater now captures only this current same-container
form immediately before the newest successful HTML replacement, **after** its
existing stale-response guard. It restores the two date values and open/closed
state into the same fixed form in the new fragment. If a date field had focus,
it returns focus to the matching replacement field. Reading at replacement time
preserves edits and clearing done while the fetch was pending, not an older
request-start copy. Failed/stale responses do not replace or capture anything.

Recognition is bounded to existing fixed date IDs/names, both fields belonging
to one GET `export_csv.php` form and its details inside the same container. No
source form or no matching new form means no draft is applied. The Laravel
outside form and unrelated forms remain outside this path. No new fields,
listeners, markup, input rules, CSS, storage, cookies, session/flash data, URL
parameters, submission, download, retry or backend calculation are introduced.
Only the browser's exposed date-input values are copied; native picker state,
partially typed values not exposed by `.value`, cursor selection and draft
persistence across whole-page navigation/reload are not promised.

The existing AJAX URL/method/options, latest-request guard, filter/page state,
pagination/filter focus, error handling, summary expansion, booking refresh
callers and export controls remain unchanged. Both existing `main.js` copies
stay byte-identical. This is presentation state only, not export validation or
an accounting decision. The existing endpoint remains authoritative. No parser,
serializer, CSV bytes/filename, PHP/SQL/identity/authority/CSRF/writer/runtime/
security/host/route/dependency or deployment change; #53 stays partial/open.

## Coarse evidence and limits

Node 24.22.1: one seven-group journey executes the actual frozen old and candidate
scripts in a deliberately small VM DOM/fetch model, tied to the actual current
Native fixed form markup. The old updater loses the modeled draft; the new one
keeps current edits/clear/focus/open state, exact existing fetch/filter/page
contracts, both out-of-order response orders, unchanged failed-read nodes, a
subsequent explicit refresh, absent/newly absent forms and an unrelated POST
form exclusion. Storage/navigation tripwires stay unused. Both script copies
match, Node syntax and whole-tree/source-preservation checks pass. Independent
source review/rerun supplements this bounded proof; no new repository test suite,
workflow or manual CI dispatch.

A VM model is **not** a real DOM/HTML parser or browser/date-picker test. No App/
bootstrap/auth/CSRF/HTTP/actual user input/browser/history/focus-picker/download/
layout or production acceptance is claimed. Real focus/calendar behavior remains
for browser acceptance. No real accounts, credentials, keys, operational data,
server, external service or deployment.
