# #30 — dormant V5 Web-generation slice, not runtime activation

Depends on the reviewed NEW-only schema/admission and writer-core union tree
`2868309c6c7d8a828fb8f0359cf164bde180b6c0` on Main `d907c15d`.
**V5 remains explicitly CLOSED; #30 stays OPEN.** No route, Native boundary,
UI control, runtime opener, installation profile, schema or key is activated.

## Explicit profile; no missing-column inference

`AccountLifecycleWebIdentity` is built lazily from the immutable admitted
`RuntimeDatabase` profile, without opening its source. Existing V2/V3/V4 and
unconfigured profiles retain their current behavior. Merely finding V5-like
attributes on a model does not select V5. Existing constructor call signatures
remain valid; the application provider supplies the profile to its actual
container-composed services, including the selected shared-budget session action.
No new guard, session engine, hashing configuration or directory connector exists.

In V5, both the existing local/LDAP wrapper and the stock Eloquent local-login
branch require a current active row with explicit positive integer generation.
The latter uses stock `SessionGuard::attemptWhen`, not a custom password check.
LDAP still admits only the existing wrapper's ordinary directory users; local
break-glass does not depend on a directory call. Rehash/remember-me policy stays
unchanged, and directory credentials never become a local password hash.

## Login and refresh

Only successful credential login establishes `chrono_web_account_generation_v5`:
an exact two-field marker containing integer `user_id` and integer `generation`
from the trusted current model. A new V5 login attempt first removes any old
marker, so an interrupted guard login cannot reuse another login's authority.
The existing server-controlled source marker and Laravel password marker remain
separate and mandatory in V5. Refresh never creates/adopts a missing marker.
Null, scalar, malformed, wrong-user and stale-generation markers are refused.
The existing logout/invalidation and stock `auth.session` password check remain
responsible for their original behavior. Old V2/V3/V4 absent-source compatibility
is unchanged; it is not imported into V5.

Deliberate deactivate/reactivate advances the schema's generation. Fresh login
can establish the new authority; an untouched older session cannot revive even
though the account is active again. This does not claim request cancellation or
full concurrency/session-store acceptance.

## Existing read snapshot and two writer facades

Regular `LegacyChronoReads` projections capture V5 authority from the refreshed
guard before opening their existing read transaction. Inside that same snapshot,
current actor status/generation, ID, source, role and password fingerprint must
match. No raw credential/status snapshot is attached to a presentation DTO.
Only the actor is checked: an authorized active Admin/supervisor may still read
retained history belonging to inactive/departed subjects under existing scope.
Database export, intake/LDAP confirmation readers and other read stacks remain
separate gates.

The existing Admin edit/delete facade captures the same explicit authority and
passes it to the already reviewed writer core. That core rechecks it under the
sole existing IMMEDIATE lock, including the last-active-local-recovery invariant.
Command data cannot supply actor state or generation. Ordinary edits retain the
target's status/generation. This slice does not add a lifecycle command route.
All other V5 writer/facade operations remain deliberately refused by the writer
core; this is not a complete Web-mutation channel.

## Bounded evidence, not acceptance

One coarse invented PHP 8.5.12 component journey uses actual stock guards with
array sessions, a fake LDAP verifier, anonymous canonical fixtures and one
removed disposable SQLite writer file. It checks local/LDAP login and refresh,
marker strictness/non-revival, a stale in-flight read and Admin edit, fresh edit,
retained departed-subject history, and the older V4 stock local-session path.
Syntax and exact-source preservation are recorded separately.

No operating account/database/key/directory is used. HTTP/browser/CSRF/cookies,
real LDAP, remaining writer/read/enrollment paths, JWT/API, terminal grants and
receipts, NEW-V5-only Native refusal, concurrency, injected COMMIT failure,
restore and human recovery are not accepted. `RuntimeDatabase` still refuses
V5 before source access; this package is no permission to remove that guard.
