# #32 — Read already-journaled API policy in existing History

Source basis: Main `8032f3fc79f390a64d9196a2aaa588d029adc77b`, tree
`01894602696fdb3e056c6fdefcdad24837d33120`.

The existing API administration journal already stores the explicitly nonsecret
`ApiPolicyState` snapshot under `api_service_policy`. History previously did not
recognize the entity for filtering or selected presentation/export. It now
offers **API-Betriebsfreigabe** in the existing entity selector and selects only
the six existing fields: snapshot-format version, saved enable/preparation
flags, policy revision, changing person's ID and stored change time.

These are historical stored values, not current effective API authority.
`version` describes the policy snapshot format, not an API protocol version.
`key_prepared` records the saved preparation flag; it does not reveal a key,
path, fingerprint or token, attest that a private key currently exists or is
valid, or mean that tokens were rotated/revoked. No key/configuration file is
opened, no private material is loaded and no API channel is enabled by History.

The existing journal rows have owner `user_id=0`. The unchanged History query
keeps them inside existing Admin-role visibility, excluding ordinary/member
and supervisor-team scope. The entity filter only narrows that same visibility;
no source/local restriction or permission is added. Historical `updated_by`
IDs are not resolved into current names, accounts or authority.

Only the shared entity label and fixed presentation allowlist change, plus this
note. All prior entities, intake-policy/account-status display, SQL/filters,
cursor/page limits, export envelopes, actor/reason/time, actual API journal
validation/append/authority/key operations, schema, auth/CSRF/routes/runtime,
security settings, controls and assets stay unchanged. Existing scalar/type/
length bounds, missing-field handling, omission markers and HTML escaping apply.
Unrecognized credentials/key/token fields remain unselected, not copied raw.

Bounded anonymous-Memory verification uses fabricated historical journal rows
serialized by the actual `ApiPolicyState` and checked for continuity by the
actual `ApiPolicyJournal::read`, then the original scoped History query,
Native cards and actual Laravel reader/controller/compiled views/JSON paths.
It is not an API management writer, key-preparation/provisioning/rotation,
current-key or runtime-authority acceptance. No real credentials, keys, tokens,
file database, host or configuration are used/changed. Full App/auth/CSRF-stack,
HTTP/browser/layout/concurrency and production acceptance remain open. No new
repository test suite/workflow/manual CI dispatch; #32 remains partial/open.
