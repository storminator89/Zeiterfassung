# Existing database snapshot card (default off)

The separate [V5 same-snapshot authority component](account-lifecycle-v5-snapshot.md)
does not activate V5 Runtime or change this existing older-profile export/HEAD contract.

This opt-in Laravel slice preserves the existing Settings database-snapshot card
and native `download.php` filename. It does not connect restore/import, copy a
native login, support LDAP-admin authentication, or activate any default route.
`LocalWebRoutes::register(..., includeDatabaseSnapshot: true)` additionally
requires an explicitly bound `RuntimeDatabase`. No production source binding,
manifest, path fallback or export action is supplied by this packet. The existing
synthetic fixture explicitly enables it only for its verified invented database.

## Sensitive scope and authority

The unfiltered SQLite database can contain password/token hashes, encrypted LDAP
values, PIN/card/pairing hashes, credential grants/generations, audit and personal
time data. External `.key` files, code/configuration and restoration procedures
are not included: **this is not a full-system backup**. Source development or
merging this inactive feature does not authorize creating, copying, downloading
or publishing an actual database snapshot. Every real secret-bearing export and
its destination need separate explicit authorization. No actual export occurred.

## Admission and fresh authorization

Existing PDO read/write methods, RuntimeAdmission and identity checks remain
unchanged. A private SQLite3 source factory uses the same trusted admission and
existing `identity(false)` before/after opening: canonical private single-link
file, exact device/inode, outside both webroots, approved rollback-journal header.
Normal SQLite runtime rollback sidecars/locks remain SQLite-owned; persistent WAL
is still refused. Do not use a cold file copy, checkpoint, attach, sidecar deletion,
writer fallback or `VACUUM INTO`.

The source explicitly uses `SQLITE3_OPEN_READONLY`, `query_only=ON`, timeout5000,
one main file, exact schema version/approved pragmas and exact owned migration
ledger. The fixed operation owns a DEFERRED read snapshot, checks admission in
that snapshot and reloads the guard identifier's current local-admin role and
password hash there. It compares that hash with the guard's authenticated hash in
memory only; neither is logged or returned. The unchanged protected web stack
still includes session/CSRF, RefreshLocalAccount, auth:web and auth.session.
Deleted/revoked/nonlocal accounts and nonadmins fail before destination creation.
Native LDAP-admin parity is not claimed by this local-only migration.

## One completed backup and private artifact

Only after same-source authorization, allocate a random0700 request directory
beneath the **already private admitted source parent** and a fixed0600 empty
`snapshot.sqlite`. An existing parent that is not private/outside both webroots
fails closed; no source/existing-directory chmod or host change is attempted.
Only the newly created request file may be chmod0600. Source and destination
identities must differ; destination opens READWRITE without CREATE.

PHP's supported `SQLite3::backup` is called once with fixed `main` names. It uses
the SQLite online-backup API, not a raw filesystem copy. False/exception/lock or
failed integrity/schema/ledger/identity checks refuse; there is no application
retry or alternate source handle. Destination schema SQL/ledger must match exactly; its physical schema cookie may
differ from the source after online backup. Destination writer/check handles close
and the source read snapshot rolls back/closes **before** response streaming. Only a
complete verified artifact gets attachment headers. Its path is never exposed by
an accessor, response header, SQL/exception text or user selector/callback.

The PHP wrapper copies all pages in one step; timeout5000 is not a total-duration
guarantee. Large snapshots/read locks may delay writers and consume storage.
No production-size, concurrent-writer, disk-full or crash acceptance is claimed.

## HTTP and cleanup policy

* GET returns the fixed `timetracking.sqlite` attachment, completed Content-Length,
  application/octet-stream, no-store and nosniff via a fixed StreamedResponse.
* HEAD runs the ordinary refreshed local-admin guard only: status200, safe fixed
  headers, **no export source, backup, private artifact, length generation/body**.
* POST is not registered. GET Range/If-Range is rejected400 before generation;
  HEAD ignores range hints and remains bodyless without generation.
  Offload hints are ignored; no X-Sendfile/X-Accel, range processing, ETag,
  Last-Modified or conditional304 behavior is used.
* Admission/creation failure returns a generic nonattachment500; expired auth is
  nonattachment401/no-store. No raw native exception/previous exception is kept.
* The artifact owns cleanup from allocation, failed creation, response construction,
  completed/failed streaming, discard/destruction and shutdown. Cleanup is exact
  inode/path only, idempotent, never recursive/glob deletion. Source handles never
  remain open through a download.
* After headers, abort/read failure cannot repair HTTP status: stop the stream,
  do not append an error document, and clean up. A fixed sanitized event may be
  logged, never SQL, hashes, private paths or native exception messages.
* Unlink/rmdir failure or abrupt process/OS termination can leave a private orphan.
  Do not claim guaranteed crash cleanup or silently perform broad orphan deletion.

## Evidence limits

One approved invented-only compatibility proof on PHP8.5.12 established backup
under explicit RO/query_only/DEFERRED source checks, same-source invented Admin,
completed destination integrity/logical rows/schema/ledger, source rollback/hash
stability and full disposal. A separate one-coarse artifact lifecycle proof and
syntax/source checks accompany this packet; no HTTP/browser or actual data is
used. These do not establish real authentication, LDAP, production activation,
large-database behavior or authorization for an actual secret-bearing export.
