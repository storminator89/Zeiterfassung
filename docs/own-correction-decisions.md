# Own correction outcomes on Home

Related to #68, this is a read-only in-app feedback step, **not completion of
notification delivery or the whole issue**. The existing Home/Attendance page
now contains a collapsed card for the current user's stored `approved`,
`rejected` and `withdrawn` correction requests. There is no new page or redesign.
Native PHP and the connected Laravel Home use the same projection and DE/EN/ZH
copy. Optional read adapters without this capability keep their existing page.

## User path and scope

Open **My completed correction requests** on Home. Up to five requests with a
usable decision timestamp are ordered by that stored time (then numeric request
ID), with up to five separately listed requests whose decision timestamp cannot
be interpreted strictly. Each group says when more exist. Empty means no own
requests with these terminal statuses, not no pending work. Unavailable reads
show an explicit unavailable message instead of an empty or clean result.

Each item opens the existing correction detail through the intersection of
`own_requests=1`, `request_status=all` and its exact request ID. The footer opens
the existing own request list, including pending work. The request detail reads
the then-current authorized state; the card does not grant approval authority.
An Admin sees only their own requests here, never all requests they can review.
No query parameter selects a different owner, team, status or date for this card.

## Data and read contract

The optional `OwnCorrectionDecisionsReads::ownCorrectionDecisions()` has no input.
The canonical read adapter reloads current account authority and reads both groups
inside its existing guarded snapshot. Native PHP reloads the known role and
password fingerprint inside its own read transaction. A borrowed transaction is
refused without closing it. Authentication/authorization refusal retains the
protected response; other failures do not expose raw errors.

Only request ID, terminal status and stored `reviewed_at` are projected, together
with load time and the two truncation flags. No reasons, proposal/original JSON,
booking values, reviewer identities, account fields or request-supplied subject
are selected. IDs remain exact decimal strings. Decision times require strict
integer epoch metadata and a representable four-digit-year instant; malformed,
missing, oversized or binary metadata is separately marked unclear. Epoch zero
is valid. No free-form date guess, `now` substitution or source repair occurs.
Valid decision labels use Europe/Berlin, with UTC machine-readable instants.

Only the ordinary protected GET reads the card. Status JSON, booking POST and
Laravel HEAD/disabled-Home paths do not invoke it. Existing attendance actions,
API contracts, four-eyes reviews, withdrawal finality, history, CSV, schema,
audit triggers and writer bindings are unchanged.

## No delivery claim

This is a fresh projection of stored outcomes, not push delivery, an unread
counter, a receipt or an acknowledgment. Loading, repeating or following a link
does not mark anything read or mutate a request. Therefore there is no outbound
message, dedupe token, retry worker, new provider or hidden subscription. The
bounded card cannot replace the full existing request list or an audited
notification-delivery workflow. Targeted outbound notifications, their delivery
scope/dedupe/retry contract and any additional workflow APIs remain open in #68.

## Verification boundary

A seven-phase coarse component journey uses the unchanged canonical V2 fixture
and real core approval/rejection/withdrawal functions, then the current guarded
reader, controller, DTO, compiled Blade, Native wrapper and Native preheader
hook. It covers own/foreign/pending scope, exact large IDs, separate truncated
recent/undated groups, bad epoch metadata, all three locales, safe failure versus
empty, current authority, borrowed transactions, disabled/status/HEAD paths and
source/schema/audit/session/timezone preservation. Corrupt metadata fixtures are
separate unadmitted in-memory data; they are not admitted runtime databases.

A separate private NEW-installation journey uses the actual Artisan producers,
providers, routes, encrypted file sessions, CSRF, standard authentication and
admitted file-backed V5 database. Two synthetic accounts exercise own request,
forbidden self-review, independent approval, withdrawal and rejection, current
own Home results and exact detail links, foreign Admin exclusion, existing
History/own-CSV and no business mutation from card reads. Generated accounts,
keys, database, cookies and files are removed. The first assertion expected
German while the application selected English; explicitly selecting `lang=de`
corrected the private test expectation, not product code.

Runtime and exact immutable candidate evidence accompany the implementation.
These are coarse component and real local Kernel checks, not a real browser,
Native HTTP login, load/concurrency, Docker/TLS, external messaging or production
acceptance. No real identity, key, credential or operational data is used.
