# Prepared V5 FreshWeb connects the existing own-request withdrawal flow

Source anchor: Main `350eda864f63a990955b0adecee7d4a1659a8ed9`, tree
`a0a56d3355d784df7ff3bee9c803383ed22c9881` after PR287. This closes a real
composition gap, **not the remaining operating Runtime gates**.

The existing corrections page already has an owner-only pending-request form,
controller capability check, lazy scoped facade and one-lock withdrawal writer.
Its V5 continuation already captures/rechecks complete account authority. Yet
FreshWeb selected neither the interaction nor the existing server capability,
and the registrar's optional withdrawal check recognized only V4. Merely having
the prepared core therefore could not expose this ordinary local V5 user flow.

## Small explicit composition, no duplicate implementation

The FreshWeb provider selects the existing withdrawal interaction and registrar
argument only from immutable `RuntimeDatabase::isAccountLifecycleV5()` metadata.
V4/default selection remains false, including the existing V4 LDAP composition.
No new config/environment opt-in, service binding, route, endpoint, form field,
request-selected source or write operation is added. The existing scoped
`LocalWebCorrectionWithdrawals` and same corrections POST are reused.

The optional registrar check now accepts separately explicit V4 **or** V5 while
retaining non-debug and bound-writer prerequisites. V5 is not labelled V4. The
whole V5 first registrar check, actual Runtime reader and local-account-path
eligibility snapshot remain unchanged and precede this check and all registrar
side effects. Today's original V5 Runtime hold still throws before routes or
`chrono_corrections.withdrawals_enabled` can be selected. A prepared display bit
in the provider is not an effective HTTP/write capability or Runtime admission.

## Original handler and writer contracts stay authoritative

All controller/facade/Blade/reads/Writer/core code is byte-identical. The existing
page shows the form only for a local owner of a pending request with both the
interaction and server capability. CSRF, untrimmed native-form middleware,
strict command fields and existing reason/ID validation remain unchanged. LDAP,
foreign, stale role/hash/generation, nonpending/replayed or malformed requests
do not gain authority. A user cannot select an actor, source, generation or
server capability through POST data.

The actual facade captures the refreshed guard model through the existing V5
identity and invokes `withdrawCorrectionWeb`, never the generationless legacy
continuation. The original one-attempt IMMEDIATE writer rechecks that captured
identity under its sole lock before the existing owner/pending/null-review
checks and UPDATE/audit. Original proposal/reason/snapshot, reviewer_id, booking,
sessions and unrelated rows remain retained. This is withdrawal, not a reviewer
decision, deletion, reopening or automatic resubmission.

Only the existing writer's return after its transaction lifecycle allows the
controller continuation. Its fixed unconfirmed-error/no-automatic-repeat wording
and current rollback/COMMIT uncertainty handling remain unchanged. This packet
does not certify injected rollback/COMMIT failure or repair another writer's
failure shell. No new retry, draft retention, fake receipt or success inference.

## Still gated and evidence boundaries

Actual standard-provider boot compares the published original provider selector
with the fixed one on V4 local, V4 existing-LDAP, held V5 local and default/off.
Every V4/off interaction and effective configuration stays unchanged; only the
prepared V5 withdrawal display bit changes. Held V5 still has no effective
withdrawal flag, Web route, resolved withdrawal facade or writer. Incomplete
budget and LDAP/Sync/API V5 selections continue to refuse. No fictitious V5 route
or alternate Runtime opener is used to claim a successful route admission.

A separate actual existing-facade/file-only-writer component journey opens only
its own disposable canonical NEW V5 file for fixture data; that handle is never
bound to Runtime, the DB manager or a route. Static guard snapshots use explicitly
unusable password placeholders, not usable credentials or authenticated sessions.
It exercises lazy construction, strict command fields, own pending withdrawal,
one audit, retained original/unrelated data, foreign/Admin-foreign, invalid,
repeat and LDAP refusals, fixture-only status/generation changes with stale
refusal and current authority success, and an unavailable writer resolved once.
That unavailable-resolver case is not a real COMMIT/rollback-fault test. The real
Runtime reader still refuses before fixture work. The original anonymous-memory
legacy withdrawal journal also passes unchanged owner/hash/role/finality/history
contracts. These are component checks, not an admitted installation.

No Runtime/eligibility/Setup/key/auth/session/cross-channel/Native gate is opened.
No credentials, login, account provisioner, secret file or key/token generator is
used. Coarse component fixtures are not operating accounts or public Runtime
admission. Actual HTTPS/cookie/session/CSRF/browser, concurrent workers, I/O or
COMMIT/rollback faults, crash/restore and human recovery remain outside this
preparation. Do not remove the Runtime hold based on source selection or a
passing component journey; #30 and migration remain open.
