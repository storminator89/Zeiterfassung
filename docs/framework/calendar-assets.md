# FW-08 Calendar — pinned FullCalendar Standard, Laravel-only

The user approved replacing only the Calendar component on 2026-10-27. The existing nine cards, three server-navigation links/URLs, business colors, 700px container, read-only behavior and text-only details modal remain. The internal grid is FullCalendar's stock Classic renderer rather than TOAST; small grid/stacking/focus differences are expected, not a new application UI.

## Narrow source boundary

Base: `d698b3614d94e04b5e81092da42c897d147f052f`. The dormant Dashboard Blade/DTO were unchanged from frozen Calendar source `50a92499445bbc476f6aab9ec969c0449540b57e` before this patch.

Only new official vendor files, `public/assets/js/chrono-calendar.js`, `ExistingUi/CalendarEvents.php`, this document, a new DashboardPage payload accessor and Calendar-only Blade hunks are involved. No native/root files, route registrar, layout, guards, session/auth, readers, controller, database or other views change. There is no activation, host/Webroot/CSP change, production deployment or claim of protected browser acceptance.

**Native fallback remains unchanged**, including its existing TOAST CDN dependency. This packet replaces no native file or publicly active route. Laravel assets are relative to its intended `framework/public/` webroot. They are not made reachable through the deliberately denied `framework/` path under the old webroot. Shared/native asset reuse requires separate explicit integration; do not weaken that protection or add a path bypass.

## Official assets, no new build pipeline

The project has no Vite/Mix/frontend pipeline. Copying official Standard browser assets avoids adding one. Vendored package is **fullcalendar 7.1.3, Standard, MIT**, official source release `v7.1.3`, commit `6b890e803c66d1e6db2fb21c449936155b7e81fc`.

Exact tarball: `https://registry.npmjs.org/fullcalendar/-/fullcalendar-7.1.3.tgz`

Published integrity: `sha512-Mn8VNqLX80C93UFh0s9aKqhHUSh0BwK8pczyQ+nNDKutJeM0/SVLkJoizTfX9sdmwGiS6+hkAHAg9YC10Us0gg==`.

`public/vendor/fullcalendar/7.1.3/provenance.json` records each shipped file's size/SHA256, source lock URL/hash and bundled-component/license provenance. The tarball actually contains `global.js`, **not** the `global.min.js` names shown in some docs; official bytes are retained without custom minification. Shipped executable assets are `all/global.js`, `themes/classic/global.js` and `locales/de/global.js`; CSS is `skeleton.css`, Classic `theme.css` and `palette.css`. English is built into the engine, German uses its matching official locale. All script/stylesheet URLs are local and pinned; no runtime CDN/import map, paid Premium package, scheduler/resource feature, account or service is used.

Upstream release lock records Preact **10.29.8**, temporal-polyfill **1.0.1** and temporal-utils **1.0.1** in the standalone bundle. No separate runtime Preact/Temporal script is loaded and no global polyfill is installed. FullCalendar/headless/core and these runtime components are MIT; original Preact/Temporal notices are included. Transitive temporal-spec **1.0.0** is a TypeScript declaration package under Apache-2.0; its notice and full Apache license are retained conservatively. Exact component registry archives and published SHA512 were checked when sourcing notices. Upstream whitespace is preserved byte-for-byte; a full `git diff --check` therefore reports vendor-only trailing-whitespace/indentation warnings. The scoped first-party check passes; vendor integrity, not whitespace rewriting, is the applicable proof. No blanket security/license guarantee or independent upstream-build reproduction is implied by acquisition integrity.

### Reproduce acquisition without running package code

Use only the exact URL/integrity above, not `latest` or an install/update command:

1. Download the tarball to a private working directory with HTTPS-only `curl --fail`.
2. Verify SHA512 **before** opening it: hash bytes, Base64-encode the digest and compare the entire published `sha512-...` value. Fail on mismatch.
3. Inspect all archive names/types; reject absolute/traversal names and links. Read only the exact seven `package/` members corresponding to the six JS/CSS files and `LICENSE.md`; do not extract or execute arbitrary package paths. Preserve their bytes.
4. Source the component notices from the exact tarball URLs/integrities in the provenance manifest; preserve the notices. Verify the copied public files against every manifest SHA256/size.
5. Run syntax checks and the coarse presentation/build-integrity check; no npm lifecycle/build is necessary. Any later version update requires a new explicit asset review and regenerated hashes/notices.

## Presentation, dates and open ends

`CalendarEvents` maps already-authorized `calendar_range_events()` results. It does not query, filter, reinterpret reporting/finality, change source arrays or write anything. It delegates date interpretation to the existing `booking_time` and `booking_local_date` helpers. Timed start/end values are actual epoch milliseconds, retaining offset/fold identity rather than asking a new browser parser to interpret legacy strings.

All-day values remain Berlin dates. Native/TOAST ends are inclusive; FullCalendar receives the following **civil date** via `modify('+1 day')`, not `+86400`. Original inclusive dates remain in modal metadata. Timed raw ends, including null/empty and zero-length native-valid records, are kept separately from FullCalendar's normalized visual range. Missing timed ends retain the old **30-minute display fallback**, not FullCalendar's one-hour default; `forceEventDuration:false` and the null payload preserve that no actual end was booked. The old display fallback is not persisted or presented as an authoritative recorded end in backend state.

