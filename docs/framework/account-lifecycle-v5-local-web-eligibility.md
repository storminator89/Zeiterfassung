# V5 local Web requires an account path, not just an open reader

Source anchor: Main `af43dc2af1989751341104ae4cf6b908b4cc89a6`, tree
`e13c4c31bca0ca6c251df6f49cb5ff3136827bc9` after PR275/276/277. This is a further
fail-closed prerequisite, **not V5 Runtime activation or human recovery proof**.

FreshWeb's local-only V5 metadata accepts an explicitly disabled setup route.
Previously the V5 registrar only probed an admitted reader and disconnected it.
Even if the eventual complete Runtime gate passed, an empty installation or a
populated source without a login-eligible local Admin could therefore mount a
login/Admin surface with no usable local administrative account path. A setup
flag alone cannot repair a populated source: the existing first-admin writer
deliberately requires zero users under its sole lock.

## Actual fixed admission predicate

The existing V5-only check at the very start of `LocalWebRoutes::register` still
calls the unchanged `RuntimeDatabase::readConnection` first. Its original V5 hold
therefore still refuses before the new predicate, PDO work or registrar effects.
There is no alternate opener, callback, override flag or new Runtime entrypoint.
V4 and default/off routes receive no additional read or changed lifetime.

Only after that existing admission succeeds, `LocalWebRecoveryEligibility`
requires an already-resolved named `chrono` PDO SQLite reader, `query_only=1`,
`DEFERRED` transaction mode and no existing transaction. It owns one single-attempt read snapshot and then
disconnects on success or failure. The same raw handle is retained; no reader is
upgraded, writer resolved, key/token/password generated or account modified.

Within that snapshot exactly two states are acceptable:

| Current source and route selection | Result |
| --- | --- |
| At least one active local Admin satisfies the existing `AccountLifecycleAuthority::locallyLoginEligibleAdmin` predicate | Ordinary local Web may continue, regardless of whether the setup route is selected. |
| No users at all, and the caller separately selected the existing first-admin setup route | Initial setup may continue to its unchanged trusted configuration/token and locked zero-user checks. |
| Empty without setup, or any populated source without an eligible local Admin | Fixed failure before route/registrar effects. Selecting setup does not bypass this refusal. |

Only candidate local Admin rows are projected privately; hashes/rows/handles do
not leave the operation. The unchanged predicate checks current active status,
integer generation, local source/Admin role, trimmed nonempty username and a
supported stored password hash that is not the forbidden empty/default password.
It proves **syntactic eligibility only**. It does not show that an operator knows
the password, that login actually works, or that backup/restore is recoverable.
The existing fixed failure does not expose SQL, paths, hash values or a previous
driver exception. Any uncertain snapshot/cleanup outcome refuses; no retry.

## Preserved boundaries

The entire Runtime implementation and its V5 pre-open hold remain unchanged.
The PR275 scoped lifecycle facade is still reached only after the registrar
returns successfully; this adds no binding or route. Existing setup-token digest,
expiry, closure marker, first-admin validation and same-lock zero-user behavior
remain authoritative. Nothing closes/reopens setup, creates an Admin, resets a
password, restores data or interprets a directory account as local recovery.

This is a point-in-time route prerequisite, not a lock held for later requests.
Current login/session and same-lock writer authority checks remain essential.
Complete cross-channel/physical Runtime admission, operating setup/key policy,
Native renamed-file/WAL/cutover compatibility, real local-Admin password knowledge,
HTTP/session/browser/concurrency/COMMIT-loss/crash/restore and human recovery
acceptance remain open. Do not remove the Runtime hold based on this predicate.

## Bounded evidence

PHP8.5.12/Laravel13.38.0 component checks use anonymous-memory row-shaped fixtures
and the real new predicate/snapshot lifecycle. They cover empty setup versus
empty ordinary mode; populated non-Admin/directory/inactive/departed/malformed
local candidates; eligible and later eligible local Admin candidates; fixed
failures, unchanged rows, closed snapshots/readers and no lazy PDO resolution.
A fixed public pre-existing example hash is reused only as a structural row
value: no hash/key/token generator, authentication, account provisioner or
operating credential is used. This is predicate-component evidence, **not** an
admitted V5 file, successful Runtime opener, login or human recovery journey.

Separate actual standard-provider boot checks on new credential-free cohorts
preserve V4 routes/budget; held local V5 still hits the exact original refusal
with no Web routes, lifecycle binding, DB-manager connection or writer resolution.
Missing budget and LDAP/Sync/API selections remain refused. Current source
autoload and the shared installed lock identity are checked; all cohort DB and
manifest bytes remain unchanged. Existing public App-key format metadata is only
format-checked, not generated, encrypted or read from a secret file. PHP lint,
exact other-source preservation and complete patch inverse/replay are separate
checks. No remote action or manual GitHub-CI run is part of this local evidence.
