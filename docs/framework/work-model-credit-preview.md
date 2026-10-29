# #61/#79 — Existing read-only model-credit preview in Laravel

Source basis: Main `ddddbd851028d4b3d5bddeb399f1c51769b7e3c5`, tree
`f9e4217e093e337b0398d56416e5b4c6bada6039`.

The Native model form already has a bounded hypothetical credit comparison.
The Laravel model form lacked it. This slice delegates to that exact unchanged
`lib/work_model_credit_preview.php` helper; it introduces no new formula,
recalculation, ledger, stored draft, correction or model writer.

## Explicit read-only user flow

An optional `WorkModelCreditPreviewReads` capability is implemented by the
existing `LegacyChronoReads`. The stable `ChronoReads` interface and all providers,
routes, Runtime/registration gates and feature flags remain unchanged. Existing
components without the optional capability do not show a Preview button and an
explicit preview request cannot fall through to a model write.

The existing local Admin form gains the Native-style **without saving** action.
The preview profile requires deliberate person selection; blank, missing or
unavailable targets do not silently become the first person. Exact bounded
display values, including reason and seven weekday values, are escaped and kept
only in that read response. History links explicitly use a new tab and
`noopener noreferrer`; a fragment returns to the form without loading a page.
There is no automatic replay. Legacy unmarked POST remains Save, explicit Save
uses the exact old command path, and an unknown explicit action never writes.
Old `saved=1` query text is not presented as success on any POST/preview error.

The existing helper limits output to 100 stored absence rows in the candidate
model section, marks truncation/unplaceable data, and compares **hypothetical
daily target minus retained net credit**, not an account balance. Stored
holidays retain their existing zero-target rule without a completeness claim.
NULL/open/invalid rows remain unknown, not zero. Existing credits and models
are neither recalculated nor stored by Preview.

## Existing authority and snapshot, not a new admission

The facade captures only the authenticated local Admin ID and the existing
guard-loaded private password version. Both remain internal. The unchanged
canonical read snapshot still reloads actor/role/source and checks its captured
V5 generation or existing-account version before invoking the helper. The
snapshot actor must match the captured actor and still be local Admin; the
Native helper independently checks the current same-snapshot password version.
No request actor/role/version, raw PDO, password, fingerprint or generation is
returned, flashed, logged or rendered. No account-model password field is added
to a projection. A stale version fails closed as unauthenticated.

The result leaves only after that existing read transaction returns. A failed
read is not an empty/clean comparison. The writer is never called by Preview;
its unknown outcomes are not reclassified as confirmed rollback. Existing Save
commands and error handling otherwise remain, without retaining their inputs
or claiming a successful write. The V5 Runtime hold remains closed.

## Evidence limits

Coarse functional evidence uses invented read-only Memory rows, the actual
read adapter/helper, and an isolated controller/DTO/compiled-Blade journey with
fake command/auth transport. It is not successful public Runtime admission,
real login/session/CSRF/HTTP/browser, concurrency/crash/restore or production
acceptance. No credentials are generated or real account data used. No new
repository test suite, workflow, dependency, host/security switch or deployment.
The separate Laravel rejected-review draft remains open: the existing writer
does not expose a confirmed rollback result, so it was not implemented by
guessing from an exception type. PR #239 and its denial remain untouched.
