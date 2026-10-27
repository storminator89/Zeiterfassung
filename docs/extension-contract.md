# CH-16 / CH-17: bundled extension contract repair

## Scope and immutable input

- Issues verified directly: [CH-16 / #22](https://github.com/storminator89/Zeiterfassung/issues/22) and [CH-17 / #23](https://github.com/storminator89/Zeiterfassung/issues/23), 27 October 2026.
- Source baseline: `5bb31dfddde0fa5ba6f3ec833372a15bbb5b4789`.
- Only the existing Edge/Firefox popup bundles, their focused pure-JavaScript tests and this evidence note are changed. The bundled controllers are identical and retain the existing storage adapters, UI classes, tabs, colours and typography.
- No backend/key/configuration/authentication-method changes, new extension permissions, browser installation, workflow changes, or changes to the unmerged Home/Workmodel packets are included.

## Matched contract

The client reads authenticated `GET /api.php/attendance` as top-level
`{success:true,state:'absent'|'working'|'paused',record_id:integer|null}`.
This endpoint is a **separately reviewed dependency**, not present in the source baseline. It must expose the existing authoritative `attendance_status` helper under the existing JWT guard. Until it is integrated, these clients fail closed instead of guessing pause state from time-entry rows. Do not release this client packet without that dependency.

Start/pause/resume use `POST /api.php/attendance`; end uses the existing record-matched `POST /api.php/setendzeit`. Requests contain an action, a secure event ID, Boolean pause-end confirmation, and the expected record ID for end. No client timestamps, invented pause totals or locally calculated booking status are sent. Acknowledgements must match the event ID and top-level `record_id` and contain a recognised server state. A fresh authoritative read follows an acknowledgement, including a cached replay, or a definitive conflict.

The exact event is saved before sending and restored on popup restart. An uncertain response blocks different actions and exposes an explicit same-event retry. The event ID and confirmation payload are unchanged on retry. A confirmed/rejected event is cleared; 5xx, invalid/mismatched acknowledgements and response loss retain the event. End during a pause requires explicit confirmation. These guarantees rely on the existing server's transaction, ownership and idempotency checks; mocks are not a substitute for deployed integration testing.

## Credential and destination handling

Legacy password copies are removed before automatic login and never read into the password field or written again. Legacy tokens without an explicit API binding require fresh login. Passwords are used only for the user's explicit login action and the field is cleared immediately. Existing bearer-token authentication is retained; logout is local cleanup, not server-side JWT revocation.

Only a canonical HTTPS origin with the existing root `/api.php` path is accepted; URL credentials, query parameters, fragments, other paths and unsaved target changes are rejected. Requests omit ambient cookies and referrers and reject redirects. Server changes and logout remove credentials, account markers, old record IDs and pending events. A 401 explains manual reauthentication and removes auth state; an uncertain event may survive expiry only to be restored after the same origin and account have been verified again. Other-account login clears that event. Generation checks and aborts prevent late login/status/mutation responses from restoring a logged-out or changed session.

Location/description inputs are explicitly disabled with an explanation because the current authoritative booking contract does not save that metadata; implementing metadata remains CH-45. Existing time-entry values remain display-only, and displayed duration is labelled gross duration rather than actual net work time.

## Verification performed

Runtime: **Node.js v24.22.1**, pure VM functions with synthetic response objects, in-memory storage and a stubbed `fetch`. The stub never opens a socket. No real credentials, tokens, personal data or live server responses were used.

Commands:

```sh
node --check time-tracker-extension_edge/popup.js
node --check firefox_add_on/popup.js
node --test tests/extension_contract.mjs
git diff --check
```

Result: **34/34 focused tests passed**, plus JavaScript syntax and whitespace checks. Each bundle covers HTTPS/target validation, password/token migration, explicit synthetic login without password persistence, start, pause, resume, cancelled/confirmed end during pause, repeated clicks, popup restart, lost start/end responses, exact event replay, refresh after a stale replay, conflict, 503, malformed/mismatched acknowledgement, storage failure, missing status dependency, token expiry/account binding, logout/server change, late responses, text-safe server messages and failed account-switch lookup. One focused deferred-storage regression additionally covers both controllers' actual initialization continuation after logout and server change: stale snapshots cannot restore API/auth state or trigger automatic login. Static checks compare controller parity, markup hooks, existing permissions and wrapping/hidden-control CSS.

Independent review found that the original `49b9c0bf7736a2abad5f1fb703c8b3da11d39943` startup storage read was not generation-bound despite its 33 passing cases. The corrective candidate binds both storage initialization and its automatic-login continuation to the captured session generation. The new focused regression fails against that historical controller (stale API restored after logout) and passes with the correction; the full focused suite remains green. No pending-event, account, target, mutation or other control semantics were changed by this correction.

## Explicitly not verified

- No Edge/Firefox extension was installed or run. Browser engine/runtime/storage/permission/CORS integration and real visual layout were not tested.
- No browser/login/server requests, real-token/password transmission, local HTTP server, database fixtures or integration tests were run. A denied secure-login path was not bypassed.
- No GitHub CI run was requested or triggered, and no remote branch, PR, merge or deployment was performed by this packet author.
- The separate status endpoint and combined exact commit still require the publication owner's review. These local contract results do not establish deployed/browser acceptance, an issue's full acceptance, or productive rollout.
