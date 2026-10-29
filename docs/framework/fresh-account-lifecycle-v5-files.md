# Dormant NEW-only V5 application files

**Preparation only, not runtime/setup admission.** This narrow source packet
adds a fixed V5 branch to the existing NEW-only private file producer. The
ordinary `chrono:prepare-fresh --new-only --private-parent=...` command and
`FreshApplicationFiles::createNew()` still produce V4 with the same receipt
shape. Only the additional explicit `--dormant-account-lifecycle-v5` flag, or
the fixed internal CLI-oriented `createNewDormantV5()` entry point, selects V5.
There is no free-form schema/fixture, existing target, import/reset/resume,
account, key, credential or activation argument.

The source basis is exact Main `e954a448` tree
`5f31f4a35d8e17f803a5c0fbc684afd3e0ef0672`. A local snapshot commit is not a
new remote Main or publication claim. No installation command, real data/key
operation, GitHub write or CI dispatch is authorized by this document.

## The existing file boundary stays authoritative

The existing canonical private0700 parent must already exist outside the
checkout and webroots, owned by the current Unix CLI operator. Complete trusted
ancestor ownership/sticky-directory rules, exclusively allocated random0700
instance and0600 single-link database, held-file device/inode/owner/mode guards,
no-CREATE writer open, no overwrite, same-UID trust assumptions and cautious
own-cohort cleanup remain those of [the original producer](fresh-application-files.md).
Unknown/replaced objects or sidecars stop cleanup; there is no automatic retry,
recovery, chmod, ownership transfer, old-file deletion or migration.

The V5 branch builds only the fixed canonical anonymous-memory
`account_lifecycle_v5.php` source using `AccountLifecycleV5References`. The new
empty file receives its four exact lineage rows through the existing
`FreshAccountLifecycleV5Installation` and the held NEW-only identity guard under
its one IMMEDIATE transaction. All five application-version rows, empty users
and login budget, initial manual-on/LDAP-off/registration-off policy and exact
canonical V5 definitions must match. No historical account is backfilled, no
status/generation is inferred and no real source file is copied.

After all materialization/ledger handles close, the existing **offline**
`OfflineFiles::openReadOnly()` reopens only this producer's own held NEW identity.
This is not a new generic opener or a RuntimeDatabase capability. Its unchanged
cold rollback-journal/no-sidecar/private-file/READONLY/no-CREATE rules remain.
A private void helper verifies the complete canonical fresh V5 source, physical
empty login budget, exact four-row ledger and sequence, actual schema cookie,
recorded FK/journal/synchronous settings, query_only and busy_timeout; it repeats
the held identity guard. Every PDO/statement is released before the following
cold-file snapshot comparison. No business reader/writer escapes this helper.

Only then is `runtime-admission.json` written last/exclusively/fsynced with the
exact twelve-field V5 manifest and four distinct V2/V3/V4/V5 signatures. The
fixed sessions/cache/locks/compiled directories are empty. The V5 receipt adds
`dormant=true`, `runtime_openable=false`, `setup_provisionable=false`; activation,
credential creation and server-start flags stay false. The filename describes
future operator metadata, **not** an accepted Runtime capability. File fsync is
not a power-loss/COMMIT/recovery or directory-durability certification.

## Setup and runtime remain unavailable

`RuntimeDatabase::requireImplementedRuntime()` remains unchanged: V5 reads,
writers and snapshots refuse before source open. This packet changes none of
the existing runtime/composition/provisioning gates; public V5 admission remains
unavailable. Independently composed slices may understand V5 internally, but no
web/API/LDAP/shared-budget, first-admin or key-provisioning capability is enabled
by this file producer.
The producer does not call any key/token/setup provisioner or create a user.
It must not be used as a reason to weaken those gates or call V5 V4-compatible.
Do not point Native PHP at the generated V5 file; the separate Native boundary
and known-good Laravel/local-Admin recovery gates are not supplied here.

Before a usable V5 installation, independently review and compose all channels'
current-active/generation/locked-writer authority, complete V5 profile and
digest propagation, exact setup/key preparation and setup closure, safe local
recovery, Native-V5 refusal and real operational admission. The complete-profile
digest fix must be included before future V5 key metadata is provisioned; do not
reuse an experimental digest that omitted the V4 predecessor signature.

The next setup packet should explicitly select the V5 fresh-reference checker
while preserving complete manifest/instance binding and empty-state checks.
It must not produce usable credentials while the coherent runtime/recovery
admission gate remains unresolved. No synthetic token, autologin, hidden user or
second authentication path is a substitute for that gate.

## Bounded evidence

One coarse disposable private-tempfile journey prepares ordinary V4 and dormant
V5 cohorts, verifies their exact manifests/lineage/canonical empty state and
ownership/modes, preserves a preexisting parent sentinel, refuses an aliased
parent before allocation, and confirms the original V5 read/writer/snapshot
refusal against the actual new file. Test-owned files are then removed. No
setup/key provisioner is called; no account or credential is generated.

Syntax/diff and exact composition checks are separate. This evidence does not
establish CLI application boot, HTTP/browser/CSRF, actual setup/login, directory,
cross-UID/adversarial races, multiworker/concurrency, crash/power loss, restore,
Native cutover, host/TLS/security configuration or operational readiness.
