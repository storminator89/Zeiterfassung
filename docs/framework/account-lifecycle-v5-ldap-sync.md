# #30 — dormant V5 LDAP sync/session slice, not runtime activation

Dependency: reviewed Web-generation tree
`63f5a704c49e011080f5da06043b80c5f3397d3a` on Main `d907c15d`.
This incremental source is prepared for reanchoring onto Main `bbb5d055`;
that reanchor must preserve its unrelated pairing-limit/site changes.
**V5 stays CLOSED; #30 stays OPEN.** No source opener, route capability,
Native boundary, installation/profile/key, UI or operating account is activated.

## Fixed authenticated sync boundary

The existing local-Admin sync facade captures explicit V5 authority from the
refreshed guard, never command data. Its existing read snapshot checks current
active source/role/password/generation and syntactic local login eligibility
before resolving transport or cipher sources. The snapshot closes before fake
or real collection. The completed materialized stage carries no actor selection.
The existing sole IMMEDIATE writer rechecks captured authority and the persisted
intake revision/allow flag after collection, before settings or account changes.
Missing V5 context fails closed. V4 still uses its original optional-free call.
`IntakePolicyStore` accepts V5 only through an explicit trusted read/require-allowed
argument and exact `[1,2,3,4,5]` ledger; its other policy-write path stays V4-only.

The whole eligible stage is checked under that lock for retained username
collisions before settings/departments. A local or unknown-source row refuses
the whole import, including the last local break-glass account. Existing LDAP
rows keep ID, source, role, password placeholder, status and generation while
permitted directory attributes/manager assignments retain their old policy.
Inactive/departed rows remain retained and collision-protected. An omitted row
is never interpreted as departure; a present row is never reactivated.
New LDAP rows explicitly use ordinary role, `active` and generation `1`.
No drop/recreate, local conversion, directory-status inference or new connector
is introduced. Default Native/V2/V3/V4 import branches retain their old behavior;
the optional V5 importer flag is trusted writer composition, not public input.

## Separate LDAP session revocation

This existing action still rotates only the local LDAP session placeholder;
it is not account deactivation, a directory password change or terminal lock.
Its V5 confirmation purpose is distinct from the unchanged V1 ticket. The
opaque framework-encrypted V5 ticket binds actor ID/password/generation and
target ID/password/status/generation, with the same existing bounded lifetime.
The actor read is generation-bound in the same snapshot. The writer rechecks
both actor and target under its sole lock before generating a placeholder.
Old target or actor authority cannot revive after deactivate/reactivate; a
committed placeholder rotation makes replay stale. Revocation can still target
an already inactive/departed LDAP user without changing lifecycle or any device,
credential, attendance, session-of-work or retained history row.

## Bounded evidence and remaining gates

One coarse synthetic PHP 8.5.12 journey uses anonymous canonical fixtures,
removed disposable SQLite files, an in-process fake directory, a fake opaque
ticket envelope, and an invented in-memory cipher root (never an actual key).
It checks retained inactive/departed authority/history, explicit new defaults,
empty-search non-departure, local collision rollback, stale in-flight actor
refusal after fake collection, fresh acceptance, actor/target ticket non-revival
and replay, inactive-target revocation, changed/all-off policy, V4 original writer
signatures and V1 ticket/revocation, plus the still-closed V5 runtime opener.
Independent static review found no actionable issue in the nine PHP-file diff.
Syntax and exact source preservation are separate evidence.

No HTTP/browser/CSRF/cookie, actual LDAP/root/key file, concurrent process,
injected commit-failure, restore, Native closure or human recovery acceptance
is claimed. Local enrollment, lifecycle UI, other read/write stacks, API and
terminal require their own coherent union and acceptance. The profile remains
unusable through `RuntimeDatabase`; this narrow implementation never removes
that guard and does not authorize a GitHub write, CI dispatch or deployment.
