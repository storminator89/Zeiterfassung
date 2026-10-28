# V5 daily Admin writers

Source anchor: Main `83717e8e56484b45a93c55d2901f054bfdcabdb9`, exact tree
`c177eba798eef8e77f23a0500eb843da6c78dfa6`. This packet makes four real
component operations work with V5 authority; it does not activate a Runtime,
change an existing UI or add another feature flag.

The existing WorkModels, PauseSettings, Departments and CarryIn facades receive
the already bound immutable `AccountLifecycleWebIdentity`. They capture only the
trusted refreshed local Admin model before resolving a writer. An optional
trailing authority carries that capture into the existing fixed writer method.
Raw command fields still select only allowed targets/business values, never the
actor, role, source, password version or generation.

Inside the sole existing IMMEDIATE writer lifecycle, before domain/SQL mutation,
one fixed helper binds user id, local source and Admin role, including the
work-model method's separate trusted role argument. The existing V5 principal
checker then re-reads active state, source, role, password version and captured
generation and requires locally login-eligible administration. Current-active
alone never replaces the captured generation. Wrong/missing authority is closed;
an authority supplied to an older profile is explicitly refused.

The unchanged domain contracts remain:

- append-only work models, effective dates, minute validation, target and audit;
- complete validated pause-rule replacement, without inventing a nonexistent
  pause-table audit trigger;
- department create/rename semantics and refusal to delete assigned departments;
- own-Admin-only carry-in, including existing string/REAL precision checks and
  audit scope. No request-selected user target is introduced.

V5 writer401 becomes the registrar's normal AuthenticationException path only
after the unchanged writer rollback; older-profile error handling remains exact.
V2/V3/V4 calls with no authority keep their original branch and SQL. No schema,
formula, controller, route, view, Native, terminal, snapshot, Runtime opener or
transaction/retry/reconnect/COMMIT-failure lifecycle is changed.

## Coarse functional evidence

One disposable private-file journey uses actual service-provider bindings and
persisted V5 work-model, pause, department create/rename/delete and carry-in
operations. It verifies the assigned-department refusal and guard-derived model
actor, then deactivates/reactivates the Admin between facade capture and writer
resolution for **each** operation. All four stale operations refuse with no
additional business/audit mutation; inactive/nonadmins resolve no writer and a
fresh current generation succeeds. All four older V4 facade fallbacks also
succeed; wrong-profile/missing authority and the global Runtime hold still fail
closed. The original source fails on its first V5 work-model write.

Only invented test accounts/hash and temporary files are used. This is not real
login/session/HTTP/CSRF/browser, concurrency, COMMIT-failure, restore or human
recovery acceptance. No operating data/credential/key/directory, publication or
new CI is touched.

## Privileged export and operating admission

The separately scoped [snapshot-authority component](account-lifecycle-v5-snapshot.md)
now captures immutable V5 Web authority and checks its complete tuple on the
actual native read snapshot before artifact allocation, including explicit V5
versions/policy source checks. HEAD remains source-free. No alternative opener
or public V5 export is enabled by that component.

The global V5 Runtime refusal stays until remaining terminal-Admin, physical
profile/provisioning, Native-boundary and local-recovery gates are complete.
Final real snapshot/restore and operational acceptance are not a synthetic
authority test. See the [combined source status](account-lifecycle-v5-core-integration.md);
neither packet claims the complete Laravel deployment is finished.
