# Shared login-budget adapters — default-off source candidate

This slice is on explicit local B4 `02a93889` plus the **exact two reviewed API2
`6ebfbc9b` blobs**, not current Main, its LDAP/Admin union or a runtime rollout.
Core996e, A4bcd8 and B4 stay unchanged. Independent source review and final
serial Main integration are separate gates. No API route/key is activated here.

## One selected domain, not two throttles

`FRAMEWORK_SHARED_LOGIN_BUDGET_ENABLED` defaults false. Only explicit boolean
true selects the profile, after the existing runtime provider and with the
complete separately admitted fresh Web V4 read/write configuration. Other
profiles, incomplete or conflicting/pre-resolved login bindings are refused.
The provider performs pure configuration/construction work: no file, PDO,
transaction, account, key, hasher, guard, cache or LDAP resolution.

Web and the API interface receive the **same immutable WriterLoginBudget object**.
Its fixed lazy factory captures the same immutable admission manifest and exact
named read configuration. Every attempt checks that selection/configuration
remains exact, constructs a dedicated RuntimeDatabase for that same physical
identity and obtains one NEW writer with a fresh anonymous AuditContext. It
does not trust another container writer factory, inherit an authenticated audit
actor, retain handles, retry, repair or reconnect. The existing runtime opener,
physical/cookie/ledger checks and the exact A4 commit/idle lifecycle remain the
authority; this adapter does not replace them with a configuration assertion.

The selected login route retains the complete session/CSRF stack and omits ONLY
`throttle:chrono-web-login`. The original limiter definition and every default-off
route stay unchanged. Registration/setup throttles and all UI bytes stay exact.
The registrar refuses a route-selection/configuration mismatch. Failed factory,
admission, corruption, lock, commit or idle outcomes never fall back to cache,
another database, another identity, 401 or a provisional 429.

## Ordering and transport

Web: ordinary CSRF first, then the existing string/nonblank field checks, then
one budget operation **before guard/provider/hash/LDAP resolution**. The existing
trimmed username and raw server REMOTE_ADDR (null fallback `local`) are used;
Forwarded/X-Forwarded-For and request->ip are not budget authorities. Allowed
successes and credential failures consume equally; budget429 is mapped only
after the writer has returned a confirmed committed limited result. Technical
failure uses the existing unconfirmed-login 503 path, without exposing cause.
The preexisting credential, password-marker, fixation and logout bodies remain
exact; default-off construction continues to accept the original one argument.

API: the existing JSON/string-shape validation precedes consume. Its raw username
and raw remote address are unchanged. WriterApiLoginBudget maps committed limited
to the **existing API JSON envelope** and Retry-After600, and technical/unconfirmed
failure to `{"success":false,"message":"Internal server error"}`/500. The historical
plan's plain-text API429 description is superseded by actual existing
LegacyApiResponse::text/native abort_request JSON behavior. Web429 remains text.
Existing LegacyApiWire handles the controlled HttpResponseException and CORS.
OPTIONS/method/path refusal remains before controller/key/database resolution.
LocalProvider2 remains independent of the optional Web LDAP wrapper, and the
real signing key is still required separately and read only after credentials.

## Deliberate boundaries

Native-style SQLite quotas count successful/failed well-shaped attempts; unlike
the old Web middleware they do not count incomplete forms. Web's existing trim
and string `"0"` rules remain, API remains raw, and native PHP empty() differences
are not hidden. No full Web wire/concurrency parity is claimed. Core's strict
global counter validation is O(countercount); serialized IMMEDIATE execution
is not asserted identical to native autocommit under concurrent requests.

B4 creates the canonical empty table only for separately authorized NEW output
before admission. This slice never invokes that producer, performs DDL, creates
accounts/keys, rewrites admission/cookies or transitions existing counters. A
missing/corrupt table fails technically. An existing instance/cache/window
transition or native worker cutover still requires explicit cold/provenance and
shared-domain review. No live login path may keep a separate unreviewed store;
dormant API source is not an active bypass.

Evidence for this packet is syntax, exact source/tree preservation and one coarse
invented adapter/composition model only, not a real Application/Request/kernel,
SQLite/admission/transaction/producer/credentials/key/LDAP/network test. Physical
signatures/cookies, multiworker races, HTTPS/client acceptance, native/#197 and
session29/30, final current-Main assembly, activation and publication gates remain.
