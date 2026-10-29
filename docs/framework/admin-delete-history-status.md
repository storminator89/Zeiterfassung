# #31/#79 — Show the existing history deletion hold in Laravel Admin

Source basis: Main `614cb887b1b45ec33e25def1a7229dddf2225196`, tree
`bcf4a86a1c0d01a7e83969c5d1bc5277b51185f9`.

The existing Laravel managed-user writer already refuses deletion when any
`audit_log.user_id` matches the target, regardless of entity or operation. The
Native list (PR251) shows this hold instead of an unusable deletion form. The
Laravel Admin list now reuses that exact correlated EXISTS predicate in its
existing paged SELECT and the exact Native fixed DE/EN/zh labels.

Only a boolean presence hint is added to each already-authorized current
local-Admin row. No audit contents, count, reason, timestamp, actor or raw audit
row leaves the snapshot. A failed query remains a failed read, not a false flag.
The private current actor/source/version/generation boundary, JOINs, search,
order, page size and count remain unchanged. No separate history query or new
endpoint is introduced; existing #34 reference hints stay intact.

For a true hint, the two existing table-cell branches show the fixed explanation
instead of the deletion form/disabled delete button. Edit/details, lifecycle and
LDAP controls are untouched. For a false/missing hint, the old capability-gated
form/disabled button is unchanged. A negative hint is not deletion permission:
last-local-Admin/recovery rules and every current writer check remain decisive.
History added after this read and manually submitted POSTs are still checked
inside the existing writer lock. The UI is only a snapshot, not new authority.

This does not deactivate accounts, alter authentication or clear history. The
unchanged #31 create-form retention and all forms/Controller/DTO/CSRF, Writer,
V5 recovery, Auth/Provider/Registrar/Runtime, schema and Native sources remain
unchanged. User baselines/model-only history also count; ordinary retained
accounts may all have history. #31 remains partial, not fully accepted.

Coarse evidence uses the actual reader, unchanged DTO and whole compiled Blade
over invented query-only SQLite Memory, comparing the complete current baseline
to the candidate. It is not actual Auth/SessionGuard/provider/V5 admission,
CSRF/HTTP/browser/modal/concurrency/crash/real database or production acceptance.
No new repository test suite/workflow, dependency or host/security change; no
#239 code, denied operation, retry or bypass.
