# #32 — Read already-journaled intake-policy changes in existing History

Source basis: Main `6ec57d4007cf6d6129d3d2acddc5daf06ee6881f`, tree
`1c7f6e7a23e85d38d0f16fde3909dee0afc13dce`.

The existing V4/V5 `user_intake_policy` UPDATE trigger already saves the three
intake switches, revision, stored change time and changing person's ID in the
audit log. History previously did not recognize that entity for filtering or
selected presentation/export. It now offers **Aufnahmewege** in the same
existing entity selector and selects only those six already-journaled fields
for its existing before/after comparison and selected JSON paths.

The labels reuse the existing Admin controls: manual creation, LDAP
synchronization and public self-registration. Stored 0/1 values are shown as
such, not reinterpreted as the current effective policy. These switches do not
describe the permission to log in to an existing account, and showing an old
snapshot cannot enable an intake method or start directory synchronization.

No policy table lookup is added. Existing audit rows have owner `user_id=0`;
the unchanged History query keeps them visible only to the existing Admin role,
not an ordinary member or a supervisor's team. The entity filter only narrows
that same visibility. There is no new local/source restriction or permission.
No user, name or current policy is loaded from a selected historical ID.

Only the shared entity label and fixed presentation allowlist change, plus this
note. Other entities and fields, current account-status display, query SQL,
filters/cursor/page limit, selected-export envelope, actor/reason/time, audit
triggers/schema, policy validation/writer, role/CSRF/runtime/settings/assets and
all controls remain unchanged. Scalar/type/length limits, omission markers and
HTML escaping remain in force; arbitrary credentials or unrecognized fields
stay unselected. Missing historical fields are not invented.

Bounded anonymous-Memory verification exercises the existing V4 policy store
and audit trigger, unchanged scoped History queries, Native cards and the
actual Laravel reader/controller/compiled views and selected JSON. It proves
the previous unavailable presentation, explicit filtering, saved-value diffs,
ordinary/team exclusion, unchanged other entities and secret-field omission.
This is not an admitted Runtime writer, V5 authority, App/auth/CSRF-stack,
HTTP/browser/layout/concurrency or production acceptance. No real credentials,
keys, file database, host or configuration are used/changed; no new repository
test suite/workflow/manual CI dispatch. #32 remains partial/open.
