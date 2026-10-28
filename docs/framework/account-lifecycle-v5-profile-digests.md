# Dormant V5 complete profile-digest binding

This source-only prerequisite is based on the exact Main `e954a448` tree
`5f31f4a35d8e17f803a5c0fbc684afd3e0ef0672`. It does **not** admit or
activate V5, prepare an instance, provision a key/token/account, mount a route,
change a host/security setting or touch an operating database.

The existing pure database-profile digests in `Api/ExistingApiKeyAdmission`,
`Setup/FreshLdapEncryptionKeyFiles` and `Ldap/LdapSyncFileSources` must bind the
complete explicit V5 signature chain. The historical projection included the
final V5, V2 and V3 signatures, but omitted the V4 predecessor
`intake_policy_schema_signature`. Two syntactically accepted V5 manifests that
differ only in that predecessor therefore had the same projected digest despite
different exact four-row migration-ledger expectations. This is an omission in
the projection, **not** a SHA-256 cryptographic collision or evidence of an
operating-system/key compromise. The existing V5 pre-open runtime refusal means
this is not a claim that a deployed V5 key-admission bypass was exercised.

Only for exact `schema_profile=account-lifecycle-v5`, all three projectors now
append `intake_policy_schema_signature` before hashing the same canonical JSON.
V2/V3/V4 receive no extra null field: their version, field order, encoding and
digest bytes remain unchanged. The metadata envelope version remains1; the
explicit profile distinguishes the V5 projection. Old experimental V5 digests
without this field must not be adopted as a fallback; regenerate such metadata
only through a separately reviewed and authorized future preparation flow.

The bounded synthetic evidence uses the canonical anonymous-memory V5 reference
signatures and the real twelve-field `RuntimeAdmission` parser, with invented
unopened path/device/inode metadata. It reproduces the old projection collision,
checks that the three corrected projections agree and distinguish the V4
predecessor, preserves V2/V3/V4 historical values, and verifies the original V5
read/writer/snapshot refusal before physical open. No physical DB/key/metadata
file is opened by this journey. This is not filesystem/HTTP/browser/CSRF,
concurrency, restore, Native-cutover or operational recovery acceptance.

## Remaining runtime-admission work

The [combined source status](account-lifecycle-v5-core-integration.md) records
which generation-bound business/enrollment/Admin/snapshot components now work;
the following operating boundaries remain separate from this metadata fix.

- `FreshApplicationFiles`/`PrepareFreshApplication` still produce only V4.
- First-admin setup and independent app/API/LDAP key preparation, FreshWeb,
  LDAP-sync, shared-login-budget and API composition retain their V4-only gates.
- Route/intake-policy capability checks must eventually select an explicit
  complete V5 contract, never alias V5 to `isIntakePolicyV4()`.
- All account-bearing writers, cross-channel generation enforcement, local
  recovery and a separately reviewed Native-V5 refusal must be coherent before
  the `RuntimeDatabase::requireImplementedRuntime()` hold can be reconsidered.

Keep existing V2/V3/V4 profiles and UI unchanged. Do not remove a runtime hold,
broaden an existing profile predicate or create a second DB opener to make
NEW-only V5 preparation appear runtime-ready.
