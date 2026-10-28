# Owner withdrawal of pending corrections (#50 / #79)

Source-only, separately default-off slice on Main
`b482b1cd7f5aa22580747452cd710e2a7c103721`. It does not duplicate the
Berlin time helper merged in #214 or claim a complete account-balance preview.

## Bounded rule

A current **local** user may irreversibly withdraw only their own still-pending
request, with a 5–1000-byte trimmed reason. Admins/supervisors gain no right to
withdraw another person's request. LDAP remains request-only. A fresh same-lock
role/source/password-fingerprint check precedes the owner/pending lookup.
Unusable or stale principal context refuses. No request may supply an actor,
role, fingerprint, reviewer, new status, timestamp or original snapshot.

`pending → withdrawn` is a terminal owner action, **not** approval or rejection.
Only `status`, `reviewed_at` (completion time) and `review_reason` (withdrawal
reason) change; `reviewer_id` remains NULL. Pending rows already carrying any
review metadata refuse rather than overwriting it. The proposal, original JSON,
request reason/creation time, booking, sessions, events and prior audit remain.
The existing UPDATE audit trigger appends exact before/after and the owner plus
withdrawal reason in the same transaction. No delete/reset/reopen or automatic
retry exists. A later correction requires a new request and independent review.
The existing request/review core and its self-approval ban remain unchanged.

## Schema and read compatibility

The existing `time_adjustments.status TEXT NOT NULL DEFAULT 'pending'` has no
status CHECK. Its existing `adjustment_closed` trigger rejects every subsequent
update of a non-pending row; `adjustment_no_delete` retains it. No migration,
DDL, new table/column/trigger, schema cookie/signature or admission rewrite.

Native and Laravel queue labels/filters now recognize `withdrawn`; native POST
still cannot perform withdrawal. Bounded history correlations and their public
export accept the retained status with unchanged owner/team scopes, limits,
selected fields and identifier rules. History labels distinguish withdrawal
metadata from review metadata. Consumers assuming exactly three status strings
must be updated before a later activation. No existing retained status is
converted and no applied booking or account balance is changed.

## Separate composition, existing UI

The existing registrar has a trailing explicit `includeCorrectionWithdrawals`
boolean, default false. True requires a non-debug explicitly admitted writable
V4 profile. It sets a server-owned route capability; the controller checks it
before dispatch. The separate `Interactions::correctionWithdrawals` defaults
false and gates the existing-card form along with corrections/local identity.
Display flags are not backend authorization. Shipped FreshWeb, default and
fixture profiles do **not** select either new capability. The lazy service and
writer method open nothing by construction; no new public endpoint is added.

The normal POST/CSRF/refreshed guard/auth.session/session-blocking chain remains.
Only explicit submission of the existing-card withdrawal form can dispatch the
fixed command. The trusted writer uses its existing single-attempt IMMEDIATE /
COMMIT / idle lifecycle; success redirects only after that returns. An unknown
failure asks the user to reload/check, not retry automatically. Existing request,
review, selection, queue pagination, Berlin helper and manual ISO fields remain.

## Bounded evidence and remaining gates

One coarse anonymous `sqlite::memory:` source journey on PHP **8.5.12** used the
existing synthetic fixture and unusable placeholder password hash. It covered
owner withdrawal; foreign/role/hash/input/repeat refusal; unchanged schema cookie,
request payload/original and booking tables; exact appended before/after audit;
closed-state reset/delete/review refusal; and actual bounded retained history
correlation/export. Native labels were checked as source, not by booting the
native page. The full admitted AttendanceWriter, service/controller, HTTP,
CSRF/session, real concurrent writers, commit failures, browser and production
flows were not functionally exercised. Source review and syntax/Blade checks are
separate evidence, not operational acceptance.

No real database, account, credentials, keys, LDAP, operational data, host,
runtime configuration or deployed site was accessed/changed. No flag enabled,
server started, remote repository written, new CI run requested, or feature
activated. Account-balance preview, LDAP withdrawal policy, delegation/escalation,
full #50/#79 acceptance and native V4/#197 cutover remain separate open work.
