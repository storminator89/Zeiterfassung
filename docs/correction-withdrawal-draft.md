# #50 — Keep a refused withdrawal reason in its existing form

Source basis: Main `79a31ae9d6cde340598a70bd65691273a41424ad`, tree
`49d9a54ecf9f79c722e5d39be8967e2639c27e03`.

The existing Native and Laravel withdrawal forms previously cleared a typed
reason after a known refusal. They now retain only that bounded exact text in
the same response, following the already-existing request/review draft pattern.
The existing withdrawal command, accepted reason rules, owner scope, four-eyes
review, transactions and journal are unchanged.

Retention requires an `AttendanceError` with status 400/409 **before** the
command returns. Native additionally requires its existing confirmed rollback;
the unchanged Laravel writer already converts uncertain rollback or attempted
commit outcomes to generic exceptions. The controller now also marks a returned
withdrawal command completed before constructing its redirect.

The definitions-only helper accepts a canonical positive PHP-range request ID
and a string reason of at most 4096 bytes. It does not trim, coerce or truncate.
That display bound does not change the core's existing trimmed 5–1000-byte
reason contract or browser attributes. Its ID is only a matching marker: the
form target still comes from the freshly loaded, already-scoped queue row.

The exact reason is HTML-escaped into only the same still-pending own visible
request with empty decision metadata. Native also reuses its existing local
source/version display predicate; Laravel requires its existing withdrawal and
interaction flags. Hidden, foreign, final, malformed, off-page or unsupported
requests cannot receive the text. No new form or permission is granted.
The Native details card opens for that one retained reason; an inline note in
both existing forms asks the user to check the current request and deliberately
choose withdrawal again. Nothing is automatically sent.

GET, returned commands, unknown errors/outcomes, unconfirmed rollback, other
statuses and malformed/oversized helper fields retain no reason. No draft is
stored in the database, session, browser or redirect, and none survives reload.
Other request/review/metadata drafts, filters, pagination, SQL, parser, writer,
routes, runtime gates, assets and configuration stay unchanged. Denied PR239's
Native time helper and other held changes are not dependencies or included.

## Bounded verification

PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0 with the unchanged Composer lock:
one anonymous-memory Native page/core and Laravel controller/read-SQL/DTO/
compiled-Blade journey covers known refusals, exact large IDs and escaped text,
foreign/final/malformed/off-page requests, invalid fields, GET, disabled flags,
unknown writes/outcomes, returned commands and explicit successful submission.
Native rejection preserves every business/audit row and session; Laravel uses
a fixture withdrawal adapter to the actual shared core, not an admitted runtime
writer. No credentials, keys or file database are generated or used.

This is not full bootstrap, auth/CSRF-stack, HTTP/browser/layout, physical
lost-COMMIT, concurrency, runtime/production or deployment acceptance. Necessary
syntax/diff checks and independent source review are separate evidence; no new
repository suite, workflow or manual CI dispatch. #50 remains partial/open.
