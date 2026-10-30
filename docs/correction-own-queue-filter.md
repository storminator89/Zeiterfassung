# Own-only view of the existing correction queue

Partial #50, not delegation, escalation, approval or completion of the issue.
Base Main `0c78588dc5d7cd1e06def7fab0babd2ffd71fc9c`, tree
`f221e82a1c36797a53f633a07bf603be6e292c54`.

The existing **Anträge filtern** form now offers **Nur eigene Anträge**. This
narrows the current own/Admin/direct-team queue by its already-authenticated
actor's user ID; it never accepts an owner ID. In particular, a supervisor can
separate their own submitted requests from the team review worklist. Their own
requests still require a different authorized reviewer; no self-approval or
delegation is added.

The exact raw query `own_requests=1` opts in; absent or empty retains the original
queue. Other representations, arrays and invalid values produce an empty queue
and GET 400, not a silently wider result. Status and exact request ID are
intersections, not alternatives. Counts, rows and the existing optional own
balance preview retain one snapshot, ordering and fifty-request paging. Page,
preview, request-ID reset and existing command-redirect URLs preserve an active
or invalid own filter. A fresh GET filter submission can explicitly select the
broader **Alle bisher sichtbaren Anträge** view again.

Native and Laravel use the same definitions-only selector parser. Laravel adds
an optional `OwnCorrectionQueueReads` port; missing capability refuses the opt-in
with empty GET 503 rather than falling back. The general `ChronoReads` contract
and old default/request-ID methods remain available. Existing authority, raw
command bags, writers, four-eyes checks, draft handling, timestamps, durations,
schema and APIs are unchanged. No new page or redesign; no JS, route, permission
or runtime switch. Copy follows the existing German correction form; shared language files
are untouched, not a new fully translated correction workflow.

## Coarse evidence and limits

One fabricated PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0 anonymous Memory
journey uses the actual old/new reader and controller, existing compiled Blade
in DE/EN/ZH and extracted actual Native count/list SQL. Ordinary, supervisor and
Admin default results match the old paths; own rows/counts/paging agree between
Native and Laravel. Status plus request ID, an exact large ID, other owners,
invalid/array/raw-normalized input and missing capability are covered. Existing
navigation preserves the selector. Source/schema/audit/change counters,
query-only/transaction cleanup and native globals remain unchanged; no commands
are dispatched. Necessary PHP/compiled-Blade syntax and diff checks pass.

Identity is an isolated double, not authentication acceptance. No full Native
bootstrap/router/auth/HTTP/CSRF/browser/visual/production or approval acceptance,
real account/credential/operational data, new repository test suite, manual CI
dispatch, GitHub Approval, runtime/host/security/dependency/schema change or
deployment. Held/denied packets remain untouched; #50 stays open.
