# #53 — Native CSV controls keep the own list available

Source basis: actual Main `6ec57d4007cf6d6129d3d2acddc5daf06ee6881f`, tree
`1c7f6e7a23e85d38d0f16fde3909dee0afc13dce`, after PR #359.

The existing Native full-CSV control called `exportToCSV()`, whose unchanged
shared JS uses `window.location.assign`. The Native range form also submitted
in the current context. A successful attachment may leave that page visible,
but a normal 400/409/500 export error replaces the own-list page. That leaves
its selected list/page/dates unavailable until navigation back, rather than
keeping the clarification list beside the error. The existing Laravel controls
already avoid this navigation and do not depend on the export JS function.

Use exactly the existing Laravel controls in Native: the full export becomes
an ordinary `export_csv.php` anchor with the same button classes, icon, label,
localized explanatory title, `target="_blank"` and `rel="noopener noreferrer"`.
The unchanged GET range form receives the same target/rel and the same existing
localized explanation. Copy its three current Laravel hint values to the Native
DE/EN/ZH dictionaries; do not introduce a new hint policy or translation. Native
escapes the new hint with the existing `h()`.

This is a user-initiated ordinary link/form, no popup script, automatic download,
retry, new listener or fixed named window. Browser settings decide tab/window or
attachment handling. There is no persistent draft store: unrelated list refresh,
page navigation or reload can still replace its state. This change does not
repair historical exports or imply that an exported record is finally approved.

The original endpoint, form payload/date bounds/required checks/submit control,
closed details state, appearance classes and all existing own-list query/render/
review/pagination paths remain unchanged. All existing language contents,
including the Chinese header fix from #361, remain intact. The old shared JS
function stays untouched for compatibility; these two controls no longer call it.

No exporter, CSV bytes/filename, parser, row/sum/absence calculation, SQL, account
or scope selector, identity, auth/CSRF/session, writer, route, asset, dependency,
security/runtime/host/schema or deployment change. #53 remains partial/open;
no team/payroll schema or new file format is delivered.

## Coarse evidence and limits

PHP 8.5.12 / Laravel 13.38.0, one isolated render-only journey per DE/EN/ZH:
extract/evaluate the actual Native export header+range form and compile/evaluate
the actual unchanged Laravel active export anchor+period partial using matching
Composerlock dependencies. The baseline actual controls retain their old JS and
same-context gap. Candidate Native full anchor and entire period details subtree
match unchanged Laravel by DOM element/text/attribute signature. Targets/rel,
fixed endpoint and hint match, no onclick/download interception appears, all
original Native range payload/validation/submit attributes remain exact, and
session/timezone globals are unchanged. The Chinese journey uses the actual
published missing-label repair, not invented fixture constants.

Necessary PHP/compiled-Blade syntax, complete-tree/inverse/source-preservation
and independent review supplement this bounded proof. An initial local three-
way integration exposed the adjacent Chinese EOF additions from #361; both
existing constants were preserved and only the new hint added before rerunning.
No new repository tests/workflow or manual CI dispatch.

No full App/bootstrap/DB/auth/CSRF/HTTP/real response-code or download/navigation,
actual browser/window/Back/Forward/layout/production acceptance is claimed. HTML
render/DOM structure is not a browser execution. No real accounts, credentials,
keys or operational data; no server, external service or deployment.
