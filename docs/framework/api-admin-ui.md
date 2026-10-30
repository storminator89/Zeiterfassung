# API administration after initial setup — local V5

The root Laravel standard starts with the API **off**. After the token-free
browser first-Admin setup has closed, an active **local** Admin can manage it in
**Administration → API verwalten**. No API environment-file edit, Docker socket,
API CLI, restart or signing-key copy is needed for these normal actions.

## Browser actions

1. Open the card: it shows the effective operating state, nonsecret key status
   and policy revision. The card creates nothing merely by being opened.
2. To enable for the first time, enter your current own password, explicitly
   confirm enabling, and separately confirm creating an independent private API
   signing key. Submit **API-Schlüssel anlegen und einschalten**.
3. A successful redirect confirms the committed policy. Subsequent enable/disable
   actions also require your password, CSRF and explicit confirmation. An old
   tab/repeated submission cannot overwrite a newer policy revision; reload and
   deliberately choose again.
4. Clients use the existing `/api.php/login` and compatible authenticated API
   endpoints. The UI never shows the signing key and creates no personal bearer
   token. It does not make an anonymous API or change a user's password/role.

**Off is a temporary channel switch, not token revocation.** It blocks new API
policy checks and mutations, but does not rotate/delete keys or tokens. An
in-flight read/login that already passed the ingress check can finish; every
V5 API mutation/replay checks again under its sole writer lock. A mutation that
already holds the writer lock completes before a subsequent Disable commits.
Unexpired tokens may work again on re-enable; existing account-generation,
suspension and password-change revocation remain independently effective.

## Scope and authorization

The operator capability `FRAMEWORK_API_UI_MANAGEMENT_ENABLED` defaults false in
application config; the root standard Compose sets it **true**, not API-enabled.
The UI binds only in the existing production/non-debug admitted Fresh V5
composition, with the exact private manifest/cohort, completed `setup-closed`,
setup flag false, shared login budget and existing session/CSRF/stock FileStore
lock guards. Before closure it is unavailable. V4, native, V6-HOLD, alternate
non-Fresh profiles and directory Admins do not gain these controls.

Each action captures active local Admin authority and verifies the current
password, booking a distinct persistent attempt budget **before** password
verification (5/user, 20/IP per 10 minutes). Under operator-then-writer lock it
rechecks source, role, stored password version, account generation and expected
policy revision **before** new file reservation or entropy. Normal account,
password, last-Admin, API JWT format and API authentication rules are unchanged.

The effective policy is a reserved `api_service_policy` append-only event in the
existing audit log, with secret-free before/after state and CAS revision. No V5
DDL/schema change, generic settings SQL or environment rewrite is introduced.
The reader checks canonical state and continuity of the latest two events;
this is **not** a full-history-chain or privileged-database-tamper proof. The
first explicit Disable is persisted even when the source default is already off.

## Independent private signing key and uncertain outcomes

The new post-setup producer is separate from the old NEW-only/stopped CLI. That
CLI's empty-installation checks are unchanged. Only the fixed private cohort
paths are used: `jwt.key`, `framework-api-key.env`, and permanent
`api-management-key.claim`. Exact owner/mode/single-link/ancestor/manifest and
same-DB admission checks apply; there is no request path/metadata/key selector.
The independent key uses the existing ASCII-64 signing-key format and original
typed ten-field metadata/admitted read-only key source. App-Key is not reused.
Neither key bytes nor metadata paths enter the UI, flash, policy event or error.

Exclusive claim and both file reservations precede entropy. Writes are
flushed/fsynced and identities checked; the claim/partial files are **retained**
if any later step is uncertain. The operation never overwrites, deletes, rotates,
automatically repairs or automatically regenerates a missing/partial key. A
complete original admitted key is validated/reused, never replaced.

Filesystem preparation and the DB policy COMMIT are not one atomic operation.
After interruption/error a key may exist while policy is still off; a later
explicit authorized enable may validate an intact pair. Partial output blocks
reenabling and needs private operator diagnosis; do not delete/reset files to
force another attempt. An unconfirmed COMMIT never produces a confirmed success
or rollback claim. No directory-fsync/power-loss/recovery acceptance is claimed.
Backup the **complete** private cohort, including the claim, original keys,
manifest and setup closure, under the existing stopped/admission contract.

## Existing API installations and upgrades

