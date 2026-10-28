# Inactive Laravel LDAP sync transport and native-format cipher

Source preparation on Main `4c2ceb1ba8aa35c9bf0c6f02378669433705616a` only.
These four new files do not register a provider, route, admin action, policy,
writer, directory, CA, key, account or environment switch. They are not a finished
Admin sync feature and do not enable LDAP. Native code and the existing login
verifier remain unchanged. No real configuration, key or connection is used.

## Required operation order (later integration, not implemented here)

1. After the normal Laravel session/CSRF/throttle stack, obtain the server-side
   identity and check a **fresh current local Admin and persistent intake policy**
   before resolving transport or any key source. An arbitrary submitted actor,
   stale page or `chrono_registration`/session flag cannot authorize LDAP sync.
2. The trusted operation composition selects one admitted endpoint/BaseDN/CA and
   finite search limit. Browser fields must not select arbitrary URLs, targets,
   CA files, callbacks or roots. Provenance/admission is an external prerequisite,
   not something this transport infers from syntactically valid values.
3. `LdapSyncTransport::collect()` must finish TLS, bind, the complete bounded search,
   result release and unbind. No database transaction is open during this work.
4. Only after transport succeeds, resolve the explicitly admitted existing native
   encryption root and encrypt the freshly supplied bind password. Never prefill,
   log, flash, serialize or echo the secret; no stored-secret decryption is added.
5. Build the separate `MaterializedLdapImport` stage, then use the admitted
   collision-safe Writer. That later path must recheck current Admin, the intake
   policy/revision and relevant authenticated context inside the same writer lock
   before **any** settings/identity write. Changes while LDAP was in flight must
   refuse the stage; no automatic retry. Local-account collisions reject the whole
   stage, never convert the local/last recovery Admin or silently skip rows.

## Optional official PHP/OpenLDAP collector

`PhpLdapSyncTransport` implements only a service-bind/import search, not login,
account provisioning by itself, or the user-bind verifier contract. Construction
is pure. Only literal `enabled=true` admits collection; missing optional LDAP
extension/capabilities or invalid trusted configuration fail closed on use.

The target must be a single canonical `ldap://host:port` URI with explicit port,
no credentials, path, query, fragment or failover list, plus an exact nonempty
BaseDN. This first slice supports verified **StartTLS only**, not LDAPS, AD/UPN,
SASL, discovery or failover. The optional CA must be an existing readable canonical
absolute file; null uses managed system trust, never disabled verification.

Options are connection-local: LDAPv3, five-second network/operation/search limits,
referrals disabled and `TLS_DEMAND`. Certificate demand and an explicit CA are
read back before StartTLS. No process-global TLS setting or plaintext retry.
Bind DN is nonempty and exact; password bytes are not trimmed, decoded or stored.
The search retains the native fixed `(objectClass=inetOrgPerson)` filter and
`uid/cn/sn/mail/ou/manager` attributes. Result code must be zero, no referral or
reference may remain, and materialized count/shape must match the complete result.

The explicit limit defaults to 1000 entries and may be 1–10000 in trusted code.
The request asks for one extra entry to detect overflow. Any limit/partial-result
code or overflow refuses the whole result before a DB write. No silent truncation,
paged continuation or claim of compatibility with an unlimited large directory.
Entry order, missing-attribute import eligibility and native first-value semantics
are left to the separate materialized stage/importer, not rewritten here.

Cleanup attempts unbind even after result-release failure. A cleanup failure
prevents returning the stage; all operational errors are fixed and omit target,
DN, CA path, server diagnostics, password and previous exceptions. Results may
contain staff identities and must remain inside the authorized operation, not a
view/log/cache payload. Profile serialization is refused; debug output is reduced.

## Existing native cipher contract, no key provisioning

`AdmittedLdapSettingsCipher` takes one trusted zero-argument existing-root source.
It does not prove that source's provenance, discover/read/create a key file, use
Laravel Crypt, load `admin.php`/`security.php`, or bind any service. Resolution is
per encryption call, never constructor-time or a cached root property. Invalid
roots and source errors fail with a fixed message and no previous exception.

The root must match the native security contract: exactly 64 lowercase hex bytes.
Encryption converts it with `hex2bin`, uses maintained PHP/OpenSSL AES-256-GCM,
a fresh 12-byte IV and 16-byte tag, and emits exactly native
`v2:` + base64(IV || tag || raw ciphertext). There is no AAD, password normalization,
legacy public-key fallback, key rotation or migration write. No decrypt/readback
API is provided. Serialization is refused and debug output exposes no root.

## Evidence boundary and remaining gates

One coarse pure source journey covers default-disabled collector refusal and
native-function cipher interoperability with an invented in-memory root/input;
only the exact native crypto function definitions are extracted, not its bootstrap.
No application, DB, LDAP extension/network, real key/configuration, account,
listener, browser, HTTP, CI or deployment is exercised. Syntax and full unrelated
source preservation are checked. This is **not** successful LDAP protocol or
complete Admin sync acceptance; five-second option effectiveness, TLS/CA, server
limits, real search and cleanup still need a separately authorized synthetic
protocol/HTTP integration run before activation.

Persistent intake-policy/schema admission, authenticated Admin service/controller,
the existing Admin UI, limiter wiring, key-source admission and collision-safe
Writer composition are separate gates. Existing native conversion risks are not
fixed by these inactive files. Shared onboarding/policy/correction paths are not
edited by this packet.

Primary API contracts: [PHP LDAP search](https://www.php.net/manual/en/function.ldap-search.php),
[complete result status](https://www.php.net/manual/en/function.ldap-parse-result.php),
[PHP OpenSSL encryption](https://www.php.net/manual/en/function.openssl-encrypt.php).
