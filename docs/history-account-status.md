# #32 — Show already-journaled account status in existing History

Source basis: Main `cbf3c06971f4d381637b4c85fbafaa0a6b67a1ea`, tree
`8923740804ce0072ca79f4b5ea5a084b5807a30d`.

The existing V5 account lifecycle triggers already journal `account_status`
before and after an account change. The shared History presentation did not
select that business field, so a status-only change could incorrectly appear
as no changes to displayable fields. It now selects the saved status under
**Kontostatus (gespeichert)** in the same existing comparison and selected JSON.
Both Native and Laravel consume this unchanged shared presentation/export path.

This is historical snapshot text, not the current account's effective authority
or a new account control. Values are not translated, coerced or inferred. Older
snapshots without the field stay without it; no active status is guessed.
Existing scalar/type/length checks, omission markers and HTML escaping apply.
`auth_generation`, passwords, tokens, directory-specific metadata and unrecognized fields
remain unselected. No new lookup, ownership field, audit write or raw snapshot
is passed to a view or selected export.

Only the shared field allowlist and this note change. Existing queries,
owner/Admin/current-team visibility, filters/cursor/page limits, export envelope,
actor/reason/time, lifecycle triggers, schema, authority/writer, routes, assets,
runtime settings and all other snapshot selections stay unchanged. No live
account, credential, key, database, host or security configuration is changed.

Bounded verification uses anonymous Memory fixtures and the existing V5 trigger,
scoped History queries, selected presentation/export and Native/compiled Blade
cards. It demonstrates the prior missing diff and the added saved-status diff,
including transitions, old snapshots, malformed/escaped values and continued
omission of credentials/generation. It is not a positive runtime writer,
authentication/CSRF/HTTP/browser/layout/concurrency or production acceptance.
No repository test suite, workflow or manual CI dispatch is added. #32 remains
partial/open; unrelated journal and held/denied work is not included.
