# Own correction request status: read-only API

`GET /api.php/corrections` exposes only the authenticated account's stored
correction request statuses through the existing bearer guard.
Web sessions cannot substitute for that guard. Admin and supervisor tokens do
not expand this endpoint to team or all-account data. There is no subject selector,
approval, withdrawal, creation, model/absence mutation or notification side effect.

## Query and response

- `status`: `all` (default), `pending`, `approved`, `rejected` or `withdrawn`.
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

The stored status is a fact, not permission to perform a review action. A client
may compare the exact request ID/status between polls but must not treat this as
an outgoing notification/delivery receipt or automatically approve/retry writes.
Unrepresentable source rows and unavailable reads fail with a fixed 503; exception
details and partial rows are not returned. Missing/invalid bearer credentials and
wrong HTTP methods retain the existing guard/wire responses.

## Composition and boundaries

Native and Laravel use the same query validator and PDO projection inside their
existing own-user read transactions. Laravel's optional reader interface adds no
method to the general `ChronoReads` contract; an unconnected adapter fails closed.
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
