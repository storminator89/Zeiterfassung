# Native entry points must not initialize a Laravel instance

Source anchor: published Main `bdcbdba48db2cc2e8a82df77c2cf203269e5045f`,
exact tree `2a068b56386df2f9532ee98f7b63e95d993ec25f` after PR270/271/272/273. The guard/helper are reused byte-for-byte from the independently
reviewed namespace-only packet on historical Main132ff9b0; its SQLite-profile
predecessor remains held, not bundled.

Reviewed NEW Laravel file preparation uses `application.sqlite` and
`runtime-admission.json`. Native bootstrap instead uses `timetracking.sqlite`.
Without an early boundary, pointing Native `APP_DATA_DIR` at a prepared Laravel
instance with no Native filename starts a parallel Native database and accesses
or creates Native secret files. A SQL check of only `timetracking.sqlite` cannot
protect this missing-file path.

The shared Native security bootstrap now rejects the presence of either fixed
Laravel artifact before `app_secret()`, secret-file access, session start or
Native configuration/DDL. Regular/partial files, directories and dangling links
all reserve this instance namespace. Only directory-entry names are inspected;
neither artifact, database, setup token nor key contents are opened. The existing
context-aware fixed503 formatter remains responsible for the response.

The check deliberately enumerates the already allowed directory and fails closed
if that enumeration fails. A direct `lstat()` existence check is insufficient:
under PHP 8.5 `open_basedir`, a reserved symlink with an external target can return
`false` even though its directory entry exists. Directory-name inspection also
rejects such links and self-looping links without resolving their targets.

There is no PDO/SQLite URI probe, copy, subprocess, new host setting or fallback.
Consequently this bounded check also works within an already allowed PHP
`open_basedir`, without weakening that setting. Native missing-file installation
and existing V2/V3 directories without these reserved Laravel names keep their
existing path. These names must not be reused for unrelated files inside a
Native data directory; the boundary intentionally fails closed on any artifact.

## Deliberately incomplete profile/cutover boundary

This does **not** identify a V4/V5 database renamed or copied to
`timetracking.sqlite` after both reserved artifacts were removed. It does not
prove database profile, ownership, schema, live-WAL state or Laravel admission.
It does not open the V5 Runtime or make a complete Native-V5 cutover safe.
Installation/cutover still requires stopped Native/concurrent writers and the
reviewed complete destination/recovery composition; a precheck is not atomic
against a privileged or concurrent file replacement.

The older `chrono-native-v4-guard` SQLite MAIN-file proposal remains separate and
on **HOLD_COMPATIBILITY**: PHP8.5 rejects its immutable `file:` URI under nonempty
`open_basedir`, including otherwise valid V2/V3 sources. A plain-path read-only
fallback may touch WAL/SHM, and immutable MAIN inspection misses markers only in
live WAL. None of that proposal's probe or unsupported fallback is activated by
this narrower fix. The full Native-V5 profile gate remains outstanding.

## Bounded evidence

On this final source, disposable synthetic Linux/PHP8.5.12 CLI checks execute
actual Native `config.php`: 24 reserved-artifact cases cover files, directories,
internal dangling, external existing/dangling and self-looping links for both
names, with/without allowed `open_basedir`. Fixed503 occurs before Native DB/key
creation; entries and external targets stay unchanged. An actual credential-free
NEW V5 cohort produced by the existing Laravel file producer is also refused by
Native configuration with/without `open_basedir`, preserving the entire cohort.
Six missing-file/V2/V3 controls execute only the definitions-only namespace guard,
not a successful Native configuration/bootstrap or secret-generation path.

Historical component evidence on Main132ff9b0 additionally exercised successful
Native missing-install/V2/V3/restart controls and the unmodified parallel-bootstrap
RED, which created disposable fixture keys. Those successful/key-generating cases
are deliberately NOT rerun here without specific credential-generation authority.
The historical external-symlink `lstat` RED motivated the retained scandir fix;
none of those historical cases authorizes a Runtime opening or a current positive
Native/credential acceptance claim. Current checks generate no key/token/account.

No operating data, credentials, server, browser, deployment or runtime setting
is used or changed. Non-Linux/case-insensitive filesystems, HTTP/session/CSRF,
permissions under a production account, concurrent installation, crash/restore
and human recovery are not acceptance claims. The exact-name Linux check is
not a cross-platform filesystem-identity guarantee. Issue30 and the complete
Native profile/cutover gate remain open.
