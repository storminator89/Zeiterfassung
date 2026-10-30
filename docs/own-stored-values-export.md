# Own current stored-value JSON download — partial #55

This is a finished narrow user function in the existing own time-list flow, not
another restore prototype. Prepared on actual Main
`64776b2fdc67d7634ea3834a0f28f8d1f6dc489b`, tree
`c0d69d3325182608ec4ad039887a8b460ec1634f` (#411). The normal own CSV correctly
refuses unresolved conflicts/invalid rows. A separate deliberate **Eigene
gespeicherte Werte herunterladen (JSON, unberechnet)** link provides current own
stored values for manual clarification without claiming calculated valid time.
The existing CSV endpoint, serializer and every quality/period rule are unchanged.

## User contract

`GET export_stored_values.php`, no query string, produces one complete attachment
named `Eigene_gespeicherte_Werte_YYYY-MM-DD.json`. HEAD/other methods do not generate
data. No subject, category, list/review/page, date, callback, language or other
query selector is accepted. The link is independent of current list pagination or
review mode; nothing downloads automatically. The existing card/table styles and
normal CSV action remain, with DE/EN/ZH privacy/no-calculation copy and no new JS.
The raw document can contain the user's own private notes and absence values;
users must choose an appropriate private destination and sharing recipient.

Only current own `zeiterfassung` fields already used in the own list/CSV are read:
`id,startzeit,endzeit,pause,standort,beschreibung,absence_kind,planned_minutes`.
Even Admins and LDAP supervisors receive only their own rows. No names, account
columns, passwords/hashes/tokens, other users, history originals, requests or
unrelated tables are exported. **Current stored values are not the original
pre-correction events.** This is not audit history, a database/full backup, a
calculated time report, period completion, data-quality attestation, legal or
payroll/software interchange contract. The file must not be presented as a
successful alternative version of a refused financial/time CSV.

The versioned schema is `chrono-own-current-stored-values-v1`. `metadata` records
one server Berlin load timestamp, fixed columns/ordering/limits, own scope and
explicit false calculation/backup/history claims. Rows use descending numeric
record ID, not interpreted chronological order. `record_count` and `complete=true`
are emitted only after the entire bounded own selection finishes successfully.
No dates are parsed, ambiguous/gap times assigned an offset, rows repaired, values
coerced into durations or totals calculated. Empty current own scope is explicitly
an empty document, not a quality or completeness finding about other tables.

Each value has `storage`, `encoding`, `value`:

- SQL NULL: `null/null/null`, distinct from empty text or zero.
- INTEGER: `integer/decimal-string`, including exact signed 64-bit/large IDs;
  not a rounded JavaScript Number.
- REAL: `real/ieee754-binary64-be-base64`, the fetched binary64 value in big-endian
  bytes, including nonfinite values without invalid JSON numeric literals.
- TEXT: `text/utf8` if its bytes are valid UTF8, otherwise `text/base64`.
- BLOB: `blob/base64`, preserving bytes and the distinct SQLite storage type.

These are fetched SQL values, **not physical SQLite page representations**. JSON
escapes markup/control characters and is an attachment with JSON MIME, nosniff and
no-store. It is not CSV, formula input, HTML or executable code. No exporter fields
are used as a filename, URL, header, SQL identifier or actor identity.

## Bounds and authority

One fixed own predicate, descending ID and `LIMIT 5001` operate inside the existing
fresh authenticated snapshot. Each selected field is bounded in SQL before PHP
materialization; an oversized representation is a refusal, never an exported null
replacement. Limits are 5000 records, 65536 bytes per field and 8388608 bytes of
complete JSON. Memory serialization is bounded; no temporary file/stream escapes.
An exceeded limit returns nonattachment413. Missing optional capability or an
unexpected read/serialization failure returns fixed nonattachment503, never a
partial file. Operators can separately authorize an appropriate offline evidence
flow for larger datasets; this feature never falls back to a whole database export.
These bounds limit retained/output data, **not total SQLite scan/CPU work**.

Laravel exposes the separate endpoint only alongside the existing opt-in CSV
composition, under the unchanged encrypted session/CSRF/RefreshLocalAccount/
auth:web/auth.session stack and existing fresh Runtime admission. A new optional
`OwnStoredValuesExportReads` leaves `ChronoReads` and older readers unchanged; older
presentation defaults do not advertise it, and an unsupported direct read is
unavailable rather than empty. The actual new named read reloads current authority
and source rows in the same snapshot. Native uses the existing current actor/
password-version/known-role pattern in an owned transaction. Caller transactions
are refused without being committed or rolled back. No network stream retains a
DB handle; complete bytes exist after the read commits and before headers.

No writers, source/schema/migration/admission changes, new runtime flags, normal
CSV changes, external services or production activation. Standard HTTP/session/
Native bootstrap behavior is unchanged. This is distinct from held #410/queue/
category filter packets and does not transport held Native catch or Restore code.

## Coarse evidence and limits

PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0, unchanged dependency lock:

1. One seven-phase component journey uses the original anonymous V2 fixture,
   actual named Reader/controller/Native wrapper and compiled existing list.
   Invalid end/DST text, invalid UTF8 stored TEXT, exact large IDs, same own scopes
   for member/Admin/LDAP supervisor, fresh role loss, Native version, borrowed
   transactions, method/query selection before data, failures/413/optional fallback,
   all locale copy and old CSV link are checked. Source/schema/audit/change counters,
   transaction state and real session/timezone remain unchanged during reads.
2. One separate unadmitted anonymous value-only Memory fixture in that journey
   checks BLOB/binary64/nonfinite/NULL representations through the actual SELECT,
   exact 64-KiB and 5000-row boundaries and complete refusal above field/row/8-MiB
   document bounds. It is not a canonical Runtime/admission proof. No original audit
   trigger is weakened to create a BLOB fixture. An initial harness count omitted
   existing synthetic record43; only the expectation was fixed.
3. One real NEW-V5 file-backed App journey performs 27 Kernel requests using actual
   producers/providers/routes/middleware/encrypted file cookies/CSRF/standard auth
   and admitted writer/readers. Normal setup/login/attendance/history/CSV persist;
   the new own download, no-selector400, HEAD405, own-list link and anonymous401
   pass. Integrity and key/admission remain unchanged; every exclusively owned
   test account/key/file is removed. No listener is started. Source-first App loading
   is required with the reused exact-lock vendor's old App map; the ordinary App
   executes, but a fresh standard-Composer installation is not thereby accepted.

PHP/compiled Blade syntax, whole-tree preservation and inverse/replay are separate
checks; independent read-only review is required before publication. No repository
suite, workflow or manual CI dispatch is added. Limits: fake RequestGuard/inert
component context for corrupt values; Kernel uses newly generated synthetic data,
not real records. No Native HTTP, browser/layout/visual, network/TLS/Docker/OS,
concurrency/load/production, full migration or payroll acceptance. #55 stays open
for its broader real-data/migration requirements. Local evidence:
`/tmp/chrono-own-stored-values-main64776b2f-support/`.
