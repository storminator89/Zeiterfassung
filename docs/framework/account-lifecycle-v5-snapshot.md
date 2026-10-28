# V5 snapshot authority on the actual native read snapshot

Source anchor: Main `83717e8e56484b45a93c55d2901f054bfdcabdb9`, exact tree
`c177eba798eef8e77f23a0500eb843da6c78dfa6`. This sensitive component packet
adds no route, opener, manifest/key preparation or Runtime activation. The
original V5 `requireImplementedRuntime()` refusal remains before every source
open; it is not disabled or reflected away by the evidence below.

## Capture, same-snapshot check, then artifact

The existing `DatabaseSnapshotExport` derives `AccountLifecycleWebIdentity` from
the **same immutable RuntimeDatabase instance** used for export. It captures the
trusted local Admin guard model, not request/session actor or generation fields.
No optional forgotten dependency or optional account column can select V4.
RuntimeDatabase rejects a V5 authority on an older profile and binds a selected
V5 authority to the authenticated identifier/local/Admin/password-version tuple.
Both outer sensitive parameters and the internal authority parameter are marked
`SensitiveParameter`; native errors/SQL/rows/hashes/private paths are not chained
into the existing fixed failures.

In the selected V5 branch, the existing fixed Runtime operation:

1. uses the unchanged private READONLY native SQLite3 source opener;
2. owns the existing `BEGIN DEFERRED` transaction and performs admission reads
   on this exact handle, establishing its actual read snapshot;
3. validates the complete V5 application versions/policy in addition to the
   existing exact four-row ledger, identity and connection policy;
4. calls the internal `AccountLifecycleSnapshotAuthority::authorize()` on that
   same handle, re-reading id, username, role, source, password, active state and
   authorization generation and comparing the captured complete authority;
5. only then calls the existing `DatabaseSnapshotArtifact::capture()`.

The actor must still be an active locally login-eligible Admin. Deactivation,
departure, source/role/password change or a stale generation after reactivation
refuses before any export directory/file is allocated. The new checker has no
path, opener, transaction ownership, artifact or export callback. It returns no
row, handle or reusable authorization ticket.

PHP's native SQLite3 API exposes no transaction-state query. This internal
checker therefore depends on the fixed caller owning the already admitted read
transaction; `query_only=1` is checked but is not claimed to prove transaction
ownership. It is not a standalone public authorization or arbitrary-source API.

Authorization applies to that consistent snapshot, not instant cancellation of
an already authorized export after a later state change. Rollback-journal readers
can delay writer/revocation commits. No concurrency or production-size timing
guarantee is added.

## Existing contracts remain

The older-profile identifier/local/Admin/raw-password check stays in its existing
branch. The native opener, PDO/identity paths, full artifact backup/verification,
headers, stream and exact-owned cleanup are unchanged. No secret source is
copied through a new mechanism and no key or restore operation is included.

HEAD still checks only the ordinary refreshed guard/captured V5 model and emits
no source open, artifact, length generation or body. It does **not** guarantee a
later GET will pass current same-snapshot authorization or that V5 Runtime is
operational. GET performs the complete native snapshot check. Existing physical
and public V5 gates remain closed, so this is no public export-enable switch.

## Coarse evidence and boundary

One disposable journey uses real private synthetic SQLite files and actual
`SQLite3` READONLY/`BEGIN DEFERRED` handles, not supplied/mock actor rows. It
checks inactive/stale-reactivated and role/source/password-drift authority before
artifact allocation, then authorizes fresh authority on the real snapshot and
exercises the unchanged artifact backup, fixed headers, stream and cleanup.
Source bytes remain unchanged and test bytes are neither published nor returned
as a user artifact. A separate branch of that same journey exercises the full
existing V4 Runtime export/facade and source-free HEAD behavior. V5 public Runtime
calls still produce their original pre-open refusal.

The V5 check+artifact component is intentionally exercised directly under the
test-owned native transaction; this is **not** execution or activation of the
still-blocked V5 Runtime wrapper. No Runtime hold is bypassed. Actual V5 physical
opener/admission, full HTTP/login/session/CSRF/browser, concurrent revocation,
crash/restore/production-size and human-recovery acceptance remain unproved.
Only clearly invented fixture users/hash and test-owned temporary files are used;
no operating source, credential, key, LDAP/device secret or account is accessed.

Before reconsidering the Runtime hold, finish the remaining complete V5
terminal-Admin, profile/provisioning, shared-login-budget/public composition,
Native-boundary and recovery gates and perform real operational acceptance.
This packet supplies no publication or real secret-bearing export authorization.
See the [combined source status](account-lifecycle-v5-core-integration.md) for the
working components and exact remaining operating-admission boundaries.
