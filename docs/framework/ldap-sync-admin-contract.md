# Inactive LDAP Admin form/result contract

Source-only delta on the independently reviewed orchestration source
`ad24a3ece1f94ecf8c92f644bd6ac85a21d07882`, which itself has explicitly documented
local prerequisites. This is **not** actual Main, a mounted Admin form, source
adoption, profile/credential provisioning or finished LDAP sync. No common Admin
controller/DTO/Blade, layout, provider, route, locale, Writer or runtime admission
is changed by these five paths. The other owner's fresh-V4 policy/Writer mode
integration and the later serial combined review remain required.

## Fresh GET metadata, no external operation

`LocalWebLdapSync::targetForForm()` reuses the same guard-derived local Admin and
fresh DB-hash/persisted LDAP-policy snapshot as synchronization. A stale/deleted,
source/role/hash-changed or unauthorized principal, disabled policy or missing
V4 state refuses **before** resolving any external factory. The snapshot is
closed before resolving only the trusted transport's pure `target()` metadata.
The path never invokes `collect()`, the cipher/key factory or the Writer factory.
Unexpected profile/source errors remain fixed, without a previous exception.

The existing synchronize flow uses the same extracted private principal helper;
its validation, snapshot, target matching, collection, encryption, stage and
locked Writer call are otherwise unchanged. This is not a second auth/session
engine, a substitute for normal session/CSRF/throttle, or proof of profile source
admission. A later binding must remain restricted to the explicitly admitted V4
composition; a presentation object alone never grants authority.

`LdapSyncForm::fromTarget()` copies only URI/port/BaseDN for an already authorized
view. It has no bind username, password, ciphertext, root, old-input field or
enable flag. Null/unavailable presentation must not render a credential field.
Its target must come from the fresh service result, never a request-built object.
The DTO deliberately does not certify HTTP/CSP/asset safety; that is a separate
mandatory document boundary, not a boolean checkbox in a data model.

## Committed result, not a query-triggered success

`LdapSyncOutcome::fromCommittedPlan()` accepts only the native two-array manager
plan from the successful service return. It does not establish that commit itself.
It validates positive integer IDs and the existing seven fixed conflict reasons,
retains total conflict count and at most the first 20 ID/reason pairs in native
order. No assignments, DN, name, search entry, source value, credential or raw
error enters the display array. The transport's hard 10000-entry bound also bounds
this contract. Assignment count is deliberately not presented as changed rows.

`toDisplay()`/`fromDisplay()` use one exact bounded shape; unknown keys/codes,
duplicates, malformed counts and missing/excess details refuse. A later PRG may
flash only this array, bound to the current server-derived Admin ID, under the
existing encrypted session. It must never flash submitted credentials or accept
a query/request result as evidence. A fresh Admin check is still required before
consuming/displaying the one-use result. Object serialization is refused.

Manager conflicts mean the native identity/configuration import committed while
affected relationships were retained. They need a visible warning, not blanket
success. Unexpected response/flash/DTO failure after commit remains unconfirmed,
never a rollback promise or permission for automatic retry.

## Reserved later HTTP/UI/security integration

- The shared Admin owner freezes policy UI and manual/registration gates first.
  Then integrate sync dispatch/binding serially on the exact reviewed composition.
- Count `sync_ldap` in the strict single-action whitelist. Reject unknown/mixed
  commands before service work; pass raw POST data minus known CSRF fields only.
  Preserve admin.php's raw-form TrimStrings/empty-string exemptions.
- Keep fresh Admin + persisted policy authorization authoritative on both GET and
  POST, with the existing policy/revision/hash check again under Writer lock.
  Profile existence, hidden links, DTO presence or a disabled fieldset are not
  authorization. The old precursor import method is not an endpoint alternative.
- A persistent LDAP-action-only named limiter must not throttle unrelated Admin
  actions or disappear when a private runtime cache is rebound.
- Reusable bind credentials require a no-store **local-asset/CSP Admin document**,
  false app debug and private-entry acceptance. The existing app layout still has
  external CSS/fonts; do not mount a secret field there without the explicit
  serial security/layout slice. A meta tag or DTO comment is not proof of headers.
- Reuse the existing LDAP card/field names; admitted target values are readonly
  and compared exactly at POST. Bind DN/password are always blank and never
  restored from old input or decrypted settings. No script-driven automatic retry.
- Malformed commands: 400; stale/unauthorized principal or policy: 401/403;
  throttling: 429; operational/unconfirmed failures: fixed 503. No credential
  reflection, query-triggered success, unescaped native diagnostics or LDAP call
  during a DB snapshot. Successful POST can use bounded actor-bound PRG outcome.

The supplied single invented read/presentation journey exercises only fake guard
and pure target metadata with the actual fresh snapshot/policy code and strict
DTOs. No application, real authentication/session, LDAP, key, Writer mutation,
credential form, HTTP/browser, CI or remote action is performed. Source/syntax
evidence does not prove mobile rendering, private credential entry, response
headers, successful LDAP protocol or end-to-end Admin sync.
