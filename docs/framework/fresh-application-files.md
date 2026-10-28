# New empty application files (source-only preparation)

`chrono:prepare-fresh --private-parent=/canonical/private/parent --new-only`
prepares **new empty V4 files**, not a running or provisioned installation. It is
an explicit operator CLI command, not a web endpoint or automatic bootstrap.
Existing local-web fixture v1/v2/v3, native installation, old-data adoption and
their credentials remain unchanged. Do not point this command at an old database;
there is deliberately no target/reset/import/resume option.

## File boundary

- Unix PHP CLI with `posix_geteuid`, SQLite/PDO, the maintained framework dependencies
  and `fsync` is required. The parent must already exist, be canonical (no symlink
  or `.`/`..` aliases), owned by the current operator and mode0700, outside the
  checkout and both application webroots. Existing permissions are never changed.
- **Explicit POSIX trust boundary:** every ancestor, including `/`, must be an
  actual directory owned by root or the current operator UID. Group/other write
  is rejected unless the directory is sticky. Thus a trusted-owned1777 `/tmp`
  can contain an operator-owned private child: an unrelated UID can create its
  own names but cannot rename/remove that existing root/operator-owned child.
  A non-sticky0777 ancestor or any foreign-owned ancestor is refused, even if the
  final parent is0700. Allocation, every identity guard and cleanup repeat the
  complete path check; symlink components are always rejected, not followed.
- An exclusively created random `chrono-fresh-*` child is mode0700; the only
  database is exclusively reserved mode0600. The fixed trusted source inventory
  builds an anonymous **EMPTY** V2→V3→V4 schema in memory. `VACUUM INTO` materializes
  only that new source into the reserved empty file. No existing runtime DB is
  read/copied/exported, and no users, passwords, app keys or setup tokens exist.
- The new file is opened without SQLite `CREATE`, under its captured held-file
  identity. Parent/instance/file device, inode, owner, mode and single-link checks
  remain active before/inside/after the one dedicated ledger transaction. File
  size/content may legitimately change during DDL; no cold full hash is demanded
  inside that transaction. This does not defend against a malicious same-UID/OS
  administrator, trusted-owner permission changes, mount/namespace changes or
  host compromise. PHP path checks are not `openat`-anchored atomic filesystem
  operations: the rename/symlink-race argument relies explicitly on unrelated
  UIDs being unable to replace trusted-owned components through any admitted
  ancestor. Root/current-UID actors remain trusted, not a promised race-proof
  adversary. Sticky ancestors do not prevent denial of service/name exhaustion.
- The reviewed fresh V4 registrar writes the exact three schema-lineage ledger
  rows. They describe the freshly generated V2/V3/V4 schema chain, **not** historical
  migration/adoption of user data. Initial policy is manual on, LDAP/registration
  off; it does not block the separately protected first-admin setup.
- The actual new connection's FK/journal/synchronous values are recorded, not
  invented or imposed on existing files. With the maintained connector they are
  FK0, rollback journal `delete`, synchronous2. No new claim of FK enforcement or
  changed business calculation is made. Final closed-file inspection and the
  unchanged admitted runtime read opener must accept the exact identity, settings,
  three-ledger chain and empty V4 policy. Runtime switches remain false.
- Empty `sessions`, `cache`, `locks`, `compiled` directories are mode0700.
  `runtime-admission.json` (mode0600) is written **last**, exclusively and flushed
  with `fsync`. It is nonsecret metadata, not a complete activation manifest.
  The CLI receipt contains only file/directory metadata and explicit false flags
  for activation, credential creation and server start.

## Failure / interruption

There is no automatic retry, overwrite, recovery, restore or activation. Failure
messages contain no native exception/SQL/path/row details. Cleanup closes handles
and removes only confirmed objects from the newly owned cohort. Unknown sidecars,
replacements, links, nonempty children or changed identity stop cleanup and leave
the private instance for operator inspection. Existing files are never removed.
An interrupted/partial manifest is not resumable or sufficient evidence of success;
retain privacy, inspect/discard only the confirmed new instance, and never infer
that a ledger failure rolled back solely from the CLI error. File `fsync` is not
a claim of crash/power-loss acceptance or directory-entry durability certification.

## Still required before a usable installation

1. Integrate independently reviewed V4 runtime admission and all intake-policy
   gates, plus protected first-admin setup and fully local auth assets. Do not
   publish/activate this packet against a known failing admission prerequisite.
2. Provision a private independent Laravel app key and a strong setup-token
   digest through a separately reviewed operator process. This command never
   creates either. Deliver the token to the intended operator via an explicitly
   verified private channel, never public setup HTML, URL, chat or diagnostic log.
3. Compose the real default-off web application with the admitted database,
   final private session/cache/locks/compiled paths **before** limiter boot, fixed
   local assets, trusted host/TLS and persistent limits/session blocking. The old
   synthetic fixture router is not repurposed by this command. No server starts.
4. With applicable action-time authorization, the user enters their own admin
   password and operator token in the normal protected browser form; no injected
   session or autologin. Close setup/remove its token profile after confirmed
   success. The count-empty guard alone is not irreversible token consumption
   after deleting every user or restoring an empty DB.
5. Log in normally, then choose effective manual/LDAP/registration policy in the
   real admin UI. No email server/verification, LDAP availability or human admin
   handoff is implied by the empty file receipt. Visual/mobile/HTTP/concurrency,
   recovery and actual installation acceptance remain separate gates.
