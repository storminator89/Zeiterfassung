# Stored target-minute labels — partial #55

Base Main `7225b2176c326b54bd4748f99268073e53ea743f`, tree
`cec66fba7e4affa3143b0878f80820b3ff36b4a0`. Only the existing own-list display
helper and this note change. No repair, full diagnostic attestation or issue
closure is implied.

Modern credit rows previously cast `planned_minutes` to integer for their
existing **Sollminuten** label. A missing/NULL or unreadable stored value could
therefore look like legitimate `0 Sollminuten`, while a fractional value could
silently look like a truncated whole-minute target. An admitted fabricated v2
snapshot reproduces this; no real data incident is claimed.

The same Native/Laravel helper now labels unsupported stored target metadata
**Gespeicherte Sollminuten nicht auswertbar**, without emitting the raw value.
It displays only actual integers or canonical unsigned decimal strings in the
existing model range 0–1440. A real zero remains `0 Sollminuten`; valid values
retain their previous label. SQLite's existing affinity may already deliver an
integer for a numeric stored representation; the helper neither changes nor
repairs that conversion. Existing invalid-time warnings keep precedence.

This checks only the displayed target field. It does not certify agreement with
the measured duration, entitlement, calendar/model validity or approval. Existing
modern/legacy recognition, start-day labels, open-state handling, raw records,
net-duration calculations, day totals, saldo, export, diagnostic filters,
authorization, writers and data-admission policy remain unchanged. The fixed
German helper copy follows its existing labels, not a new translated workflow.

## Evidence and limits

One PHP8.5.12 / SQLite3.46.1 / Laravel13.38.0 query-only anonymous Memory journey
uses the unchanged actual v2 fixture/admission check, actual old/new helper,
grouping, extracted Native row renderer and existing compiled DE/EN/ZH Blade.
Sixteen fabricated rows cover NULL, unreadable/prefix text, fractional, negative,
oversize and valid zero/1/480/1440 targets, SQLite-affinity representations,
work/open rows and a large ID; additional pure values verify unsupported types,
legacy recognition and invalid-time precedence. All other details, group totals
and CSV bytes stay exact after reversing only the target label. Source/schema,
audit/change counters, query-only/transaction and native globals are preserved.
Necessary PHP/compiled-Blade syntax and diff checks pass.

No full App/bootstrap/auth/HTTP/CSRF/browser/visual/production, schema admission
expansion, migration, repair, payroll or deployment acceptance. No real data,
accounts, credentials, runtime/host/security setting, schema/dependency, new
repository suite/manual CI or GitHub Approval. Held/denied packets remain
untouched, including #352/#57; #55 stays open.
