# Find one own record blocking the existing CSV export

Read-only usability improvement for #55, prepared against actual Main
`8ba8809cd56de8f676acbaf3c6b5733b5acd5282`, tree
`09b56ceb5d6ebf21a3dd33e782e12ea186d35925`. The existing invalid-record CSV
rejection did not identify any offending record, so the owner had to search the
list before using the already-existing correction workflow.

The same `409 text/plain` response now appends one strictly validated example ID
and a copyable relative `adjustments.php?record_id=...` path. It is the **first
already-invalid row in the same complete authorized own snapshot**, never a
second lookup. Native keeps its original complete scan and captures that row;
Laravel uses the exact row at its unchanged first-invalid return. The original
fixed rejection text remains the prefix. If this row has no canonical positive
ID, the helper adds nothing: no guessed, aliased or repaired target.

The helper reuses the existing exact correction-selector ID parser. Only fixed
text and the parsed bounded decimal ID are returned, not timestamps, reasons,
notes, owner/reviewer fields, errors or other rows. Native and Laravel use the
same helper. It is a formatter of already-authorized data, not an access gate.
The link re-reads current state under existing own/role checks, which may have
changed since export failure; it neither confirms current existence nor submits
or retries any correction. Other rows may also block the export. The example can
be outside a selected CSV period because full-own-snapshot quality admission
still precedes period selection.

No new query, owner selection, validation/blocking rule, status/content-type or
attachment change. Existing conflict-first precedence, period parser, successful
CSV bytes/filename/serializer, technical errors, role/CSRF/writer/repair/schema,
dependency/runtime/host/security behavior remain unchanged. No HTML page,
JavaScript, new route, data repair or deployment. Held/denied packets are not
transported. #55 stays partial/open.

## Coarse evidence and limits

PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0 with unchanged preexisting Composer
lock: original-schema anonymous Memory, actual named own snapshot reader and
actual old/new CSV controllers. 22 comparisons cover user/Admin, full/period/empty
success bytes, ignored foreign subject query, invalid rows outside selected
period, first-of-multiple failures, conflict-first and malformed-period behavior.
Six newly useful rejection paths show the exact `9007199254740993` example and no
other/foreign/raw-source fields. Fifteen malformed ID forms produce no hint;
canonical IDs through PHP_INT_MAX remain exact and output is under 500 bytes.
A missing-table fixture preserves the generic 500 without a misleading hint.

Eight old/new captured Native post-auth body comparisons exercise its actual
bound own query, admission gates and serializer; body/status/headers match
Laravel. Only native header/status calls are captured and exit becomes a fixture
return, not a real HTTP request. Source/schema/audit/change counts/query_only,
transaction cleanup and native globals remain unchanged during reads. Three PHP
lints and diffcheck pass. An initial external digest assumed the deliberately
removed fixture table still existed; only the harness was corrected.

RequestGuard is an identity double. Native bootstrap/auth/session/CSRF, full App,
HTTP/browser/download/layout/production/concurrency and current target navigation
are not accepted here. No real data/accounts/credentials/keys/file DB, writer,
fixture server, new repository test suite or manual CI dispatch. Natural exact-head
and Main CI are followed separately; browser acceptance remains with the user.

Evidence: `/tmp/chrono-csv-quality-hint-main8ba8809c-support/` contains coarse.json,
proof.json, candidate.patch and independent-review.json. To conserve temporary
storage, this source clone shares its existing baseline object store; no new
complete mirror/cold bundle is made. Exact full-tree inverse/replay is recorded.
