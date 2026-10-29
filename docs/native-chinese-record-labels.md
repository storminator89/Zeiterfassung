# #62/#79 — Chinese own-record list labels must exist

Source basis: actual Main `cbf3c06971f4d381637b4c85fbafaa0a6b67a1ea`, tree
`8923740804ce0072ca79f4b5ea5a084b5807a30d`, after PR #357.

The existing Native Settings language selector offers Chinese. Its Native
`languages/zh.php` dictionary lacked `TABLE_HEADER_TIME_RECORDS` and
`BUTTON_EXPORT_CSV`, although the unchanged Native own-record list uses both
constants directly. Evaluating its actual header with the real Chinese
dictionary on PHP 8.5.12 throws `Undefined constant TABLE_HEADER_TIME_RECORDS`;
the list cannot finish rendering. German and English already define both.
This is a reproduced isolated render defect, not a complete authenticated
Native browser or HTTP reproduction.

Add only those two missing Chinese constants, using the exact values already
published in the existing Laravel Chinese Copy dictionary: `工時紀錄` and
`匯出 CSV`. No second translation policy, fallback dictionary, conditional
runtime definition or hardcoded test label. All previous Native dictionary
contents, DE/EN dictionaries, templates and controls remain unchanged.

No language selection/negotiation, account preference/session persistence,
reader, query, row calculation, CSV/export/navigation, authorization, writer,
security, runtime, host, dependency, asset, route or schema changes. This does
not claim every Native page is fully translated or that every Chinese label
used elsewhere exists. It does not transport the separate unpublished Native
CSV navigation candidate or any held/denied packet.

## Coarse evidence and limits

PHP 8.5.12: one isolated render journey evaluates the actual dictionary and
extracts/evaluates the actual unchanged Native own-list header and CSV range
form. Frozen-Main Chinese reproduces the missing-constant error; candidate
Chinese, unchanged German and unchanged English render successfully. Both
new values equal the existing real Laravel Copy values. PHP token inspection
of the actual entire Native list verifies all 16 TABLE_/BUTTON_/CSV_ language
identifiers are defined; this is not execution of every row branch. Existing
export controls and session/timezone globals stay unchanged. Necessary PHP
syntax, exact full-tree preservation and independent source review supplement
this bounded proof. No new repository tests/workflows or manual CI dispatch.

No App/bootstrap/DB/authentication/CSRF/session-cookie/HTTP/browser/navigation,
full-table/row/layout or production acceptance; no real accounts, credentials,
keys or operational data. No server or deployment. #62 and #79 stay partial/open.
