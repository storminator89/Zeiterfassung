# Separately closed new-device enrollment source

Base: Main `3cdcf90f5cf6331f2d57940e0ee3eb602d5907f7`. This #79/#81 source-only
slice connects only the native `terminals.php` **Neues Gerät** / `action=device`
operation and its one-time enrollment-code alert. Existing Site creation,
module settings, device/access readers and both revocations are retained.
No actual installation, device, account, credential, key or runtime is changed.
This is not terminal protocol activation or hardware acceptance.

## Independent default-off composition

The trailing trusted registrar argument `includeTerminalDevicePairings=false`
requires the existing Terminal page, exact non-debug mode and a separately bound
operation-scoped `AttendanceWriter`. It records only the in-process capability
`chrono_terminal_admin.device_pairings_enabled`. It opens no connection, resolves
no writer/key source and changes no stored/environment setting. Previous
registrar positions and every shipped composition remain unchanged.

The one Terminal POST retains the existing encrypted cookies, persistent blocked
Session, `LegacyFormCsrf`, refreshed account, `auth:web` and `auth.session` stack.
Raw-form preservation and fixed POST authentication 401 now include this
separate action. Unknown/mixed commands, other capabilities, absent facade and
debug mode cannot select enrollment; enrollment does not grant any other action.
`Interactions::terminalDevicePairings=false` is a separate form-display choice,
not authority. Both choices show the native card/labels/classes/field names;
a selected server operation still authorizes a manually supplied valid request
independently of whether its form is shown.

The new Site-option reader uses the existing fresh snapshot/current-local-Admin
gate and projects only `id,name` in native name order. It neither lists people
nor reads device generations, pairing hashes/codes, credentials or keys. Labels
are escaped; a displayed Site is not a future write authorization. No assets,
layout, host/security settings or new UI are introduced.

## Fixed writer operation and confirmed result

The facade accepts only `action=device`, scalar `name` and canonical positive
`site_id` form strings. Names retain native trim and 100-byte length. Arrays,
extra fields, zero/leading-zero/overflow IDs are rejected before writer
resolution instead of reproducing accidental native scalar-to-integer casts.
Only the refreshed web guard selects local Admin identity and password version.

Inside the existing one-attempt same-PDO IMMEDIATE writer lock, enrollment first
calls `assertExpectedWebPrincipal(admin, 'local', version, 'admin')`, then checks
the native effective module/server permission and current Site existence. Only
then does it generate the native 24-byte random enrollment code, 16-byte device
invalidation generation and 600-second expiry, and perform this prepared SQL:

```sql
INSERT INTO terminals(site_id,name,generation,pairing_hash,pairing_expires)
VALUES(?,?,?,?,?)
```

Only the SHA256 code hash is stored. Native `enabled=0`/empty-contact defaults
remain: creating this row does not release a device or prove it is online.
No user/credential/module/grant/work row is changed; no signing-key source is
resolved, generated, derived or rotated. The unchanged native protocol consumes
a valid code and derives its device signing material only in its separately
admitted flow, which is neither edited nor executed here.

The result leaves `executeLocked()` only after confirmed COMMIT and idle state.
Unknown COMMIT, result construction or rendering failure cannot trigger an
internal retry, replacement code or success/rollback claim. The existing
transaction/poisoning lifecycle is unchanged. Audit scope remains the fixed
`web:{admin}` / `terminals.php`, with no code/hash/generation in its reason.
The known admitted schema has no new Terminal audit journal; none is invented.

## One-time direct Admin response, not Flash or session storage

Unlike nonsecret commands' native PRG, enrollment success deliberately renders
its existing native one-time alert in the **direct POST response**. There is no
code in Flash, session data, URL, redirect, old input, audit context or application
logging. The existing exception policy logs only exception type. No code is
copied into checked-in evidence or rendered preview files.

`TerminalDevicePairing` holds the code privately, marks its constructor argument
sensitive, redacts ordinary debug output, refuses serialization/cloning and
consumes the code once in the Blade alert. Expired/reconsumed results refuse
rather than show the code again. The fresh Admin read boundary is rechecked
before rendering; normal registered web-context reads also recheck source,
role and password version. A subsequent GET has no code source to recover.
The DTO contains only this ephemeral result and nonsecret Site/device display
fields, never a signing key or stored hash/generation.

Every selected enrollment page reuses the existing `AdminCredentialDocument`
policy and existing local-only layout branch: no-store/no-referrer, self-only
CSP with nonce, no-frame and nosniff. A stricter upstream CSP is intersected,
not replaced. Default/non-enrollment layouts are unchanged. Every enrollment
POST result/error suppresses inherited `?saved=1` so an older Site/module/revoke
redirect cannot falsely claim that an uncertain enrollment was saved.

“One-time” is server-side result consumption, **not** guaranteed browser-history,
screenshot, clipboard or user-memory erasure. A user-confirmed POST refresh or
resubmission can create a new disabled row/code; this slice does not add global
idempotency or invent native duplicate-name restrictions. After loss/uncertainty,
reload and inspect the stored state; do not automatically repeat. A disabled
pending row may remain until expiry or a separately authorized revocation.
No automatic cleanup/recovery mutation or plaintext code recovery is added.

## Bounded evidence and still-open gates

PHP 8.5.12/Laravel 13.38.0 necessary syntax and compiled-Blade checks are combined
with one coarse disposable known-v2 fixture smoke, not real installation data.
Synthetic guards cover actor/form denial before writer resolution, stale locked
role/password, module/server/Site gates and one disabled-device enrollment.
Other table rows, audit/schema and original/backup bytes remain unchanged.
Code/hash shape, one-time consumption, debug redaction and refused
serialization/cloning are checked without emitting token values.

Direct controller probes use a synthetic inert array Session and facade result,
not login/Session/CSRF middleware execution. They cover one response display,
no secret session/Flash/URL/Location, local-only document policy, stale saved
marker suppression, missing/debug/default denial and action isolation. A throwing
facade simulates an unconfirmed service outcome; it is **not** an actual failed-
COMMIT test. Seven fresh-process route-table selections cover none, each isolated
capability and all five, including protected middleware declarations, raw-form
preservation and fixed authentication responses, with zero writer/DB resolution
and no HTTP dispatch. The first harness-only Site setup attempted to insert id 1,
which the fixture already creates; setup was corrected to update that synthetic
label. Product validation/assertions were not weakened.

No real accounts/devices/keys, login/browser/HTTP, terminal hardware/NFC,
concurrency, actual uncertain-COMMIT, interrupted/back/repeated-POST acceptance,
new GitHub CI/write or publication is performed. Test/evidence files stay outside
the source patch. The capability remains off. #79/#81 remain partial; personal
credential provisioning and complete lifecycle/terminal/operational acceptance
remain separate. V5 schema/authority work is independently prepared: this package
does not activate or claim complete V5 terminal-grant lifecycle coverage. Any
serial Main/V5 union, especially shared writer/provider/route/controller paths,
requires a new exact-tree review before later activation.
