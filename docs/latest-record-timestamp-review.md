# Existing legacy-time findings on the latest own-record card

Read-only partial #55 / #79, prepared against actual Main
`0c78588dc5d7cd1e06def7fab0babd2ffd71fc9c`, tree
`f221e82a1c36797a53f633a07bf603be6e292c54`. The existing own time-record list
flags strict legacy-time findings, but the latest own work-record card showed
runtime-readable ambiguous/gap/unsupported/strict-invalid timestamps with no
corresponding hint. The same original row could therefore have a warning in the
list and an apparently unqualified latest-card duration.

Both existing Native/Laravel fragments now add one localized warning and the
existing exact own correction link when the **unchanged** shared
`time_record_local_time_findings()` reports any finding on a runtime-noninvalid
row. No second diagnostic/parser or normalization rule is introduced. The fixed
DE/EN/Traditional Chinese message distinguishes a diagnostic finding from a
confirmed invalidity and says the unchanged display/calculation is not a
confirmation of an unambiguous legacy time. Only a boolean enters Laravel's
prepared DTO, not raw values, diagnostic categories or another record. Older
optional DTOs without the additive boolean retain their previous rendering.

Existing invalid-row alerts remain the sole warning on runtime-invalid rows;
there is no duplicate alert. Missing open ends are not diagnosed as bad
timestamps. Ordinary strict-supported local times and explicit offsets/Z retain
their old output. No counterpart lookup, overlap scan, new SQL/reader/selection,
owner parameter, writer, repair, changed duration/date/pause/sum, booking action,
role/team/auth/CSRF, schema/dependency/runtime/host/security or deployment change.
Native's bootstrap and non-atomic surrounding page behavior remain untouched.
The existing canonical calculation remains authoritative for current accounting;
this warning does not certify, correct, close or reinterpret a stored record.
Held/denied packets, including #352's separate absence-overlap changes, are not
transported. #55/#79 stay partial/open, not a complete data-quality attestation.

## Coarse evidence and limits

PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0, unchanged preexisting Composer lock:
one original-schema anonymous Memory journey runs the actual current own
snapshot reader and actual old/new latest controller with compiled actual
fragments. 156 old/new cards cover 13 row shapes × own user/Admin × DE/EN/ZH ×
UTC/Los Angeles process timezone. The missing baseline warning reproduces 72
times: ambiguous start/end, spring gap, readable unsupported shape, readable
strict-invalid offset, and an open ambiguous start. Explicit fall offsets/Z and
ordinary/open supported times stay unchanged. Runtime-unreadable/end/pause
failures keep only their previous alert. Three empty own cases are unchanged.

Foreign and own absence canaries are never selected despite higher IDs; query
subject parameters cannot select them. The correction link preserves a
`9007199254740993` row ID exactly. Existing DTO fields and all other rendered
markup/text match after removal of the added warning and incidental line-leading
indentation; existing escaping, source/schema/audit/change counts, query_only,
transaction cleanup and native globals/timezone are preserved.

Separately, the actual Native query/calculation/full-fragment extraction runs
13 rows plus empty/repeated cases in each language on minimal anonymous Memory.
The unchanged query selects only its synthetic bound owner. Six new warnings per
language and all old values/other output are verified. All six dictionaries emit
zero bytes and share the same copy. Necessary nine PHP source and compiled actual
Blade lints plus diffcheck pass. No repository test suite, fixture server or
manual CI dispatch is added; natural exact-head/Main CI is followed separately.

RequestGuard is an identity double; Native bootstrap/security/config/functions
side effects and full App/login/auth/CSRF/HTTP/browser/layout/production/concurrency
are not exercised or accepted. No actual accounts/data/credentials/keys/file DB,
writer or deployment. Browser acceptance remains with the user. Local evidence:
`/tmp/chrono-latest-timestamp-review-main0c78588d-support/` contains coarse.json,
native-de/en/zh.json, proof.json, candidate.patch, complete-trees.bundle and
independent-review.json; merge/CI evidence is recorded separately.
