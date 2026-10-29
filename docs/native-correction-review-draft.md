# #50 — Keep a rejected Native review reason in its existing form

Source basis: Main `efc83e80607506cabfb4cd12f0e5c6626d71b229`, tree
`c237fe65a57d3cd5559dcaef9a0111131ee2c6f4`.

The Native correction page previously cleared a reviewer's typed decision
reason after every domain rejection. It now keeps only that bounded exact text
in the response when the existing core throws a known `AttendanceError` with
status 400/409 **before** the command returns and the caller confirms rollback.
This reuses the existing request-draft safety boundary; it does not change the
core, its transaction, permissions, checks or rejection precedence.

The definitions-only helper, already merged unchanged for the Laravel form in
PR314, accepts only a canonical positive request ID within
the existing PHP integer range and a string reason of at most 4096 bytes.
It neither trims, coerces nor truncates values. Malformed/oversized values are
omitted. The display limit does not change the core's existing 3–1000-byte
accepted-reason contract or the form's existing browser attributes.

The text is HTML-escaped and appears only in the exact matching request's
**existing** pending/non-self/Admin-or-Supervisor review form on the freshly
loaded, already-scoped visible queue page. The existing SQL, role/team scope,
counts, status filter, pagination, target selectors and four-eyes rule remain.
A request outside that page, no longer pending, self-owned or not reviewable
does not gain a form or receive the text. This is not an additional permission
check or a stronger identity/snapshot guarantee than the existing page provides.

No decision is retained or selected. The user must deliberately review the
current state and choose Approve or Reject again. Nothing is sent again, stored
in session/browser/database, carried to another target/page, or recovered after
an unknown writer/commit outcome. Returned commands, 403, unknown exceptions,
unconfirmed rollback and GET do not retain a reason. Existing own-request drafts
and read-only balance previews are unchanged.

Bounded source/anonymous in-memory page checks and PHP syntax are evidence for
this display guard only, not real authentication, CSRF, browser, competing
review, crash/lost-COMMIT or production acceptance. No new repository test suite
or workflow, Runtime gate, LDAP/API/terminal channel, credential, host or deployment.
The Laravel review form is unchanged. #50 remains partial/open; the unrelated
PR #239 change and its denial remain untouched, with no dependency or retry.

## Current additive composition

Only `adjustments.php` and this note change. The shared helper is reused byte-for-byte;
no new helper or Laravel change. Existing metadata-request drafts, saved-banner
error suppression, metadata previews and all current request/core/queue/SQL bytes
remain intact outside the three display-only additions. No Native date helper from
held PR239 is included. A current coarse same-response anonymous Memory journey
reuses the existing Native component proof; actual auth/HTTP/CSRF/browser and unknown
physical-COMMIT recovery are not inferred.
