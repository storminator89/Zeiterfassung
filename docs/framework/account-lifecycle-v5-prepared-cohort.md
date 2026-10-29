# Prepared V5 source cohort — useful components, operating profile still closed

**Not an activated migration or a usable V5 installation.** The source basis is
Main `03e46d19777ac1e5415a5f7fe0717784074dc64a`, tree
`2969faa5e5d219c6fa498918729e64c9773c9a27`, after #260/#261/#262. Its Native correction
draft/preview, unresolved-reference status, complete operation filter and all
other unrelated source bytes remain intact. Local synthetic
Git commits are not upstream ancestry. This source packet performs no GitHub
write, CI dispatch, deployment or real setup/account/key operation.

## Existing work reused instead of recreated

The merged Main already includes canonical V5 schema/admission/ledger,
Web/API generation identity, terminal protocol and LDAP-sync enforcement,
Web business and daily Admin operations, enrollment/shared-budget writer,
read/snapshot authority, full profile digests and physical canonical empty-budget
validation. Those implementations are not reintroduced or duplicated here.

Five already independently reviewed local packets now apply cleanly to the same
basis. Applying all five in their original order **or the complete reverse order**
produces the same source tree before the final cohort documentation update. The
only direct overlapping path is this core-integration document; both the Runtime
and terminal-Admin statements are retained. Existing functional files are reused,
not rewritten to make tests pass.

| Existing packet | Useful demonstrated dependency | Still not admitted |
| --- | --- | --- |
| [Runtime profile](account-lifecycle-v5-runtime-profile.md), original `chrono-v5-runtime-profile-main25eb9778-support` | Fixed V5 PDO-state branch and immutable policy/V5 writer flags; pure state/flag matrix and all three unchanged pre-open refusals. | The new PDO branch and Runtime writer-flag propagation remain unreachable and **not physically executed**. No hold is bypassed for a test. |
| [NEW V5 files](fresh-account-lifecycle-v5-files.md), original `chrono-v5-new-files-maine954a448-support` | Actual unchanged Artisan boot/command on the assembled source creates distinct private empty V4 and explicitly dormant V5 cohorts, with full manifests/ledger and physical empty login budget. | No account, setup token or app/API/LDAP key is created. V5 receipt explicitly says runtime/setup unavailable. No existing-file migration. |
| [Five terminal-Admin operations](account-lifecycle-v5-terminal-admin.md), original `chrono-v5-terminal-admin-main091f843b-support` | Real existing provider bindings/facades persist site, credential/device revoke, module and device-pairing operations through full captured-generation authority; all five stale in-flight races refuse. | No terminal HTTP/key/profile selection, employee-credential-enrollment feature, real device/grant or operating setting is activated. |
| [Own password](account-lifecycle-v5-own-password.md), original `chrono-v5-own-password-mainc7abc178-support` | Actual Settings controller/CSRF/Blade/session and same-lock V5 password CAS/recovery/audit/logout; older V2/V3/V4 behavior retained. | No public V5 source/route or independent recovery/password-reset channel. |
| [Admin lifecycle flow](account-lifecycle-v5-admin-flow.md), original `chrono-v5-lifecycle-web-main2072a6e2-support` | Existing user table → one deliberate dialog/reason → CSRF → snapshot/ticket facade → existing CAS/recovery/audit → confirmed PRG or self-logout. | Its final optional interface is still **unbound**. No provider or route selects the new capability. #30 is not complete. |

## Bounded final-cohort checks

PHP8.5.12, Laravel13.38.0, locked dependencies unchanged. Existing component
harnesses rerun against this **one complete assembled source**:

- NEW V4/V5 private files, strict canonical fresh/budget/ledger verification,
  original V5 read/writer/snapshot refusal and owned-cohort cleanup;
- Runtime pure profile validation/truth table/refusal, without executing its new
  unreachable PDO branch or adding a test bypass;
- actual provider resolution and all five terminal-Admin operations, five
  deactivate/reactivate races, inactive/nonadmin/no-authority refusal and older
  V4 operations;
- actual own-password Settings journey, current-password/form/quota/CAS/audit,
  generation/recovery and stale session/JWT handling, plus direct V2/V3/V4
  operation preservation;
- actual Admin lifecycle controller/Blade/CSRF and stock array sessions,
  deactivate/reactivate/departure, unchanged history/open interval, replay409,
  last-local-Admin protection, selflogout, stale actor/session/JWT/writer,
  confirmed receipt versus new-form failure and unconfirmed writer503/no retry;
- original V4 Admin receipt/render regression; actual rendered lifecycle script
  against minimal Node24.22.1 DOM doubles for cancel/reset/repeat/BF-cache reload.

Additionally, the actual unmodified `artisan chrono:prepare-fresh` entrypoint
runs in separate processes with the assembled checkout's own local autoloader,
first with ordinary V4 and then the existing explicit dormant-V5 flag. Both exit0
and create only their fixed six private cohort entries; versions, empty users and
login attempts, 11/12-field manifests, modes and unchanged parent sentinel are
verified. Both owned cohorts are removed. These are NEW disposable fixtures, not
an operating source or generated usable credentials. The first probe with a
symlinked older vendor failed App autoload resolution; using a private copy of the
same exact installed locked vendor plus ordinary no-script autoload regeneration
fixed that fixture setup. No dependency/version change or source workaround was
made.

Source/compiled-Blade/actual-JavaScript syntax, Composer strict validation and
platform checks, complete tree inverse/replay, original functional-source
preservation and cold transport import are separate evidence. These are bounded
coarse component/CLI checks, not a new repository test battery or GitHub-CI
workflow. Prior component documents accurately retain their historical test
boundaries; this cohort adds only the explicitly stated checks above.

## Remaining practical next dependency: coherent Setup/FreshWeb admission

The actual V5 file producer now works as a preparatory CLI component, but the
four setup/key provisioners still select V4 and then read through the closed
Runtime. `FreshWebConfiguration`, `FreshWebServiceProvider`, LDAP-sync metadata,
physical shared-login-budget composition and public V4 selectors still require
coherent review. Removing their V4 checks alone is neither functional nor safe.
Do not use an alternate private opener, generated token, preloaded PDO, fake
profile or a hidden user as a substitute for the unchanged Runtime hold.

Before any operating activation, separately complete and review exact V5
manifest/lineage/private-identity admission across NEW setup/token/app/API/LDAP
keys and setup closure, all ordinary Web/API/LDAP/budget/terminal selectors,
Native pre-DDL refusal/replacement and locally recoverable known-good Admin
operation. Then establish real complete HTTP/session/cookie/CSRF/CSP/browser and
terminal/directory acceptance, worker/concurrency/commit-loss/crash/restore and
human recovery. Only that later coherent evidence can justify reconsidering the
Runtime hold or binding the prepared lifecycle control. No operating approval is
implied by source composition, successful Artisan file preparation or synthetic
user-flow tests. #30 and the overall Laravel migration remain open.
