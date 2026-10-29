# #34/#79 — Visible stored assignment issues in Laravel Admin

Source basis: Main `5a9cbc16b7e4411259b7d04a50ff973f77f5e6b7`, tree
`7ca8d98a710a03d3e1823de497c8db37a8f39c75`.

The Native Admin already distinguishes a genuinely absent assignment from a
stored ID whose existing LEFT JOIN finds no target. Laravel now reuses the same
unchanged, definitions-only `admin_reference_status` helper for the two current
Admin table cells. A missing department or supervisor no longer silently looks
like an unassigned person. Invalid or inconsistent lookup values are marked for
manual review, not coerced to a guessed target.

The existing `adminUsersPage` snapshot still independently reloads and requires
the current local Admin. The exact same two JOINs select their assigned/matched
IDs only for the pure classification. All four helper fields are removed before
returning the page projection; only two allowlisted status strings are added.
Names, search, order, ten-row paging/count, current actor and private snapshot
source/version/generation checks remain unchanged. No additional query, account
field, personal selector, traversal, credential, writer or authority is added.

The table displays fixed escaped warning text for missing/invalid/unknown
references and points to its existing Edit control. NULL-unassigned and resolved
references—including legitimately empty names—retain the existing presentation.
Missing raw IDs and malformed original values are not echoed in the warnings.
The existing German Admin text is retained without a new language dependency.

This is a bounded page-level stored-reference hint, not a complete database scan,
graph/role correctness check, repair, replacement assignment, migration or
foreign-key admission. A resolved JOIN does not certify a valid supervisor tree
or a clean database. Existing assignment/deletion/last-Admin/audit/LDAP/V5 rules,
forms and routes remain authoritative and unchanged; #34 remains partial.

The merged #31 create-form draft touches a different Blade block and its
Controller/DTO only. Its entire final merged delta is preserved by this additive
fresh-Main reanchor; the existing form inputs and their bounded retention are
unchanged. No #239 code or denied operation is used or retried.

Coarse evidence uses invented query-only SQLite Memory, the actual read adapter,
same Native helper and compiled Blade. It is not real Auth/SessionGuard/provider,
CSRF/HTTP/browser/modal, V5 admission, concurrency, migration or production
acceptance. No new test suite/workflow, dependency, Runtime or host change.