Before deploying this feature, **stop/drain every old Web/API/CLI worker and
exclude restart; replace them together**. Old binaries do not understand the
new journal and could ignore Disable. This is not detected at runtime; mixed
versions or rolling back to an older image are unsupported once policy is used.
Do not claim older-code rollback or mixed-worker fail-closed guarantees.

An already explicitly admitted Env-selected V5 API keeps its original default
and original key source until a policy action. The new ingress and writer guards
honor a saved Disable even on that legacy source and even if UI capability is
later removed. It cannot bypass a policy entry just by remaining Env-enabled.
V4's original API selection is unchanged. The standard UI does not need the
older `compose.api.yaml` overlay; do not combine it casually or append settings
to generated key files. Alternative compositions require a separately reviewed
capability deployment; no existing user's environment is edited by this source.

## Bounded source evidence, not operating acceptance

Coarse probes use a disposable canonical V5 cohort and the actual first-Admin
closure, standard providers, service, admitted Runtime, sole writer and policy
checks. All password/session/CSRF/key values are fixed public dummy fixtures;
password verification/accounting are explicit stubs and key entropy is a trusted
fixed/throwing test callback. No real credential/hash/JWT/session generator,
listener, actual installation or persistent user setting is created/changed.
Compiled DE/EN/ZH forms and selected failure/authority/revision/retained-partial
paths are covered. A real SQLite BUSY-on-COMMIT probe rejects an unconfirmed
result, retains its complete pair and permits only a later explicit enable
without new dummy entropy. This does **not** attest lost COMMIT acknowledgement,
real HTTP/CSRF/login, browser/iOS, Docker/TLS, crash/power loss or production recovery.
The user retains actual browser acceptance. Do not run real key creation merely
because this source/PR was reviewed or its natural CI succeeded.

## Rejected-attempt feedback — partial #79

Source Main `a929d308a47be011a7c7f02fea7a50ee0607e1f6`, complete tree
`ed4ad1eeb553b1beb2f0709e196c7598df7d6ad0`. An unconsumed earlier
`chrono_api_notice` could previously appear as a saved-success notice beside a
new rejected API-management POST: known 400/409 outcomes rerender the existing
Admin page, which consumes that old receipt. The current error did not make the
older saved-state receipt confirmation of the new operation.

A new already-dispatched API-management attempt now forgets only that previous
non-authorizing display receipt before the unchanged service invocation. A
current rejection or unconfirmed outcome cannot inherit an older success. The
same existing successful 303 redirect still creates the same minimal new
one-use receipt; an ordinary GET still consumes a legitimate receipt normally.
No submitted password, token, confirmation or policy form is copied to session,
URL or response. No automatic retry, new draft, rollback claim or state repair.

Only two existing paths change: this note and two inserted controller lines.
API service invocation/arguments/remote address, exception classes/statuses,
source/role/CSRF/admission/budget/password/confirmation/revision checks,
key producers/files, policy/audit readers and writers, fresh status reads,
forms/DTOs/locales, dispatch/routes and all runtime/host/security settings remain
unchanged. This is not permission to activate, disable, provision or otherwise
change a real API or key. The non-authorizing session notice is not policy state.

### Bounded component evidence

PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0: actual old/new Admin controller,
canonical business reader on fabricated query-only anonymous Memory, compiled
actual Admin content/API partial and existing display DTOs; fake RequestGuard
and `LocalWebApiManagement` outcome port, inert shared layout. Baseline old
success beside new 400 reproduced. Eighteen 400/409 combinations cover all three
old notice values in DE/EN/ZH: only the current escaped error remains, with
unchanged single service call, complete arguments and remote address.

Original GET one-use consumption, fake known-success 303/new minimal receipt,
unknown 503 without retry or source diagnostics, unchanged authentication/
authorization/budget exceptions, unavailable fresh status, and mixed/member/
directory refusals/form hiding are covered. No submitted dummy password/token
persists or renders; unrelated session marker, source/schema/change counters,
query-only/transaction state and native session/timezone remain unchanged.

No actual API policy/password/key service invocation, real account/credential/
key, file DB, server, operating setting or deployment. No full App/bootstrap/
V5-admission/auth/CSRF/HTTP/browser/layout/runtime/production/performance or
concurrency acceptance. Necessary PHP/compiled-Blade syntax, exact source/tree
preservation, inverse/replay/cold bundle and independent review are separate
checks; no new repository testsuite or manual CI dispatch. #79 remains partial.
