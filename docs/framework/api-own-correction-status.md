# Own correction request status: read-only API

`GET /api.php/corrections` exposes only the authenticated account's stored
correction request statuses through the existing bearer guard.
Web sessions cannot substitute for that guard. Admin and supervisor tokens do
not expand this endpoint to team or all-account data. There is no subject selector,
approval, withdrawal, creation, model/absence mutation or notification side effect.

## Query and response

- `status`: `all` (default), `pending`, `approved`, `rejected` or `withdrawn`.
- Optional `request_id`: one exact positive ASCII decimal **string** within
  `1..9223372036854775807`, without leading zeros, signs, spaces or exponents.
  It narrows the same own-account query, never selects another account.
- Existing strict API pagination: `limit` defaults to 100 and accepts 0..1000;
  `offset` accepts 0..2147483647. Omitted offset skips zero and is reported as null.
- Values are parsed from the unnormalized PHP query. Arrays, whitespace, invalid
  numerals/statuses and unknown parameters (including `user_id`) return 400.
- Results are ordered by request ID descending. Offset pages can shift when new
  requests arrive: this is a current snapshot, not a stable export/change feed.

Each item contains exactly `request_id`, nullable `record_id`, `status`,
`created_at_epoch` and nullable `reviewed_at_epoch`. IDs and epoch seconds are
decimal **strings** within SQLite's signed 64-bit integer range, preserving values
above JavaScript's safe-integer range.
No reasons, proposed/original times, reviewer identity, owner selector, credentials,
audit snapshots or account metadata are exposed. Null remains null, not zero.
`meta` reports the selected status, total matching own rows, returned count,
limit, offset and `order: request_id_desc` in the same read snapshot.
Only when `request_id` was explicitly supplied, `meta.request_id` additionally
echoes that validated string. Without it, the existing response keys/values and
ordering remain unchanged. Unknown-parameter errors now name the additional
accepted parameter; all existing parsing/guard/status rules otherwise remain.

For example, `GET /api.php/corrections?request_id=9007199254740993` can poll a
known own request directly without scanning shifting offset pages. Omit offset
and use a nonzero limit (the default already does so). Status, limit and offset
still apply together: a mismatching status, zero limit or skipped row does not
silently override the caller's other filters. Foreign and nonexistent IDs both
produce zero matching rows; no owner/existence distinction or additional data
is returned. The selected row's five fields remain exactly the same projection.

The stored status is a fact, not permission to perform a review action. A client
may compare the exact request ID/status between polls but must not treat this as
an outgoing notification/delivery receipt or automatically approve/retry writes.
Unrepresentable source rows and unavailable reads fail with a fixed 503; exception
details and partial rows are not returned. Missing/invalid bearer credentials and
wrong HTTP methods retain the existing guard/wire responses.

## Composition and boundaries

Native and Laravel use the same query validator and PDO projection inside their
existing own-user read transactions. Laravel's existing optional list-reader
interface and the general `ChronoReads` contract remain byte-identical. A separate
optional exact-request reader capability adds the narrower lookup; an adapter
without it returns the same fixed 503 for an exact-request query, never an
unfiltered list. Its default list remains usable through the original interface.
Native retains its existing JWT/password-version checks. Laravel retains its
separate API opt-in/admission, local admin on/off policy journal, JWT/generation
checks and raw-method/URL guard. This change does not extend Laravel's operating
policy gate to Native. Both responses are no-store; the existing wire behavior
is otherwise unchanged. No new
writer, profile, schema, key or deployment flag is introduced.

This is a bounded workflow-read step of #68. The authoritative attendance GET
already exists separately; it is not reimplemented. Outgoing notifications,
delivery scopes/deduplication/retry, other workflow routes and #68 completion
remain open. No live account, credential, API key, server, browser, production
directory or external messaging service is used by the component checks.

## Exact-request composition and evidence — partial #68

Source Main `8032f3fc79f390a64d9196a2aaa588d029adc77b`, tree
`01894602696fdb3e056c6fdefcdad24837d33120`, after PR #364. This delta adds only
the explicit own-request filter, optional adapter method and matching contract
documentation. It contains no denied absence-credit packet or other held delta.
The existing endpoint, bearer guards, admission/source/generation policies,
owner selection, current-actor snapshot, routes and no-store boundary remain.
The SQL count and row read share `user_id = :owner AND id = :request_id` plus
the optional status inside the same existing transaction. The raw decimal string
is validated again by the projection and bound as text, never through a float
or JavaScript Number. IDs/reasons/times are neither repaired nor rewritten.

The coarse anonymous Memory journey uses the actual shared query/projection,
current Laravel reader/router/controller and extracted Native handler. Existing
list defaults/statuses/pagination, raw query/method guards, fixed errors and
private-field exclusion remain covered. Exact own/foreign/nonexistent requests,
adjacent JS-large IDs, maximum SQLite ID, status/paging conjunction, malformed
selectors, later status changes and new arrivals are compared across both
transports. Reads use `query_only`; actual auth identity is deliberately a test
double, not a JWT/login/operating-policy or real-client acceptance claim.

Necessary syntax, schema JSON and complete source preservation are separate
checks. No new repository test suite or manually dispatched CI, no real accounts,
credentials, secrets, file database, HTTP server, browser, notification service,
runtime/security-setting change or deployment. A returned current status is
still not a notification, delivery marker, approval grant, immutable export or
booking receipt; no automatic poll, retry or write is added. **#68 stays partial.**
