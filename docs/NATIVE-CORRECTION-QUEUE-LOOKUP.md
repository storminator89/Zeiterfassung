# Native correction queue: exact known request lookup (partial #50)

The existing `adjustments.php` status filter now accepts an optional request ID.
A blank field keeps the old queue. A supplied ID must be a canonical positive
ASCII decimal integer supported by the existing correction selector. It stays an
exact PHP integer (including adjacent IDs above JavaScript's safe integer range);
no JavaScript number or float is involved. Malformed input displays no queue
rows/counts and a fixed error; GET returns 400. The field is a query selector, not
a command target, permission, write receipt or automatic retry.

The same request-ID predicate is added to both existing count and row SQL,
**inside the unchanged own/admin/assigned-supervisor scope**. Status remains
conjunctive. A hidden, missing or status-mismatched request can return no rows;
there is no separate existence check. Existing 50-row ordering/page clamping,
current read snapshot, selected booking, balance-preview links and POST redirect
retain the filter. Removing the ID keeps status and selected booking, resets the
page and does not submit a business command. Lookup state never changes the
original command/body target or its independent locked authorization.

One optional field and conditional reset link use the existing filter card,
classes and native German copy. Existing request/review/withdrawal forms,
permissions, CSRF, locking, drafts and writers are unchanged. No new endpoint,
route, schema, dependency or runtime switch. Laravel's existing queue and reader
contracts are deliberately unchanged; lookup there is a separate follow-up, not
silently claimed by this native-only package. Held/denied changes are excluded.

## Bounded evidence and limits

Base published Main `9b42efbc8bed285a6045338620d6501e475abb48`, tree
`ac013f317076535c5e230a05e573f488c063c88c`. PHP 8.5.12 / SQLite 3.46.1,
fabricated anonymous Memory rows and extracted actual source fragments:
- actual old/new scope/count/row SQL: 45 default/blank parity cases across roles,
  statuses and pages; own/assigned/admin visibility, hidden/missing equivalence;
- exact adjacent large/max IDs, status conjunction and page clamp;
- 18 malformed selector kinds yield no queue read, rows or counts;
- actual URL/form fragments: exact lookup and booking preservation, reset,
  POST redirect independence, escaping and no phantom booking field;
- entire command/rollback/draft block byte-identical; query-only source/counters
  unchanged; necessary syntax and whitespace checks.

This is not the real Native bootstrap/auth/CSRF/HTTP/browser/layout/production
acceptance, account creation, real data/credential use, new test suite, manual CI
or deployment. User browser validation remains separate. Request status is the
current stored fact, not a delivery notification. #50 remains partial/open;
representation, escalation and overall workflow acceptance are not completed.

The separately scoped [Laravel follow-up](framework/correction-request-lookup.md)
now documents its optional read capability and existing filter-card parity;
this Native packet and its original acceptance limits are unchanged.
