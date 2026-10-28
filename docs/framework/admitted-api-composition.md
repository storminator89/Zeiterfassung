# Explicit admitted API composition — default-off source candidate

Source basis: actual Main `4d78a7003ca8e265fe7657c3e8af9ab762cd6e2c`, tree
`ba3a5bdbe563940d10b340c3f8b8680901e62ce7`. The existing reviewed API2 and shared
Core/A4/B4/Web/API budget are already present. The exact three KeyAdmission3
`8752acbf` blobs are reused without another reader, hasher, JWT implementation,
budget, route definition, account path or metadata writer.

## Default-off and pure composition

`FRAMEWORK_ADMITTED_LEGACY_API_ENABLED` defaults false. Off: no key metadata
validation, binding, routes, file/DB/key access or auth resolution. On requires
the complete separately admitted uncached fresh V4 read/write Web profile,
the **same selected shared budget facade and API adapter**, non-debug production
settings, existing bearer driver and unchanged stock hash semantics. Conflicting
key/profile bindings and pre-resolved API login/identity/key services refuse;
no cache/per-request counter fallback is installed.

The operator must separately provide an exact typed ten-field
`chrono_api.existing_key_admission` array. Default null is not guessed or repaired.
It is validated by the existing ExistingApiKeyAdmission against the **same
RuntimeAdmission manifest/read configuration** used by the admitted database and
shared budget. Its canonical database-profile digest is NOT the raw manifest
file hash. No actual key, metadata file, fingerprint, private directory, account
or database is inspected or provisioned by this source packet.

The existing ExistingApiFileSigningKey constructor is pure. Only its existing
`secret()` may later perform its original read-only physical UID/ancestry/file/
identity/mode/ASCII64/fingerprint/close admission. No APP_KEY, encryption.key,
hex decoding, new secret, security.php include, automatic rotation, write or
cache substitutes for the original admitted jwt.key. Profile debug/JSON is
generic and serialization refuses; private metadata is never put in a response.

## One registrar and scoped fixed JSON

After all providers boot, the selected profile checks exact configuration and
service identities, refuses existing api.php routes, and calls the one existing
LegacyApiRoutes registrar with its new trailing explicit profile option. Original
candidate callers retain default false, solely for their existing isolated
rehearsals; no default route file starts either path. The actual public opt-in
uses true and cannot silently choose the ungated candidate.

Ordering is **LegacyApiWire → admitted profile gate → existing bearer/controller**.
Raw method/path rejection and OPTIONS happen before the profile gate or any
credential/key/DB operation. The selected gate requires the same immutable
configuration/budget/key-source objects and the canonical fresh HTTPS origin.
Changed/incomplete configuration fails closed; disabled/stale selected routes
return fixed JSON404 without resolving key or database. Raw-wire OPTIONS and
invalid-method/path responses remain intentionally available without admission.

LegacyApiWire preserves existing controlled HttpResponseException responses,
including 400/401/409/429/503 and Retry-After, and maps otherwise unhandled
downstream failures to **exact JSON500**
`{"success":false,"message":"Internal server error"}`. The common CORS tail remains
unchanged for these responses. No exception, trace, SQL, fingerprint, private
path, token or key appears in that response; no retry/auth fallback is added.
The boundary handles both directly thrown exceptions and Laravel's actual
Routing\Pipeline responses carrying the ResponseTrait exception marker. The
pipeline renders each inner slice before returning to outer middleware; a
catch around `$next` alone is insufficient. This normalization is local to
LegacyApiWire; the global exception handler and every other route are unchanged.
All wire responses also carry native-style no-store and nosniff headers, so
issued tokens and controlled errors are not offered as cacheable/sniffable output.
This is a route-scoped downstream boundary, not a global exception handler or
certificate for failures before the routing/middleware pipeline exists.

## Existing semantics deliberately unchanged

The login JSON/string shape check still precedes the single shared consumption;
API username stays raw and REMOTE_ADDR remains the raw peer/local fallback.
Only a confirmed committed allowed result reaches the existing stock local
provider/hasher and only successful credentials reach signing-key secret().
Both successful and failed attempts consume the same Web/API budget. Writer,
Core, field/window/namespace rules and commit-before429 are unchanged.

Bearer verification, current account/password-version binding, guard restoration,
own data scopes, read/status envelopes, API attendance writer/idempotency,
aliases and controlled failures remain exactly current Main. Web LDAP login,
sessions/CSRF/UI, terminal/history/Admin and native sources are untouched.
This source adds no request-time DDL, schema/cookie/manifest rewrite or reset.

## Evidence and operational boundary

This package enables NOTHING in the running environment. Syntax, whole-source
mapping/preservation, a single explicitly invented pure composition/wire model,
and independent bounded source/security review are source evidence. A separate
coarse real Laravel Router/Pipeline/ExceptionHandler proof uses synthetic internal
Request objects only: HTML/JSON Accept and debug failures become exact JSON500,
controlled errors retain status/body/Retry-After, raw OPTIONS/rejections skip the
inner slice, and an unrelated route retains ordinary exception rendering.
It is not full Application/kernel, credential, token, key, SQLite/PDO,
physical admission, worker, HTTP/client, LDAP, browser or production acceptance.
The existing key source's positive physical read and prior budget source's real
IMMEDIATE/commit/race behavior are not proved by composing their definitions.

No original-key metadata admission/provisioning CLI exists here. Obtaining that
metadata, enabling either explicit flag, registering a live public profile,
counter/window/native-worker transition, actual DB/key provenance, trusted
proxy/HTTPS deployment, client acceptance and native/#197/session29/30 cutover
remain separately authorized/reviewed steps. Known verifier nbf differences,
API pre-lock credential-revocation race, O(countercount) scan and native
autocommit interleavings remain; no full API migration or issue closure is claimed.
