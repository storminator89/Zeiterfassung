# V5 login-budget and local enrollment core

Source anchor: actual Main `83717e8e56484b45a93c55d2901f054bfdcabdb9`,
tree `c177eba798eef8e77f23a0500eb843da6c78dfa6`. This packet implements
working component operations, not Runtime activation, secret provisioning or
terminal credential enrollment. Local snapshot commits are not remote ancestry.

## Fixed operations, not a generationless compatibility bypass

- `consumeLoginBudget` can consume V5 anonymous counters under the existing
  sole IMMEDIATE lifecycle, with exact admitted schema-cookie checks before and
  after the unchanged quota core. It returns allow/limited only after confirmed
  COMMIT. The existing shared Web/API wrappers map a committed refusal to429.
  A quota attempt intentionally has no authenticated account/generation and does
  not reset counters when accounts deactivate/reactivate.
- `firstLocalAdministrator` can initialize only an empty V5 user table under
  that lock, independently of all three intake switches. Its V5 branch requires
  a syntactically locally login-eligible bcrypt Admin and explicitly inserts
  `active`/generation1. The existing token/config/form gate in `FirstAdminSetup`
  is unchanged; this writer method is not a new public setup or token bypass.
- `registerLocalUser` rechecks the exact V5 policy revision/registration flag
  under the same lock and requires an **active usable local Admin**, not merely
  an inactive/directory/Admin-role row. It inserts an ordinary local user as
  `active`/generation1. Existing username/email collisions are retained; no
  update, source conversion, status reset or implicit reactivation occurs.
- Manual `createLocalUser` and `changeIntakePolicy` require a captured V5 local
  Admin authority matching id/source/role/password/generation under the write
  lock, before intake or account/policy DML. Existing assignment validation,
  CAS/revision, audit and work-model triggers remain authoritative.

Anonymous setup/registration/quotas do not invent an Admin identity or accept a
request generation. Their explicitly bounded writer continuations own their
distinct empty-state, policy/recovery or schema-cookie prerequisites. Every
other generic V5 operation remains closed. No arbitrary lifecycle-ready option
or second transaction manager is exposed to a caller.

## Existing service composition

`IntakePolicyAccess` receives the existing immutable `AccountLifecycleWebIdentity`
through AppServiceProvider. Its Admin snapshot captures a trusted refreshed guard
model, checks the current V5 tuple in the same read transaction as policy, closes
the snapshot and passes authority **internally** to the existing manual facade
or policy writer. Authority is never sent to a view/form/session. The writer
rechecks it after any intervening deactivation/reactivation or policy change.

`isV4()` remains strictly V4. `isV5()` reports the exact separately admitted V5
identity profile; simultaneous V4/V5 selection is refused. Missing/mismatched
composition cannot fall back to generationless manual V5 creation. Existing
V2/V3/V4 calls retain their defaults and SQL; an explicitly supplied V5 authority
is rejected on older profiles rather than silently ignored.

## Evidence

One coarse disposable component journey starts with a canonical V5 schema and
four-row ledger, then deliberately prepares an invented all-off fixture state.
It exercises persisted anonymous shared quota/429, wrong-cookie refusal,
empty/nonempty bootstrap, actual provider-bound policy/manual/registration
services, explicit new-account defaults, retained inactive duplicates, Admin
generation changes between read snapshot and writer, changed policy revision,
fresh authority success and refusal of an invented out-of-band state with no
usable active local Admin. It also exercises older V4 quota/bootstrap and V2
cross-profile authority refusal. The original source fails on its first V5
quota operation. Only test-owned private temporary files and clearly invented
password/hash data are used; all temporary files are removed. No token/key/setup
provisioner, real login or directory is invoked.

This proves component SQL behavior, not operation of an admitted installation.
The unusual all-off/no-recovery fixtures are not claims that the fresh producer
emits them or that a real restore was validated. Actual setup-token/config/CSRF,
HTTP/browser/session, concurrent workers, COMMIT failure, crash/restore, human
recovery, directory and terminal acceptance remain outside this journey.

## Still closed before deployment

`RuntimeDatabase`, its current writer factory, FreshWeb, SharedLoginBudget's
physical provider, setup/key provisioners, public routes and Native ingress are
unchanged. In particular SharedLoginBudgetServiceProvider still requires its
existing complete V4 physical profile: this patch does not allow a V5 web login
to bypass the missing coherent Runtime composition. Admin controller capability
checks that explicitly select V4 are not silently widened via `isV4()`.

The [daily Web](account-lifecycle-v5-web-business.md),
[Admin](account-lifecycle-v5-admin-writers.md) and
[snapshot-authority](account-lifecycle-v5-snapshot.md) cores are now included in
the [combined source integration](account-lifecycle-v5-core-integration.md).
Complete admission still must compose exact V5 selectors, remaining terminal-
Admin writers, safe Native-V5 refusal and local recovery, then separately perform
real operational acceptance.
Neither a passing component journey nor a NEW-only file receipt authorizes
removing the global Runtime hold or publishing/activating an installation.
