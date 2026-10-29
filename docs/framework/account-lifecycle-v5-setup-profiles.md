# Dormant V5 setup profile prerequisites — no provisioning admission

**Not V5 Runtime, setup, key or FreshWeb activation.** Source basis is Main
`cc04dc5ec33c689ab582a6797f1b511d1e47da9f`, tree
`eb5aba1d41ee6609bd43e4ac796cc6da02632660`, after PR263's prepared source cohort.
This provisioner component changes four existing private NEW-only provisioners, not a new setup
workflow, source opener, wrapper, configuration switch or credential generator.
No remote action or permission to generate credentials is supplied by this doc.

## Concrete missing prerequisite

Even after a later complete Runtime admission, the existing provisioners would
reject V5 metadata and/or run the V4 canonical fresh-state checker on a V5 file.
This packet fixes those two specific source prerequisites in:

- `Setup/FirstAdminSetupFiles.php`;
- `Setup/FreshApplicationKeyFiles.php`;
- `Setup/FreshApiSigningKeyFiles.php`;
- `Setup/FreshLdapEncryptionKeyFiles.php`.

Only the explicit immutable manifest profiles `intake-policy-v4` and
`account-lifecycle-v5` are recognized. The same fixed canonical instance path
`<instance>/application.sqlite` is still mandatory; no free-form version,
request/profile flag, V4 alias or inferred status is added. V2/V3/unknown/partial
profiles remain refused. All existing manifest parsing/identity checks remain.

The same existing `RuntimeDatabase::readConnection()` remains the sole admitted
reader. **Its original V5 hold is unchanged and still refuses before opening a
PDO.** Only after that reader could legitimately return in a separately reviewed
future operating profile does V5 select the existing
`AccountLifecycleV5References::build($mapping)->requireFreshLoginBudget($pdo)`.
That verifies canonical empty V5 state, default policy and a physically present,
canonical empty login-budget table. It is not the permissive absent/empty
signature normalization and not merely an empty `users` count. The original V4
reference call and all other V4 behavior are retained, including the API
provisioner's existing strict physical-budget check.

The exact original private-directory/ancestor/owner/mode/link checks, fixed
inventory, canonical metadata and file-identity binding, stopped-only contract,
operator locks, setup closure/unstarted checks, source snapshots, cursor/PDO
lifetimes/cleanup, exclusive NEW-file reservations, entropy/write/fsync ordering,
partial-file rules, generic errors/receipts and no-retry behavior remain intact.
No key/digest/token/profile is read back or exposed by a new path. Removing the
three intentional profile/reference additions (and LDAP comment clarification)
reconstructs each entire original PHP source byte-for-byte.

## What is still refused, and what may happen first

Ordinary V5 provisioner attempts are still unsuccessful. Before the Runtime hold,
existing private-path and metadata checks, an existing zero-byte operator lock
where applicable, and the stopped cold-file snapshot may run. Therefore this is
**not** a claim of no file access or no lock creation. No V5 token/key/profile
artifact is reserved/generated and no account is created by these held calls.
The closure operation remains its original monotone revoke-only behavior.

This provisioner component does not change Runtime/FreshWeb/LDAP/shared-budget/public route selectors, setup/key CLI
signatures, provider registration, schema/admission, Native/HOLD_COMPATIBILITY,
all existing business writers or the unbound Admin lifecycle control. The combined source candidate also contains
the separately reviewed [local FreshWeb prerequisite](account-lifecycle-v5-freshweb-local.md);
its original Runtime barrier remains authoritative. Broader LDAP/API-capable
FreshWeb profile selection is deliberately deferred: it changes
configuration overrides, provider order, private stores, LDAP/budget and public
capabilities together and must not be reduced to replacing an `isV4()` check.

This is a source prerequisite, not authorization to issue credentials, mount a
V5 Web application or remove Runtime holds. Before that, prove the entire
coherent operating profile, known-good replacement/local-Admin recovery and
separately authorized NEW setup/token/app/API/LDAP-key sequence with closure.
Only then reconsider source-level activation and real operating acceptance.

## Credential-free bounded evidence

PHP8.5.12/Laravel13.38.0: one coarse journey creates only new disposable private
credential-free V4/V5 cohorts using the existing file producer. Each of the four
actual V5 provisioner entrypoints refuses, source/manifest bytes stay unchanged,
and no token/key/profile artifact exists. The only extra entry is the original
persistent zero-byte operator lock where applicable. All three public V5 Runtime
entrypoints independently retain their exact pre-open refusal.

For V4, the fixture deliberately changes the noncredential `terminal_settings`
seed from its canonical initial state. Empty users/audit remain empty; the
existing Runtime reader and fresh-state checks then refuse all four provisioners
before reservations/entropy. This checks safe negative behavior, **not** a
successful V4 provisioning/secret-write journey. No credentials, token/key or
account are generated, and no successful provisioning path is invoked.

Separately, the already existing V5 destination validator accepts its canonical
anonymous-memory fixture and rejects a changed initial seed or missing physical
budget table. This is **not execution of the new V5 provisioner branch**, which
remains unreachable behind Runtime. No reflection/private-method invocation,
rewritten product source, injected opener, test bypass or weakened hold is used.
All owned disposable files/handles are removed; the initial expected-absence
`lstat` probes in the harness were made warning-safe, without product changes.

Four PHP syntax checks, exact full-source/inverse preservation and cold complete
transport checks are separate evidence. Positive V4/V5 secret generation/writing,
CLI successful provisioning, real setup/login/HTTP/browser/directory,
concurrency/crash/COMMIT-loss/restore and human recovery are not tested or claimed.
There is no new repository test battery, manual CI, GitHub write, host/security
change or deployment. #30/#73 and coherent operating admission remain open.
