# V5 Web business operations: functional core, not runtime activation

Source anchor: actual Main `83717e8e56484b45a93c55d2901f054bfdcabdb9`,
tree `c177eba798eef8e77f23a0500eb843da6c78dfa6`. Local snapshot commits are
not remote ancestry. This packet removes a concrete functional blocker: the
existing Web attendance, absence, correction and withdrawal facades previously
reached only generic writer operations that always refused V5 before BEGIN.
It adds no disabled display toggle, new route or generic `ready=true` option.

## Working operations and authority

The existing App/ChronoDomain providers now pass the already registered immutable
`AccountLifecycleWebIdentity` to these four facades. A V5 facade captures only
its trusted refreshed guard model **before** resolving its operation-scoped
writer. Request/form actor, role, source and generation are never adopted.
The explicit admitted Runtime profile selects V5; optional account columns do
not. Missing/wrong composition still falls into the closed generic V5 writer,
not a generationless compatibility fallback.

Four fixed methods in the existing `AccountLifecycleWriterOperations` trait run
the unchanged domain cores under the sole existing writer IMMEDIATE lifecycle:

| Fixed operation | Preserved business contract |
| --- | --- |
| `transitionWeb` | Server-derived `web:{id}` receipt namespace; exact saved response and payload hash; expected-record requirement for new non-start events; existing pause/end confirmation and audit text. |
| `absenceWeb` | Existing date/type parser and model-based absence credit; same overlap/day/holiday rules and audit actor. |
| `correctionWeb` | Same owner request, original snapshot, second-person/team review, timing and audit core; LDAP remains ordinary-user/request-only. |
| `withdrawCorrectionWeb` | Same own pending request, local source, role/password-version and reason checks; original withdrawal error translation and audit core. |

Every method first refuses a V5 authority on an older writer profile. Inside
the same transaction, before any domain operation or **receipt replay**, the
writer checks the captured id/source/role/password version/active state and
authorization generation against the current row. Inactive/departed rows,
stale source/role/password and stale generations cannot write or obtain a saved
response through an old in-flight request. Explicit reactivation permits only
fresh authority, never revival of the old captured generation.

Receipt namespaces/payloads are deliberately not rotated by generation. A
currently authorized fresh identity can still use the existing retry contract;
the non-revival guarantee concerns old authority, not deletion of historical
receipts. Status changes do not end work sessions, erase history or rewrite old
requests. These business operations never advance account generation.

V2/V3/V4 facade fallbacks and generic writer methods remain as before. The full
existing transaction/COMMIT-failure/reconnect/retry lifecycle, API and terminal
methods, Native files, schema, routes, controllers, views and transport fields
are unchanged. There is no second transaction manager or public arbitrary-write
callback. The previous generic V5 operations remain closed.

## Coarse evidence and limits

One disposable component journey uses the actual service-provider bindings,
stock SessionGuard with trusted synthetic models and a canonical V5 schema with
its four-row ledger on a private temporary file. It exercises real persisted
start/pause/resume/end and saved replay, model-based absence, correction request,
second-person approval and owner withdrawal. It then deactivates/reactivates
the actor and verifies refusal for all four stale writer paths, including an
already committed attendance receipt, with no business/audit changes. Refreshed
current-generation authority succeeds; retained history stays intact. The same
journey checks the existing LDAP request-only boundary, older V4 booking/replay,
wrong-profile refusal and the original still-closed Runtime gate. No directory
operation is performed. The old source fails at its first V5 Web stamp because
the generic writer is not bound to V5 authority.

This is functioning **component** behavior, not an operational application
claim. Synthetic models do not prove real login/session refresh, CSRF, browser,
HTTP or deployment behavior. No operating data, credentials, keys, account,
directory, host, network or Runtime activation is touched. Concurrency, COMMIT
failure, crash/restore, human recovery and Native cutover remain untested here.

## Concrete remaining blockers to a usable NEW-only Laravel V5 installation

1. `RuntimeDatabase::requireImplementedRuntime()` still refuses V5 before open;
   the Runtime writer factory does not yet select the complete V5 writer.
2. [Quota, bootstrap and local-enrollment cores](account-lifecycle-v5-enrollment.md)
   now implement their distinct V5 contracts; physical provider and public UI
   composition remain separate. Anonymous operations do not invent authority.
3. [Work-model, pause, department and carry-in cores](account-lifecycle-v5-admin-writers.md)
   now carry captured generation. Site and remaining terminal-admin facades/
   writers still need it. Existing API/terminal/LDAP slices must not be copied.
4. [Snapshot authority](account-lifecycle-v5-snapshot.md) now checks generation on
   its actual native snapshot component; the Runtime wrapper stays blocked.
   FreshWeb/setup/key/profile selectors and complete signature/digest propagation
   must consistently select V5 without calling it V4-compatible. The separate
   NEW-only file producer alone does not admit setup or business runtime.
5. Public route/profile composition, the separately reviewed Native-V5 boundary
   before Native DDL, known-good Laravel replacement and local-Admin recovery
   must be established before removing the global hold. Actual HTTP/session/
   CSRF/concurrency/restore/operational acceptance is still required.

This packet does not remove or weaken any of those gates. No old-data migration,
reset/deletion, real credential provisioner, publication or new CI is performed.
See the [combined source status](account-lifecycle-v5-core-integration.md) for the
current component/operating-admission boundary across all packages.
