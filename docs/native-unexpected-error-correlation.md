# #66 — Native technical failures use the existing safe correlator

Bounded source fix on Main `4c2038128d6057f8d51f4f78a6d5a34a62e22698`.
The already merged `chrono_native_error_correlation()` protects controlled
`abort_request()` responses. Several other existing Native catches returned
ordinary application 5xx without that header/log pair; configuration and
attendance also wrote unrestricted exception messages. `get_user.php` returned
the raw PDO message in its 500 body.

## Exactly the existing error responses, not another ID mechanism

The direct API encoder/read/outer catches, attendance JSON transport, outer
terminal boundary, CSV technical catch, user-detail PDO catch and both selected
History failure responses now call the **unchanged** existing correlator.
It is a best-effort 500–599-only server-generated `X-Request-ID` and one fixed
ID/status log line. It never consumes a client ID or exception/request value.
Success/4xx have no new ID/log; entropy, header and log failures still cannot
replace the original response. Calls defensively tolerate an early failure
before the helper became available. Already-sent headers retain the existing
skip behavior, not a fabricated fallback ID or repaired response.

The configuration catch keeps its already correlated `abort_request(503, ...)`
but removes the extra raw PDO log. Attendance removes its raw Throwable log.
The user-detail 500 is deliberately fixed to `Datenbankfehler.` instead of
exposing PDO text. Every other existing body/status/type/buffer/exit contract
is preserved; the new ID is **not appended to bodies** or JSON envelopes.
An ID is not a receipt or proof that a booking/COMMIT failed. Unknown outcomes
retain their original uncertainty and same-event reconciliation rules.

There is no new generator/logger/sink, error taxonomy, HTTP route, SQL,
transaction, auth/CSRF/HMAC/identity rule, credential, schema, admission/default
flag, dependency, UI/locale/asset or host setting. The existing Native helper,
Laravel code and all business calculations/writers remain byte-identical.

## Explicitly independent from held presentation work

This does **not** incorporate the held Laravel About-dialog Support-ID package
(layout, DE/EN/ZH and operational-document additions), nor the separate paused
Native text-body ID suffix. Those changes remain held. No presentation of an
ID in a dialog, text body, translated label or page is added here. The already
merged Laravel raw-terminal correlation is likewise unchanged.

## Bounded critical verification

PHP 8.5.12: one anonymous loopback transport journey compares baseline and
candidate through 23 representative response cases (46 requests). Actual
attendance transport and complete API/terminal outer boundaries are executed;
other response catches/encoders are exact extracted source fragments with
invented faults/data, not a claimed Native bootstrap/auth/database test.
Fifteen post-bootstrap 5xx forms carry exactly one fresh header matching exactly
one fixed log line. Caller IDs are ignored. Both previously unrestricted log
sinks and the user-detail response reproduce a public dummy canary on the old
source; the candidate never emits it. Non-ID headers/status/body agree except
the intentional PDO-body redaction. Success, 4xx, missing early helper and
already-sent-header controls preserve their prior behavior. Existing engine
warnings after forced premature output are not reclassified or suppressed.
The temporary listener is stopped and no account/key/database is used there.

The unchanged existing `tests/api_error_response.php` CLI boundary proof passes.
The existing NEW-only 21-request file-backed Laravel Kernel installation journey
also passes against the candidate source using matching-lock existing vendor:
setup/login, CSRF refusal, attendance/replay, History/CSV, logout/relogin and
integrity, with its exclusively owned disposable key/account/files removed.
Necessary syntax/diff and exact-source preservation checks are separate.
No new fine-grained repository suite or manual CI dispatch is introduced.

This is not full Native bootstrap/auth/session/CSRF/JWT/HMAC/booking, real
HTTP proxy/TLS/browser/hardware, crash/concurrency, log-sink delivery or production
acceptance. PHP engine/very early bootstrap/streaming errors are not globally
intercepted. Rotation, operational alarm thresholds/windows/owner/recipient and
delivery decisions remain separate; **#66 stays open**. No deployment or real
security/host/data change is performed.
