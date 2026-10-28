# Existing API signing-key admission core (unbound, source only)

These two new classes prepare a read-only implementation of the existing
`LegacyApiTokenKey` interface. **No provider/route/config/default binding is
installed.** They do not create, copy, inspect, rotate, chmod/chown or activate
an actual key in this source packet. No API/account/DB or operational secret is
accessed. They are not a completed API composition or production permission.

## Pure, immutable admission metadata

`ExistingApiKeyAdmission::fromConfiguration(array, RuntimeAdmission)` accepts
exactly these ten fields, without file/DB/key access:

- `version`: integer1, describing this key-metadata format, not a DB migration.
- `path`: explicit canonical absolute spelling with final basename `jwt.key`.
  No empty/dot/dot-dot components, NUL, guessed APP_DATA_DIR/default path, stream
  wrapper, APP_KEY/env secret value or alternative encryption.key source.
- `device`, `inode`, `owner`, `mode`: integer metadata, inode positive, owner
  nonnegative, exact permitted file mode0400 or0600. Numeric strings/floats and
  coercion are not admitted. No ownership or permission repair is performed.
- `parent_device`, `parent_inode`: fixed immediate private parent identity.
- `sha256`: expected SHA256 of the ORIGINAL64 ASCII bytes, recorded only by a
  separately authorized existing-key admission operation. It is not a key and
  must not be logged/published or auto-adopted on the first request.
- `database_profile_sha256`: exact match to `databaseProfileDigest($database)`.

The digest is SHA256 of a versioned, fixed-order JSON object (UNESCAPED_SLASHES,
no newline) containing the validated RuntimeAdmission path/device/inode,
schema-cookie/signature, FK/journal/synchronous metadata and full optional
V2/V3/V4 profile/signature chain, including nulls. It is **not** Token4's raw
runtime-admission.json file hash; do not interchange those field contracts.
`databaseProfileDigest` is pure PUBLIC metadata processing and never reads key
or database bytes. The input RuntimeAdmission has already validated its shape.

This metadata match does not prove the actual DB/file or authenticate a caller.
A later trusted composition must supply the SAME immutable admission that its
RuntimeDatabase physically verifies. No request may supply metadata, paths,
fingerprints, source selection or a replacement RuntimeAdmission. No binding or
metadata-provisioning CLI is supplied here. Debug/JSON output is a fixed generic
label; serialization is refused. Actual private fingerprints/paths are not
returned in exception messages or debug output.

## Lazy read-only source

`new ExistingApiFileSigningKey($admission)` is pure: no lstat, file open, DB,
source liveness or key access in its constructor. Only an explicitly authorized
later call to `secret()` performs physical admission and a bounded read:

1. Supported Unix runtime/current UID must match the fixed owner. All ancestors
   must be directories owned by root/current admitted UID; no group/other write
   except sticky ancestors, no symlinks or aliases. Immediate parent is fixed
   device/inode, owner and exact0700. No path under checkout/native or
   framework/public webroots is accepted.
2. Existing file must be regular, original device/inode/owner/mode, one link,
   exactly64 bytes, with an unaliased real path. No create flag, write, mkdir,
   permission change or advisory metadata-path fallback is available.
3. Open only `rb`, compare lstat/fstat before/after, read at most65 bytes and
   require exactly64 lowercase ASCII hex characters. No BOM/newline/trim,
   `hex2bin`, raw-binary replacement, APP_KEY or newly generated JWT key.
4. Check the private expected SHA256 of those exact ASCII bytes. Recheck
   parent/ancestor and path/held-handle identities and stable file
   size/mtime/ctime, then confirm handle close before returning the bytes.
   No secret value is stored on the object or copied into config.

All failure paths use one generic exception without chained native diagnostic,
key, fingerprint or private path. Debug/JSON output is fixed, serialization
refused. This class never includes security.php: its native app_secret routine
can CREATE missing jwt/encryption keys and is not a read-only admission loader.

Trusted root/runtime ownership and mounts/ACLs remain operational assumptions,
not an openat/host-compromise certification. A same-UID compromised process can
change code/metadata; this source is not a defense against its trusted owner.
Expected fingerprint prevents an in-place content change from silently becoming
a rotation. Stop/drain is required for an authorized future source/metadata
change; no automatic retry, key cache, reopen/adopt or rotation workflow exists.

## Why this stays separate from activation and budgets

The legacy issuer/verifier continue to use existing64 ASCII signing bytes,
HS256 and their unchanged claim/version rules. A future production binding must
explicitly admit the original key and the current DB context before exposing
API routes. Existing clients/cross-DB tokens, lifecycle#29/#30 and documented
nbf differences are not resolved here. No tokens are issued or verified here.

A usable API profile still needs a truly shared, persistent Web/API login-budget
consumer/store and scoped route/error/runtime admission. Empty per-request or
separate cache budgets are not a substitute. Runtime lazy CREATE TABLE would
change the frozen schema cookie; a known countertable must be prepared before
new-source manifest admission or have its own approved cold transition. A
limited budget result must commit consumed attempts BEFORE becoming HTTP429.
No budget/DDL/Writer/Weblogin/Frontend file is edited by this key-only core.
No public API, new auth method or permission is activated merely by loading
these definitions; `LegacyApiTokenKey` remains unbound.

## Bounded evidence and remaining gates

The intended coarse source proof validates invented metadata/profile binding,
strict refusal/serialization/default-unbound behavior, and an owner-mismatch
failure before any filesystem/key read. It creates NO valid key file, key,
credential, password hash, account, DB, JWT, request, CA, server or binding.
Positive secret-file reading, symlink/race/permission/replace/cold-rotation,
actual key/DB provenance, service/HTTPS/worker/browser and real client acceptance
are expressly NOT executed/proved here. Syntax, exact3NEW scope, whole inverse
and preservation of all prior API/Web/LDAP/Admin/native/#197 source remain
separate evidence. Independent source review is required; source clearance
would still not grant key reading, provisioning, API activation or publication.
