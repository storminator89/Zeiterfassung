# Local MCP over the existing own-account API

This implements #422 on the Laravel foundation, using exactly `laravel/mcp`
`1.1.2` (locked source `ab9238ce6c89095679651dd3d19d67641d73a436`).
Composer uses the closed range `1.1.2 - 1.1.2` to retain exactly that version
while keeping strict Composer validation free of its optional exact-pin warning.
There is one server, explicitly started through STDIO. Installation and ordinary
web boot register no MCP HTTP route, enable no API, and provision no credentials.
Existing Chrono web pages and README media remain unchanged.

## Private local operator configuration

Use PHP 8.5 and the existing locked Composer dependencies in `framework/`.
The target API must already be admitted, enabled and reachable over trusted HTTPS.
The operator supplies an existing, independently obtained own-account API token
in an absolute private regular file with mode 0600 or 0400, outside the repository
and web root. Do not paste a token into chat, command arguments, config caches or
the MCP client's JSON. There is no login/password tool or automatic token issuance.

In the private local process environment, configure these non-secret selectors:

| Variable | Value |
| --- | --- |
| `APP_DEBUG` | `false` (registration refuses debug mode) |
| `CHRONO_MCP_ENABLED` | `true` (default `false`) |
| `CHRONO_MCP_API_ORIGIN` | Exact lowercase HTTPS origin, optional port, no path/query/userinfo |
| `CHRONO_MCP_DEPLOYMENT` | Operator-assigned unique deployment identifier, 16–80 ASCII letters/digits/underscore/hyphen |
| `CHRONO_MCP_ACCOUNT_ID` | Canonical positive decimal account ID as a string |
| `CHRONO_MCP_TOKEN_FILE` | Absolute path to the existing private token file |
| `CHRONO_MCP_APPS_ENABLED` | Optional `true`; default `false`, see [Apps](mcp-apps.md) |

Start from `framework/` with `php artisan mcp:start chrono`. Configure the local
host to start that command in this directory and inherit the private environment.
Only JSON-RPC goes to stdout; framework logging goes to stderr. The MCP host and
the local OS account are trusted: they may read all data returned to this process.
Do not expose this process through an HTTP or websocket relay.

The profile captures origin, deployment identifier, account and token once per
process. Each operation first calls `/api.php/users` and requires the exact single
configured account; the actual endpoint then independently applies its existing
API guard. Token expiration, revocation, API disabling and current rights remain
upstream decisions. No model-supplied user, role, URL, HTTP method, header or
credential is accepted. No cookie/session, direct database or domain-service
bridge is used. Redirects are refused, TLS verification remains enabled, and each
HTTP exchange has a 3-second connect/10-second total limit and 256-KiB response cap.
An operation may make two exchanges. API error bodies, HTML and exceptions are
never reflected. HTTP status and bounded numeric `Retry-After` remain visible.

## Actual supported coverage

| MCP tool | Existing API contract | Authority |
| --- | --- | --- |
| `chrono_identity` | `GET /api.php/users` | Own identity only |
| `chrono_attendance` | `GET /api.php/attendance`, workflow by default | Own current state; hints are state-only |
| `chrono_time_entries` | `GET /api.php/timeentries` | Own bounded raw rows, dates and work/absence filter |
| `chrono_corrections` | `GET /api.php/corrections` | Own status only; request/record/before filters |
| `chrono_work_model` | `GET /api.php/work-model` | Own stored effective version, optional date |
| `chrono_work_models` | `GET /api.php/work-models` | Own bounded stored versions |
| `chrono_start`, `chrono_pause_start`, `chrono_pause_end`, `chrono_end` | `POST /api.php/attendance` | Four fixed API actions using server time |

Pages use integer `limit` 1–100 (default 50), `offset` 0–2147483647.
`pagination.next_offset` is null at exhaustion, otherwise the next bounded offset;
`snapshot:false` explicitly means intervening writes may shift subsequent pages.
All record/account/model/request IDs are projected as exact decimal strings,
including values above 2^53. Numeric duplicate `record_id` is deliberately omitted
in favor of `record_id_string`. Unknown upstream fields are dropped. Time rows
include only id/user_id, timestamps, pause, description, location, and optional
absence_kind/planned_minutes. Correction reasons, proposals, reviewer identity and
original JSON are omitted. Structured results have the same JSON text fallback.
The common output envelope has an SDK schema; endpoint DTOs have explicit runtime
projections. Invalid or oversized selected data refuses the whole response.
Raw entries and models are not final credits, holiday entitlement or approvals.

## Exact confirmation and uncertain outcomes

