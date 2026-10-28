# Combined V5 functional core — source integration, not activation

This packet combines the generation-bound Web business, local enrollment/login
budget, daily Admin and snapshot-authority cores plus complete profile-digest
binding. It relies on the already merged V5 schema/Web identity/writer foundation
and API/LDAP/terminal protocol slices from PR227/228; it does not duplicate them.
The source is serially reanchored to an explicitly verified Main before any
publication. Historical Main anchors in the component documents identify their
development evidence, not the final publication parent.

Current source integration is anchored to published Main
`cbf3350be9ecab67024273e581a0e578a923d676`, exact tree
`3c961ecf1584a64ac250a673d69b27c94b3c1849`, after PR234 (#57).
Its seven pending-client/asset paths are preserved byte-for-byte; the 28-path
core delta does not overlap them. This is a source anchor, not a V5 operating
admission or a claim of real browser acceptance.

## What actually works in bounded component journeys

| Component | Functional result |
| --- | --- |
| [Web business](account-lifecycle-v5-web-business.md) | Persisted stamp/pause/resume/end, model-based absence, correction request/second-person review/owner withdrawal; captured generation checked under the existing lock before replay or DML. |
| [Login budget and local enrollment](account-lifecycle-v5-enrollment.md) | Anonymous shared quota commits before429; empty first-Admin bootstrap, self-registration, manual creation and policy CAS work with their distinct locked prerequisites; new users explicitly active/gen1 and retained identities never reactivate through enrollment. |
| [Daily Admin](account-lifecycle-v5-admin-writers.md) | Append-only work models, complete pause rules, departments and precise own carry-in work with captured local Admin authority under the same lock. |
| [Snapshot authority](account-lifecycle-v5-snapshot.md) | Actual native read-snapshot actor checks reject stale/inactive/source/role/password authority before any artifact; fresh synthetic backup/stream/cleanup works. The existing V4 Runtime export is preserved. |
| [Profile digests](account-lifecycle-v5-profile-digests.md) | API and both LDAP metadata projections bind the complete distinct V2/V3/V4/V5 signature chain; V2/V3/V4 digest bytes remain exact. |

The combined evidence exercises real existing providers/facades/writers and SQL
on disposable synthetic sources, not only constructors or new display flags.
It includes stale in-flight authority across deactivate/reactivate, exact saved
receipt behavior, rollback/refusal, policy drift and older-profile preservation.
There is no new transaction manager, guard, service, formula or arbitrary callback.

## What this does not make operational

The V5 public Runtime hold remains unchanged. In particular:

1. The physical PDO/native source admission, immutable profile propagation and
   Runtime writer-factory flags must be composed completely for V5 before its
   hold is reconsidered. The current factory intentionally cannot open V5.
2. Existing NEW-only file preparation, first-admin token/app/API/LDAP-key file
   provisioners, FreshWeb and physical shared-budget providers still require
   their reviewed complete operating profiles. No key/token/account/host setting
   is provisioned by publishing this code. The separately prepared dormant V5
   file producer is **not a compile or functional-core dependency** and is not
   bundled here; a future NEW-only operating installation still needs a reviewed
   V5 file/provisioning path.
3. Public controller/route/UI selectors that explicitly select V4 remain V4,
   rather than disguising V5 as `isV4()`. Underlying V5 policy/withdrawal cores
   working does not by itself mount their public UI.
4. Remaining terminal-Admin facades/writers need complete captured-generation
   handling. The independent V4 terminal-credential-enrollment feature is not a
   core dependency and is not bundled, although its source overlay was tested.
5. Native-V5 refusal before Native DDL, known-good Laravel replacement and local
   Admin recovery must be established; do not route Native PHP to a new V5 file.
6. Real setup/login/session/CSRF/browser, worker/request ceilings, filesystem/
   concurrency, crash/restore/production-size and human recovery acceptance are
   separate. A component journey is not an operational cutover or export permit.

## Composition with other work

The independent #54 team-week reader, #68 optional workflow-read projection and
#57 durable browser pending gate share no core source paths. #57 changes only
its two fixed client hashes in FreshWebConfiguration and preserves the server event
payload/namespace contract. Pending browser events remain user/session-token
bound; the existing Laravel login/logout/session-refresh lifecycle rotates that
token and the server still checks V5 generation before receipt replay.

Terminal enrollment shares writer/provider files but changes separate fixed
operations; its exact overlay was applied cleanly and both enrollment journeys
were exercised together. Independently queued work must retain the owner's
serial order; any later Main change still requires an exact re-read/reanchor.

No Runtime/FreshWeb/Native/route gate, key/secret, operating source, deployment,
new CI dispatch or remote write is performed merely by assembling this packet.
Do not present this as a completed, activated Laravel migration.
