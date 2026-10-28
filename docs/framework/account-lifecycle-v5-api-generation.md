# #30 — dormant V5 API generation and actor-write gate

Basis: independently reviewed dormant V5 Web-generation union tree
`63f5a704c49e011080f5da06043b80c5f3397d3a`, anchored on Main `d907c15d`.
**V5 runtime and API activation remain CLOSED; #30 and full #77 remain OPEN.**
No route, key, schema, Native channel, installation flag or runtime opener is
changed. The separate V4 NEW-key/operator package is not widened to V5.

## Explicit token-profile boundary

The pure `AccountLifecycleApiIdentity` is selected lazily from immutable
`RuntimeDatabase::isAccountLifecycleV5()` metadata. Construction never opens
the source or resolves keys/accounts. Optional model columns and token claims
cannot select V5. Actual API provider composition supplies this helper to login,
bearer authentication and the attendance facade; older constructor callers keep
their current defaults.
The actual provider helper pins the selected RuntimeDatabase object (including
an initially absent binding) and rechecks that identity on use. A later binding
change fails technically instead of retaining an early non-V5 decision. Manual
nullable constructors remain old isolated callers, not certified V5 composition.

Only explicit V5 changes the credential/token contract:

- Local API login retains raw usernames, its single existing shared-budget
  consumption, stock Eloquent/hasher validation, no LDAP/session/rehash and the
  existing invalid-credentials response. Before hash/signing work it also requires
  a current active V5 account snapshot with a positive integer generation.
- A successful V5 login adds integer `auth_generation` from that trusted model
  to the existing HS256 claims. No request/session-selected actor or generation
  is copied into a token. Signing uses the existing admitted source/package.
- Bearer authentication first performs unchanged maintained JWT verification
  and current account/password-version binding, then requires an active current
  row and an exact positive integer claim equal to its generation. Missing,
  null, string, malformed and stale generations are refused with the existing
  controlled401. No first-use adoption of old tokens or claim repair exists.

Consequently deactivate/reactivate advances the account generation and an old
token does not revive. Fresh credential login can issue the new generation.
This is a deliberately new, still-dormant V5 token profile: previously issued
generation-less tokens are NOT imported into V5. V2/V3/V4 retain their existing
claims and acceptance semantics; there is no universal legacy-token equivalence
claim, downgraded-profile fallback or new cryptographic implementation. Existing
`nbf` differences and broader token/source-transition acceptance stay separate.
Generation advances on status transitions, not ordinary role/source edits. An
otherwise valid token subsequently receives the current role/source; only an
in-flight changed tuple is rejected by the writer capture. This slice does not
claim every historical role/source edit revokes a bearer token.

## Actor check before writes AND receipt replay

`LegacyApiAttendance` still requires the dedicated verified bearer RequestGuard,
never a Web identity. In V5 it captures existing `AccountLifecycleAuthority`
from that trusted model before resolving its operation writer. Neither booking
payload nor JWT extras become an authority object. The fixed new
`AttendanceWriter::transitionApi` operation accepts no target/source parameter:
user and `api:{id}` namespace derive from the captured authority.

Inside the existing single IMMEDIATE lifecycle, the fixed operation rechecks
current active status, generation, ID, source, role and password fingerprint
against the same captured actor BEFORE calling the unchanged attendance domain.
Thus an in-flight deactivate/reactivate, deletion or changed actor tuple refuses
before any existing receipt is replayed or a new booking is written. The same
old operation namespace, event/payload hashes, expected-record behavior, audit
actor/reason, domain validation, response, COMMIT/rollback and no-retry lifecycle
remain. A newly authenticated current-generation actor may still replay their
own retained receipt; changing its namespace would risk duplicate writes.

Only this fixed operation opts into `lifecycleReady`. The generic old transition
still refuses V5 before BEGIN, and V5 authority cannot target an older writer.
No public SQL callback, second transaction manager, additional commit, automatic
retry or caller-controlled lifecycle-ready flag is exposed. Every other closed
V5 mutation/terminal/Native path remains closed. Existing regular V5 read-snapshot
actor checks from the predecessor slice remain unchanged; this is not a new
database-export or full API-read/wire acceptance claim.

## Bounded evidence and remaining gates

One coarse invented component journey uses synthetic accounts/password hashes,
an explicitly public test-only JWT key, actual maintained JWT classes, anonymous
canonical fixtures and disposable SQLite files. It is not an operating account,
original key/source, real runtime-admission or HTTP/client test. It covers V5
issuance/verification, strict missing/stale-generation refusal, deactivate/reactivate
non-revival, in-flight same-lock refusal before new DML and receipt replay, fresh
generation attendance/retry and older V4 token behavior. Source syntax and exact
incremental mapping/preservation are separate evidence.
It also exercises actual provider autowiring and refusal of an early off helper
after a later RuntimeDatabase binding, plus in-flight role-change denial followed
by current-role refresh. Its login-budget adapter is explicitly fake; it is not
a shared SQLite quota/429 acceptance test.

No real keys/data/directory, production DB, HTTP/browser/extension, TLS, GitHub
write/new CI or runtime activation is performed. Real multiworker revocation/
COMMIT-failure/restore/identity-reuse behavior, remaining writers/terminal grants/
enrollment/Native gates, operator/client acceptance and serial final-Main union
review remain required. No claim of immediate cancellation of already committed
work, complete V5 readiness, old-key adoption or issue closure follows.