Before a new write, obtain fresh `chrono_attendance` and explicit user confirmation
for the particular action and event ID. Supply a stable `event_id` (16–80 ASCII
letters/digits/underscore/hyphen), identical `confirmed_event_id`, and the exact
returned `profile_binding`. For pause/end also supply the fresh positive decimal
`expected_record` and identical `confirmed_record_id`. Ending an active pause
requires `confirm_end_pause:true`; the upstream API retains its own confirmation
and atomic record-binding checks. Duplicate fields are a concrete binding contract,
not proof that a human approved; the host must actually collect the confirmation.

The binding hashes exact origin, configured deployment, account and credential
fingerprint. A changed selector or token invalidates previous confirmations,
including across process restarts. Deployment identifiers are trusted operator
metadata: the existing API has no deployment-identity endpoint. Operators must
change the selector when replacing an instance at the same origin; an undetected
server replacement cannot be distinguished by this wrapper alone.

There is **no automatic retry**, including after 429/503. Retain the original event
ID, complete arguments and profile binding after any unconfirmed response. Only
an exact positive receipt with matching event and affected record resolves the
write as confirmed. A later failed identity preflight, refusal, permission change
or negative same-ID replay does not prove that the original attempt failed.
Never silently change the event ID. A manually requested same-ID replay uses the
same upstream payload; replay precedes current-state checks in the existing API,
so the wrapper intentionally does not reject it based on a changed attendance
state. The server stores no second receipt ledger and does not erase uncertainty.
The optional UI retains pending arguments across reloads where session storage is
available and blocks replacement until a bound receipt resolves them.

## Follow-up boundaries

| Package | State |
| --- | --- |
| #423 remote OAuth/delegation | Blocked/default closed: no remote route or OAuth endpoints |
| #424 administrative/workflow APIs | Planned: no general team/admin, correction writes, leave booking, LDAP, terminal, key, SQL or shell tools |
| #425 MCP Apps | Optional own-account interface using this same tool catalog; no extra authority |

Remote use requires a real OAuth resource-server contract for exact issuer,
resource/audience, subject, client, expiry and scopes, plus a separate per-user,
per-client consented upstream API linkage with revocation. RFC 7662 introspection
is an evaluated design option, not implemented functionality. `active:true` alone
does not establish audience, linkage or permission. Current one-hour Chrono JWTs
provide neither refresh nor exchange. Passport alone does not supply that missing
resource/linkage contract. Inbound MCP credentials must never be passed through
as upstream API tokens or mapped to a shared administrator.

## Verification and remaining admission

The coarse journey in `tests/mcp_journey.py` uses disposable synthetic fixtures,
real STDIO and HTTPS API boundaries. The dedicated CI job installs the locked
dependencies, checks platform/syntax, and runs the journey. It does not activate a
real API, create production credentials or deploy anything. Actual run results
belong in the PR; do not infer a passing run from the presence of this script.
An interactive external MCP host and its sandbox have separate acceptance needs;
synthetic protocol/bridge coverage does not establish real-host compatibility.
Laravel's stock STDIO transport has no application-level line-length limit;
the local host/process boundary remains trusted. Existing dependency advisories
are reported separately by the lock audit and must not be silently suppressed.

Local evidence on 2026-10-30: PHP 8.5.12 syntax, strict Composer lock validation
and locked platform requirements passed using the already available PHP image.
`python3 tests/mcp_journey.py --php /path/to/php --http-client-only` passed against
the actual native API over verified loopback TLS. That mode loads only the
production `LocalProfile`, `OwnApiClient` and `OwnProjection`; it covers six own
reads, exact large IDs, four attendance actions, stale-record refusal, paused-end
consent, same-ID replay, one committed POST with its response lost, later API-off
preflight refusal, revocation and changed deployment/account binding. It does
**not** execute Laravel MCP, `OwnTool`, Artisan boot or Blade.

The full mode currently exits explicitly because `framework/vendor/autoload.php`
is absent. The environment denied dependency archives at `api.github.com` and
the advisory API at `packagist.org`; those downloads were not retried through
another route. Actual Blade compilation/rendering and the full STDIO journey
therefore remain unverified. Existing SDK source and a synthetic Chromium host
permit API inspection and bridge/UI tests, but cannot substitute for Blade.
No PR/CI or release result is implied by these local checks.

Sources checked 2026-10-30: [Laravel MCP](https://laravel.com/docs/13.x/mcp),
[pinned protocol enum](https://github.com/laravel/mcp/blob/v1.1.2/src/Enums/ProtocolVersion.php),
[MCP Apps](https://modelcontextprotocol.io/extensions/apps/overview),
[MCP authorization](https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization),
[RFC 7662](https://www.rfc-editor.org/rfc/rfc7662.html).