The engine is explicitly configured with `timeZone:'Europe/Berlin'`. The inspected official v7 bundle uses its internal Temporal native-or-shim branch and real IANA-zone conversion (`timestampToMarker`, `offsetForMarker`, `toDate`), not v6-style UTC coercion. Its shim uses the browser's Intl zone data; there is no silent switch to browser-local time or UTC. This is source evidence, not a browser DST acceptance result.

The reusable renderer receives only the bounded page payload; event data is an array, never a feed/URL. No creation, selection, navigation links, dragging, resizing, write callback, new view button or internal toolbar is enabled. Read-only event clicking opens the existing modal. Titles and location are assigned with `textContent`; custom event content returns DOM nodes, never attacker-controlled HTML. Colors use fixed native color values and the official per-event color/contrast API; the native border color uses a style property, not HTML. No TOAST, old DOMPurify, purifier override, package telemetry or new external executable load is introduced.

## Evidence and remaining gates

One coarse synthetic mapper/Blade-compilation/build-integrity proof covers canonical instant handling, inclusive/exclusive DST days, the missing-end display contract, safe JSON embedding, preservation of card/navigation/modal markup, official file hashes and scoped changes. PHP and JavaScript syntax are checked. No local functional app/browser tests, controller dispatch, auth/session injection, real DB/server, CI run, remote write or deployment belongs to this packet.

Still required separately: reviewed protected route/chrome integration, normal authenticated published-site acceptance, appearance in light/dark and narrow layouts, empty weeks, repeated modal open/close, server navigation/Back and DST/non-Berlin-browser behavior. Source proof does not satisfy these gates.

Primary sources: [Standard license](https://fullcalendar.io/license), [v7.1.3 release](https://github.com/fullcalendar/fullcalendar/releases/tag/v7.1.3), [official browser recipe](https://fullcalendar.io/docs/vanilla-js), [week/day grid](https://fullcalendar.io/docs/timegrid-view), [IANA zones](https://fullcalendar.io/docs/timeZone), [internal Temporal](https://fullcalendar.io/docs/temporal-polyfill), [exclusive event end](https://fullcalendar.io/docs/event-parsing), [safe content API](https://fullcalendar.io/docs/content-injection), [event hooks](https://fullcalendar.io/docs/event-render-hooks).

## Default-off route/chrome source integration

Basis of this additive step: combined Calendar/Supervisor Main
`c168e64bc4d2038ea42041cf4133bb918da0263f`, tree
`7c55335cefbe30d9ee39628483ec0bcb8b2b0d08`. Exactly seven existing files change:
`LocalWebRoutes`, `AttendancePage`, `Interactions`, the standard layout, the
explicit `LocalWebFixture`, its fixed router and this document. The reviewed
FullCalendar assets/renderer, Dashboard controller/DTO/Blade, own-only reader,
reporting formulas and all native files remain byte-identical.

The appended `LocalWebRoutes::register(..., bool $includeDashboard = false)`
preserves the existing History/Supervisor argument order and defaults. Only an
explicit opt-in adds `GET|HEAD dashboard.php` at the existing controller, inside
the unchanged web/session/CSRF stack and
`RefreshLocalAccount` → `auth:web` → `auth.session` group. Persistent session
blocking remains required. The full-page auth failure redirects to `login.php`,
including requests with query parameters; no new status/form transport,
credential/session bridge, role policy, writer or POST endpoint is added.
The reader still reloads its own guard identity and scopes all data to it.

Before **any** route registration, the Dashboard opt-in checks fourteen fixed
reviewed files: all six FullCalendar executable/style assets, the preserved
licenses/provenance and the renderer. Literal source path/SHA256 pairs, not a
mutable manifest, request path, environment value or configuration option,
select the bytes. Missing, unreadable, directly symlinked or replaced files
fail closed. The existing Supervisor asset gate and session gate are unchanged.
Without the Dashboard opt-in, its asset check is not run and no Dashboard route
is registered. The check establishes the reviewed local bytes, **not** actual
server mapping, absence of later filesystem changes or browser execution.

`AttendancePage` now admits the already-existing `dashboard.php` active state;
the existing sidebar link gains that one active condition without new markup,
controls, asset loaders or visibility policy. `Interactions::dashboard` is an
appended default-false presentation note, never authorization. The only caller
setting it and `includeDashboard:true` is the already-explicit synthetic fixture
composition; this source change does not start or authenticate that fixture.

Its fixed router adds `/dashboard.php` and the seven exact same-origin loader
URLs already present in the reviewed Blade. The six pinned JS/CSS assets and
`assets/js/chrono-calendar.js` map only to their existing
`framework/public/` files with explicit JavaScript/CSS MIME types and existing
GET/HEAD behavior. No wildcard, arbitrary filesystem path, external fallback,
repository Webroot or new asset contents are admitted. Static public asset
mapping is not a private-data or authentication route.

`routes/web.php` and bootstrap stay unchanged: the Foundation still enables
only `/up` by default. The native UI/CDN fallback, host/Webroot/security
settings, application activation and public deployment are not changed. No
new protected browser acceptance is implied by source-level opt-in wiring.

Only coarse preservation/hash/source/Blade-compilation checks and PHP/JS syntax
belong to this step; no new microtests, controller dispatch, app bootstrap,
DB/session/auth execution, HTTP/server, CI or browser run. The prior standalone
`file:` preview was denied by browser security policy; it was not retried or
replaced by a server, data URL, injection or other workaround. Actual renderer
initialization, asset serving, normal protected login/navigation/Back, modal,
light/dark/narrow layouts and DST/non-Berlin-browser acceptance remain open.
