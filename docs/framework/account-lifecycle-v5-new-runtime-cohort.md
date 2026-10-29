# V5 Runtime candidate: only the canonical NEW Laravel cohort

Source anchor: Main `654e93f66e01ea65891bd5d1e79bd6f5add0d488`, tree
`9908f246c309221bd9db83e6f5c90f8b6e8d3fbd` after PR294.
**Isolated source candidate, HOLD_PENDING_E2E. No production activation.**

The earlier blanket V5 hold is not replaced with a request/environment bypass
flag, V4 alias or unrestricted source opener. V5 now has a fixed additional
admission prerequisite at every original Runtime source-identity boundary:

- Existing canonical `application.sqlite`, inside the existing NEW producer's
  `chrono-fresh-<24 lowercase hex>` directory; owner-only0700 and trusted Unix
  ancestors, no symlink/alias or webroot placement.
- Existing `runtime-admission.json` in that same private cohort: owner0600,
  regular single-link file, bounded4096-byte stable read, canonical creator JSON
  encoding (including rejection of duplicate keys), actual integer metadata.
- The complete on-disk manifest must strictly reconstruct the same immutable
  RuntimeAdmission tuple: exact distinct V2/V3/V4/V5 signatures, identity and
  approved settings. No caller-supplied handle, extra flag or inferred column.
- Runtime captures only directory/database/manifest identity and manifest hash
  on its first physical check; every existing source identity check repeats the
  validation. Normal DB writes and creation of setup/key files do not change
  these identities; replacement/drift does refuse. No key file is read here.

This namespace is exactly what the existing Native early guard refuses before
secret/session/connection/DDL. A renamed V5 database or arbitrary admitted path
does not become a Runtime source. The existing PDO/native snapshot checks for
Dev/Inode, SQLite header/WAL, settings, complete ledger/application/policy state,
and final source identity remain in place. The writer still owns one dedicated
operation handle; old generationless V5 operations refuse before BEGIN. Current
account-generation/role/hash/source authority and PR291/294 outcome masks remain
unchanged. V2/V3/V4 take no additional file/manifest I/O or capability branch.

## Independent operating gates are still mandatory

This prerequisite is not human recovery, authentication or a public route.
Unchanged FreshWeb identity/configuration/HTTPS/session/CSRF/budget/key checks
remain authoritative. Its registrar still requires a current eligible local
Admin or the exact empty-source setup selection; existing token/profile/expiry/
closure and same-lock zero-user rules cannot be replaced by this cohort check.
API needs its separately reviewed exact V5 factory/key/generation/budget union.
Disabled/unimplemented channels do not gain routes from a Runtime file check.

The supported file identity remains pinned. A copy to a different inode/path is
not automatically recovery; no manifest edit/rebind/adoption operation is added.
First isolated recovery proof is a cold in-place restore retaining identities,
followed later by real setup/login/booking and controlled restore acceptance.
Cross-inode/datenträger recovery remains a separate explicit operation.

The final coherent source must pass the Native/API/restore handshake and actual
isolated browser journey before publication/activation is claimed. No server or
credential generator is started by this code. Generating new stored App/API keys,
setup token or account password hash requires the specific current authorization;
prior coarse fixture permission is not a blanket persistent credential grant.

## Credential-free source evidence

Nineteen actual file-only cases use independent disposable cohorts, not the real
acceptance target: exact V5 Runtime read and anonymous operation writer commit;
V4 control; renamed database/directory, missing/symlink/hardlink/insecure manifest,
insecure directory, differing/duplicate/numeric-string metadata, same-byte manifest
replacement after first admission, read-only/symlink/hardlink database, live schema/
ledger drift and WAL header refusals. Rejected calls preserve source bytes.
The valid V5 reader is real idle query-only PDO with all five application versions
and the exact four-row ledger; its anonymous quota writer persists counters without
creating users. A generationless legacy transition still refuses. No password
hash/key/token generator, setup account, secret file, browser or server is used.

Syntax, whole-tree/inverse preservation and independent source review are separate.
These cases are not successful setup/login/HTTP/CSRF/browser/API/LDAP/terminal,
concurrency/crash/restore/human recovery acceptance. All actual new credential and
server actions remain pending their own explicit authorization and safe handshake.
