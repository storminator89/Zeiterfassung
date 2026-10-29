# #32 — Redacted LDAP configuration journal

Source basis: actual main `64948f8e87c72d26338595aef7afed0382198101`, tree
`87fbcca1334b3ae9cb163ce4e8a4a7189aa4d3aa`.

## Active change in the existing import flow

The shared `chrono_ldap_import_locked` used by Native and Laravel now journals
its successful configuration INSERT/UPDATE into the existing append-only
`audit_log`. It reads the old latest row before the unchanged settings write,
reads the actually stored latest row afterwards, then appends one fixed
`ldap_settings` entity. Older settings rows remain untouched. The original
import caller owns the transaction, authorization, actor/reason UDFs, locking,
commit and rollback; this helper never connects, fetches LDAP, installs a UDF,
changes a schema, decrypts a credential or starts/ends a transaction.

The journal has the actual exact configuration ID, original database timestamp,
trusted actor and existing import reason. `user_id=0` follows the existing global
configuration convention: only the existing Admin history scope sees it, not
employee or direct-team history. The existing Native/Laravel shared History
entity filter and selected-field before/after display now recognize this entity;
there is no new route, UI or broader read permission.

## Deliberate minimization, not a raw configuration dump

- Endpoint projection allows only a single LDAP/LDAPS scheme, valid DNS/IP host
  and optional port. Bare valid hosts get an explicit `ldap://` representation.
  Userinfo, path, query, fragment, multiple hosts, controls, unsupported schemes
  and invalid endpoints are entirely omitted, with an explicit redaction flag.
  This projection does not normalize/rewrite the actual saved configuration or
  certify TLS, connectivity, CA policy or source admission.
- Port is an exact bounded integer, otherwise omitted with a redaction flag.
- Raw BaseDN, bind identity, plaintext password and encrypted credential are
  **never selected for the journal**. SQL returns only presence and comparison
  booleans for these fields; snapshots contain no raw values or fingerprints.
- UPDATE reports whether BaseDN/bind identity differ from the incoming values,
  but never what either value was. INSERT has no invented previous comparison.
  `credential_supplied=true` says only that this import supplied a credential;
  it does **not** claim that its plaintext changed. Re-encryption alone is not
  proof of a different password.
- No credential hash, token, directory entry, account list, email or display-name
  fields are journaled. Both complete safe snapshots use fixed keys only.

A successful repeat synchronization still writes settings in the existing flow
and therefore gets a journal row. Journal failure is mandatory failure of that
same transaction, not best-effort logging: the original caller rolls back the
configuration and later import mutations together. A later import/caller failure
also removes its new journal row. The feature invents no historical baseline or
past configuration changes. DB/server operators remain trusted.

## Evidence and limits

PHP 8.5.12 / SQLite 3.46.1: one eight-phase actual shared import journey over
anonymous Memory with the original v1/v2 migrators/audit triggers and original
History filters/presentation. Empty materialized directory stages avoid all
account/password/key generation. INSERT, changed/latest-row UPDATE with an exact
ID above 2^53, preserved older row, repeated source fields, URI canary redaction,
Admin vs member/supervisor history scope, mandatory journal-failure rollback,
later-caller rollback and final schema/account/attendance preservation pass.
The whole resulting journal contains none of the bind/BaseDN/ciphertext canaries.

The existing coarse `tests/ldap_settings_order.php` fake was minimally adapted to
recognize the new fixed safe reads/audit INSERT; its original preflight/encryption/
cleanup/transaction cases still pass. No new test suite, additional workflow or
manual CI dispatch. Relevant PHP syntax and exact fresh-Main content preservation
are checked before publication.

No real LDAP transport, secret encryption, account mutation, real data, file DB,
positive admitted Runtime/V5 writer, login/session/CSRF/HTTP/browser, production,
concurrency or performance acceptance. No real credential/hash/key/token creation,
source activation, host change or deployment. Existing Native/Laravel gates and
unknown-outcome error handling remain unchanged. #32 remains partial/open: this
redacted entity is not a complete raw LDAP configuration disclosure, does not
retrofit old history and does not certify all administration audit paths.
