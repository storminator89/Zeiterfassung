# Laravel correction queue: exact known request lookup (partial #50)

This is the Laravel counterpart of the separately merged
[Native lookup](../NATIVE-CORRECTION-QUEUE-LOOKUP.md), on Main
`2282747054cc7a60181e56eae9ffe6f3dac5df3e` / tree
`8027e610aba2d5087e253a28267feaabfdc13917`. It reuses the **unchanged**
`correction_queue_request_filter` and canonical correction-selection parser.
No second ID grammar, float or JavaScript number conversion.

## Same queue, narrower current snapshot

The existing filter card gains the same optional request-ID field, fixed error
and explicit reset as Native. Status and ID are conjunctive. The exact ID
predicate narrows both counts and rows **inside** the unchanged parenthesized
own/admin/current-direct-team scope. Missing, hidden and status-mismatched IDs
may return an empty queue; there is no separate existence check. Ordering,
50-row paging/clamping, current role/team reload and one visible own pending
balance preview remain unchanged. Removing the ID retains status and selected
booking and resets the page; no command is sent.

Blank/absent ID follows the existing general reader method. A separately
optional `CorrectionQueueRequestReads` capability admits the explicit lookup;
`ChronoReads` is byte-identical. Old adapters keep their old unfiltered behavior
when no lookup is requested, but an explicit lookup fails closed with an empty
queue and visible 503, never an unfiltered fallback. Malformed raw query values
show an empty queue/counts and fixed 400 on GET/HEAD, not the reflected input.
Raw query reconstruction prevents trim/null middleware from turning a malformed
ID into a valid one. Invalid state stays an `invalid` sentinel in navigation
until the user clears or replaces it.

Existing page/preview links and success redirects preserve the query selector.
The optional field is **not** a business command target. POST still receives the
same allowlisted body and independent locked writer authorization; a different
query ID cannot rewrite the body's review/request target. A rejected POST keeps
only the existing still-visible eligible draft; no retry or new write receipt.
POST outcome handling/status remains as before, independently of GET validation.
An invalid or unavailable lookup does not display a concurrent saved hint.

Existing Native sources/helper, API, general read interface, all writer/draft
services, authority/auth/CSRF/source/role/team/session boundaries, routes,
schema, dependencies, runtime/host/security configuration and admission remain
unchanged. No held/denied packet, actual credentials, new account or deployment.

## Bounded evidence, not browser acceptance

PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0: one coarse actual named Memory
reader → current controller → compiled actual correction-content journey,
reusing the prior own-profile fixture/container setup with inert shared layout.
45 actual baseline/default/blank comparisons across roles/statuses/pages;
exact adjacent large/max IDs, own/admin/direct-team isolation, hidden/missing
equivalence, status/page conjunction, malformed values and raw-query normalization,
old general-only adapter, separate booking selector/reset/page/preview links,
escaped cards, idle-transaction refusal and fresh role loss. Source/schema/
audit/change counters and native session/timezone stay unchanged during reads.
One capture-only command stub checks transport/redirect/rejected-view separation;
it is deliberately **not** a real writer or command-authorization acceptance.

Necessary source/compiled-Blade syntax and full-tree preservation/inverse checks
supplement the coarse journey. No new repository testsuite or manually started
CI. RequestGuard is a deliberate identity double, array session uses only a
fixed display token. No full App/bootstrap/login/middleware/CSRF/HTTP/browser/
layout/concurrency/performance/production/operational acceptance. User browser
validation, representation/escalation and complete workflow/onboarding remain
open; #50 stays partial.
