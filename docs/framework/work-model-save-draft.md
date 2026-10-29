# #61/#79 — Review rejected model fields without retrying an uncertain save

Source basis: Main `e5ad8f64830de6fdf0baf0258a1ccef55ffda0f4`, tree
`2a1a7e320e668086c51dbe0f0686e2aafda58af5`, including the explicit PR294
commit-attempt outcome boundary. This UI slice changes no writer contract.

The existing model form already renders escaped, bounded display values through
`WorkModelsPage::previewFormValues` and the unchanged Native helper. The Save
error path now reuses that same display mechanism only for an existing
AttendanceError 400/409 before the service returned. It does not flash/persist
inputs, retry, submit, recalculate credits, or pre-authorize the target.
The unchanged current target options still control which selection is visible.

The complete concrete path is the existing Controller -> authenticated
Admin work-model facade -> operation-scoped writer -> locked Native model core
and AuditContext. The current provider permits stored local/LDAP business-Admin
roles; its unchanged context and locked source/role/version/generation checks
remain authoritative. Neither that core nor its supplied callback owns COMMIT.
PR291 masks uncertain cleanup, and PR294 marks commit-attempt before calling
commit. Every subsequent caught error is generic/uncertain and the adapter is
poisoned, even after a physically successful commit. Only pre-attempt errors
with confirmed cleanup retain their original domain status. A status alone
without this concrete writer/callback/phase contract would not be sufficient.

Authentication/authorization, other domain statuses and generic errors do not
retain save inputs. The successful service-return marker forbids treating a
later response error as a rejected command. An unknown outcome resets this
display-only form path and explicitly asks the user to reload/check the stored
state, never automatically repeat. It does not claim that nothing was saved.
Work-model saves have no stable event-ID receipt; this slice adds no replay.

Default GET, explicit preview, successful Save redirect, all calculation and
current Reader/DTO/Blade, Writer/Core/Auth/Provider/Registrar/Runtime, schema,
Native and shared asset bytes remain unchanged. Secrets/extra form fields are
excluded by the existing command whitelist and display helper. #239 untouched.

Bounded component evidence on PHP 8.5.12 / locked Laravel 13.38.0 couples the
actual writer, existing facade, query-only read snapshots, Controller/DTO and
compiled whole existing Blade. Both V4 and V5 baseline Save refusals reset the
fields. The candidate's 27 cases cover ordinary 400 validation, an injected
precommit 409 after model INSERT with confirmed rollback, rollback/COMMIT
refusal, a real physical PDO commit followed by synthetic 400 and 409 failure,
success, GET, explicit preview, unknown action, nonadmin, bounded/malformed
fields, missing target and a stale V5 generation. Model/audit committed rows
are compared independently; only one writer resolution is allowed per Save.
POST never displays the query's `saved=1` as a success, no request extras or
private failure detail reach the form, and no draft reaches session/old input.

These are direct local-Admin component fixtures, not authenticated application
acceptance or a new LDAP-login/role proof. The PR318 stored-role integration is
preserved byte-for-byte and its separate evidence is not replaced by this test.
A fake guard uses public deliberately invalid bcrypt-alphabet row markers,
never generated password hashes or usable credentials. The existing syntactic
Admin predicate runs unchanged; this cannot prove any human can log in. No
keys, setup tokens, account credentials, login or real instance were created.
The fixture producer creates canonical empty private V4/V5 files, but the
component test uses dedicated fixture-only PDO/manager composition, not a
positive Runtime opener or full App/HTTP/session/CSRF/browser path. Test-owned
files were deleted. No concurrency/crash/operating recovery or production claim,
new repository test workflow, dependency or Runtime/host switch.

This is a small #61/#79 form-lifecycle improvement, not completion of #61's
value-dated balance ledger or closed-period requirements.
