# Stored correction-request lifecycle times in the existing queue

Read-only partial improvement for #50, prepared against actual Main
`2a07311bf5a79b1e88f7d146ba30978eab24ae9c`, tree
`02dca66b0bafd3cb900e809107745b36002fae85`. The existing Native and Laravel
request cards showed status and reasons but omitted the already-read `created_at`
and `reviewed_at` fields. A small line now makes these stored facts visible:
submission, plus a recorded decision for approved/rejected requests or a recorded
withdrawal for withdrawn requests. Pending/unknown status never acquires a
resolution from stray metadata. The line is localized in DE/EN/Traditional Chinese.

## Same stored row, no new authority

The Native queue and Laravel snapshot already select these fields. There is no
new query, identity, subject selector, reviewer lookup, audit expansion or write.
The pure presentation helper selects only the two timestamps and the existing
status. Laravel passes only the resulting strings/kind to the existing card DTO,
not raw snapshots. Older optional DTOs without the additive lifecycle property
keep their old rendering. Existing request metadata/proposals, status/reason,
counts, pagination/order/filter/preview links, drafts and all form fields/actions
remain unchanged. Existing writer, scope and four-eyes checks remain authoritative.

Times use the existing exact integer recognizer and canonical Berlin conversion,
with the same ISO seconds/explicit numeric offset as existing correction clocks.
The two repeated autumn-hour instants remain distinct. Missing/malformed/overflow
values, out-of-four-digit local years and historical sub-minute Berlin offsets
that the existing `±HH:MM` format cannot represent are fixed **Time unavailable**,
never coerced to 1970, now, zero or an invented timestamp. A genuine stored epoch
zero is valid. No raw bad value or exception is displayed. All output is escaped.

These are independent stored fields, not proof of truthful event history,
chronological consistency or current state; no timestamp is reconstructed from
status, review reason, audit rows or another record. No age clock, SLA, escalation,
substitute reviewer, self-approval, approval guarantee or repair is introduced.
No live refresh or automatic retry. Native's existing bootstrap/transaction behavior
is unchanged; calling the pure helper itself has no session, clock or DB effect.

## Coarse evidence and limits

PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0, existing original-schema anonymous
Memory fixture and unchanged Composer lock: actual named snapshot reader, actual
old/new controller and compiled correction content with an inert layout. One
bounded journey covers own/direct-supervisor/Admin scopes, all statuses, existing
page clamping and exact/invalid request lookup in DE/EN/ZH under three process
timezones. 243 old/new screens and 3240 already-authorized rendered rows; all old
DTO fields and all other rendered markup/text match after removal of the new line
and incidental line-leading indentation. No command is called. Source rows,
schema, audit, change counts, query_only and native session/timezone are preserved.

The same journey covers malformed integer shapes, actual zero, both autumn
offsets, pending/unknown states and lossy historical offsets. The actual Native
line is extracted and rendered separately for six cases in each language; all six
language sources emit zero bytes. Necessary nine PHP source lints, compiled Blade
syntax and diffcheck pass. Initial external-harness issues (old-controller include
context, response wrapper, incidental indentation and a too-broad script canary)
were corrected; no candidate behavior was changed to satisfy those checks.

RequestGuard is an identity double; the layout is inert and no Native bootstrap
or command runs. No full App/login/auth/CSRF/HTTP/browser/layout/concurrency,
production or deployment acceptance; no real data/accounts/credentials/keys/file
DB, server/fixture activation, new repository testsuite or manual CI dispatch.
#50 remains partial/open, including separate representation/escalation decisions.
Held/denied PR239's Native time-entry helper and all other held packets are not
transported. Browser acceptance stays with the user.

Frozen evidence: `/tmp/chrono-correction-lifecycle-main2a07311b-support/` contains
coarse.json, native-de/en/zh.json, compiled template, proof.json, candidate.patch,
complete-trees.bundle and independent-review.json. Actual natural exact-head/Main
CI and merge evidence are recorded separately once available.
