# Existing terminal root admission (CH-81)

The Laravel terminal backend can now bind the two **already existing** native
terminal roots without importing `config.php` or invoking `app_secret()`. This is
source composition, not key provisioning, database adoption or an enabled
installation. The device and card algorithms, ASCII key bytes, wire format,
HMAC input, nonce/grant rules and common attendance writer are unchanged.

## Explicit, default-off composition

Both `FRAMEWORK_CHRONO_TERMINAL_HTTP_ENABLED` and the independent
`FRAMEWORK_EXISTING_TERMINAL_KEYS_ENABLED` default to false. The latter selects
`AdmittedTerminalServiceProvider`; when false it does not parse the unused
metadata, bind keys, resolve a writer, or read a root/database file. Existing
explicit test compositions remain possible without selecting this provider.

An enabled composition requires uncached configuration and routes, production
/non-debug configuration, the existing admitted read/write runtime and the exact
named `chrono` database configuration. It supports the already implemented
known-v2, user-audit-v3 and intake-policy-v4 profiles. The separately selected
**NEW account-lifecycle-v5** profile uses the exact same admitted RuntimeDatabase:
its unchanged canonical `chrono-fresh-*` cohort/manifest guard remains mandatory
before opening the operation writer. V5 is never aliased to V4. Its existing
lifecycle-aware terminal writer still enforces current account status/generation
on identification, grants, event replay and receipts; reactivation cannot revive
an old grant. This is source composition following integrated PR 296, not an
installation/runtime activation. No native fallback, web session, CSRF exemption
for another route, or API JWT guard is introduced.

The operator-supplied `FRAMEWORK_EXISTING_TERMINAL_KEY_ADMISSIONS` is canonical
base64 of compact JSON encoded with unescaped slashes. The JSON object has exactly
`device` and `card` entries. Each entry has these exact typed fields:

- `version`: integer `1`;
- `path`: canonical absolute path, with the fixed basename
  `terminal-devices.key` or `terminal-cards.key`, respectively;
- `device`, `inode`, `owner`, `mode`: integer file identity/UID/mode metadata;
- `parent_device`, `parent_inode`: integer immediate-parent identity metadata;
- `sha256`: lowercase SHA-256 of the existing **64 ASCII bytes**, not decoded hex;
- `database_profile_sha256`: the pure canonical digest from
  `ExistingApiKeyAdmission::databaseProfileDigest()` for the selected
  `RuntimeAdmission`.

`mode` is integer 256 (0400) or 384 (0600), not an octal-looking JSON string.
Metadata contains no root bytes, but paths/fingerprints are operationally private:
base64 is **not encryption**. Do not put it in browser responses, logs, repository
files or publicly readable EnvironmentFiles. There is no request-supplied path,
path discovery, metadata-generation command, or configuration-cache workflow.
This change does not set any installation environment variables.

## Same Application as FreshWeb/LDAP

A selected closed-setup FreshWeb profile may now retain `http_enabled=true` only
with the separate existing-key opt-in and admitted terminal profile. Both
profiles bind the same RuntimeDatabase metadata; FreshWeb freezes terminal
settings and checks the profile before web session work, while terminal dispatch
rechecks a selected FreshWeb profile before a nonce/limiter or booking mutation.
Standalone default-off/existing-root compositions keep their original behavior.
No key, account, terminal or operating instance is created by this union.
The integrated V5 non-expiring session-lock/route-override contract is preserved;
see [roles and composition](fresh-ldap-login.md). Synthetic shared-provider boot
and signed heartbeat are not safe operating activation or device acceptance.

## Read-only physical boundary

Each use checks the immutable configuration, selected runtime/key objects,
exact immutable RuntimeAdmission, transient writer selection and writer/protocol
factory bindings. The legitimately created cached protocol also checks before
its enabled/dispatch methods, including before pairing's limiter or COMMIT.
This is not a general sandbox against arbitrary trusted code replacing the
container's protocol instance with an unrelated implementation. Only then does the source read the admitted file. It requires
an existing regular single-link file with the exact device/inode/owner/mode and
64 lowercase hexadecimal ASCII bytes; the current effective UID must own it.
The immediate parent must be private 0700 with its admitted identity. Ancestors
must be real directories owned by root or the same UID, without unsafe
non-sticky group/other writes. Symlinks, alternate basenames, hardlinks, changed
identity/content/permissions, and roots inside either the checkout or the
**actual** (including custom) public directory are refused. Device and card
files must have distinct physical identities; their contents are not required
to differ by a newly invented policy.

The source compares `lstat`/opened-handle `fstat`, bounded-reads at most 65 bytes,
checks the admitted hash, then rechecks file and parent state before returning
the original bytes. It never creates, repairs, rotates or caches a root, and
never calls a secret loader with creation fallback. A cached protocol cannot
continue after configuration/key-source/file identity drift. Request admission
failures remain generic failures without paths, fingerprints, root bytes or
underlying exception diagnostics. As with the existing native protocol, a key
failure after an already committed pairing is not a rollback claim.

## Evidence and limits

A bounded PHP 8.5.12 / Laravel 13.38.0 / Python 3.12.14 journey ran the actual
existing Python `Remote` signing/response code through **private process pipes**
to synthetic Laravel Request objects, the actual controller/backend and the
common operation-scoped writer. The same bounded journey ran once for a private
known-v2 fixture and once for a synthetic canonical NEW V5 cohort, using constant
invented root bytes and an invented PIN; no real account, server or root was
accessed.
Pair → NFC-shaped identify → start → pause → resume → end → heartbeat completed.
Exact event replay was unchanged; changed payload, used grant, duplicate nonce,
wrong signature/site and revoked device were refused. Four business events,
closed session/pause and the derived terminal audit source were verified.
Configuration drift and key-object replacement were refused through the
already-resolved protocol for heartbeat and unsigned pair, before limiter or
other mutation. Root-inode replacement was refused on signed heartbeat. Full
row fingerprints of nonce/limits/events/audit/terminal tables stayed unchanged.
Default-off laziness, actual custom-public-root exclusion, no database opening
while composing/reading roots, unchanged root bytes/modes, and removal of the
owned synthetic fixtures were also checked. For V5, a missing canonical cohort
manifest refused writer resolution; inactive accounts could neither identify nor
replay, and reactivation with a new generation could not revive an old event
grant. A fresh identification then succeeded. The cohort guard and existing
V5 writer code were not weakened or replaced.

This is not a new HTTP/SAPI/TLS/browser, physical NFC/terminal, production-key,
concurrent-writer, crash/recovery, operating-instance activation or deployment
result. Existing
separate ingress/raw-body and native terminal protocol evidence remains separate;
hardware acceptance belongs to CH-64 and the user performs browser acceptance.
