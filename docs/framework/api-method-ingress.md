# Default-off raw API method ingress

Source basis: Main `b482b1cd7f5aa22580747452cd710e2a7c103721`, tree
`f49109399934aea6cf7908698a0ae246c5d48cd1`, after PR #216. This closes only
one remaining #77 wire gap; it is not full API/client or operational acceptance.

## Same selected composition, no new switch or dispatcher

Only the existing admitted API provider, after successfully checking its profile
and installing the existing registrar, appends `LegacyApiMethodIngress` to the
stock HTTP kernel. Off: no callback or middleware installation, route, service
profile, key/metadata/database access or changed public contract. The helper
supports both an already-resolved HTTP kernel and console-before-HTTP resolution.
No new API flag, route table, credential/token implementation, admission source,
login budget, writer, client controller or native code is introduced.

The ingress is appended, never prepended: stock global middleware, including
maintenance, CORS, post-size and input gates, stay ahead of it. It does not call
the router directly to skip that stack, disable CSRF, alter proxy/host handling,
or rewrite the global exception handler. Existing routes retain their middleware
and the order `LegacyApiWire → admitted profile → bearer/controller`.

The new adapter only recognizes the raw, case-sensitive `/api.php` path or its
slash-separated descendants, with the query excluded. `/api.php-other`, ordinary
web, health and terminal paths are untouched. No percent-decoded or alternative
spelling is adopted as an API namespace by this new adapter. Such aliases retain
the existing router/wire behavior and are not certified by this packet.

## Raw method before Laravel route selection

`LegacyApiWire::restoreRawMethod()` contains the exact former method-normalization
lines: remove the override header and `_method` form/query values and set the
method from raw `REQUEST_METHOD`. The route wire still calls it. The selected
ingress reuses that method before routing; no second implementation is added.
This also keeps a raw POST with HEAD/TRACE/unknown override on its native POST
path rather than allowing an override to cause a pre-wire route refusal.

GET/POST/DELETE/OPTIONS continue through the one existing registrar and its
normal guards. OPTIONS remains the existing empty 200/pre-auth CORS shortcut.
Every other raw method, including HEAD/PUT/PATCH/TRACE/CONNECT and extension
verbs, delegates to the same existing `LegacyApiWire` 405 and CORS/header tail
without resolving a profile, guard, credential, key or database. Responses pass
through normal router response preparation, including bodyless HEAD. No new
method is accepted, no unknown endpoint is granted, and method overrides do not
change the raw API action. Existing controlled errors/Retry-After and scoped
JSON500 remain unchanged. The global stack can still refuse earlier; this is
not a new guarantee for failures before routing or request capture.

## Bounded evidence and remaining gates

One coarse proof uses actual locked Laravel classes and synthetic **internal**
Request objects, not an HTTP client/server or real application providers. It
covers selected/default-off installation and both kernel-resolution orders,
raw method/override routing, OPTIONS/CORS, bodyless HEAD, stock maintenance and
ordinary web-CSRF behavior, and no forbidden service resolutions. No real
signing key, metadata, DB/PDO, account, password hash, JWT, LDAP or network is
needed. Syntax and whole-source preservation are separate evidence; no new
microtest suite or GitHub CI run is introduced.

The existing Web/API shared-budget and original-key composition still requires
separate physical admission, actual SQLite/native-worker transition and explicit
operator activation. Token `nbf`/revocation-race differences, non-scalar date
policy, full login/start/pause/end/retry client acceptance, Firefox/Edge runtime
and store distribution, trusted HTTPS/proxy, session #29/#30, #197 and cutover/
rollback remain open. The popup bundles and Swagger's native contract are not
changed or claimed as verified here. Do not close #77 or enable a runtime flag.
