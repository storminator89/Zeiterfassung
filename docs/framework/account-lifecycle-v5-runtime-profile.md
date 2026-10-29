# V5 Runtime profile propagation — still closed

Source anchor: published Main `25eb97783d148d067dfc488c7f7d6c8c40cbceb3`,
exact tree `de4322d6ad20e10db8f6f44279f7682238701696` after PR235.
This is a necessary source dependency for a future complete V5 composition,
not an operating admission, installation, migration or Runtime activation.

## Fixed existing Runtime path

The existing PDO opener now has the same explicit V5 application-state branch
as the existing native snapshot ingress. After the unchanged admitted file,
driver/settings and complete four-row lineage-ledger checks, it validates the
actual PDO's ordered application versions `[1, 2, 3, 4, 5]` and its one strictly
typed intake-policy row through `RuntimeAdmission::assertAccountLifecycleState`.
The unchanged final file-identity check still follows that branch. No supplied
row array, optional user field, request flag or current-schema inference selects
the profile; the immutable admitted manifest remains authoritative.

The existing writer factory passes `accountLifecycleV5` only for that explicit
V5 admission. Its internal `intakePolicyV4` capability is true for V4 **or V5**,
because the V5 writer retains the V4 policy substrate and already requires both
flags. This does not change public `isIntakePolicyV4()`: V5 is never advertised
as V4, and no V4-only route, provider or setup gate is widened. The immutable
admitted login-budget schema cookie is unchanged. V2/V3/V4 retain their original
flag values, SQL branches, file settings and transaction lifecycle.

## Evidence and explicit limit

PHP 8.5 syntax, strict Composer/platform checks, exact older-profile source
preservation and an independent static review cover this bounded change.
The global `requireImplementedRuntime()` body and every call before PDO/native
open or export remain unchanged. Consequently the new V5 PDO/factory branches
are intentionally **unreachable through the current Runtime**. They are not
claimed as executed physical V5 Runtime acceptance. No reflection, source
rewriting, bypass flag or alternate opener is introduced to manufacture such
evidence. Existing V5 component journeys do not remove this limit.

## Still required before any operating cutover

- Reviewed NEW-only V5 files, setup token, application/API/LDAP keys and complete
  FreshWeb/API/LDAP/shared-login-budget operating composition.
- Explicit V5 HTTP/UI selection and captured-generation handling for remaining
  terminal-Admin operations; never alias V5 into `isV4()`.
- Native V5 refusal before native open/DDL, known-good replacement and local
  Admin recovery.
- Real setup/login/session/CSRF/browser, filesystem/request ceilings,
  concurrency, crash/restore, production-size and human recovery acceptance.

Only a separately reviewed complete composition may reconsider the Runtime
hold. Do not remove the hold in isolation. Issue30 remains open.
