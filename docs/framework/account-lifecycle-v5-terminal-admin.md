# V5 captured authority for existing terminal Administration

Source anchor: published Main `091f843b99524cc5b31098b2bb7d80afa6a1126e`,
exact tree `ac4bbd4976f950ad6b657adbfa523a3cb45b9004` after PR236.
This packet completes generation binding for the five existing terminal-Admin
component operations. It neither opens V5 Runtime nor mounts a route, changes a
host/module flag, provisions a key or enables a terminal installation.

## Existing operations, existing single writer lock

The site, employee-credential revoke, device revoke, module toggle and device
pairing facades receive the already existing immutable
`AccountLifecycleWebIdentity` from the normal lazy service provider. Only the
trusted refreshed local Admin model supplies identity, role, password version
and generation. Capture occurs before resolving the operation writer; request
fields still select only the fixed business operation and allowed target.

A trailing optional authority preserves older-profile call contracts. The site
writer reuses the existing daily-Admin guard; the four password-bound methods
pass the authority to their existing principal guard. Under the sole existing
IMMEDIATE lock, these guards check complete captured identity/source/role/
password/status/generation and locally login-eligible administration **before**
any terminal DML, replacement device generation, pairing entropy or module gate.
Being active again never substitutes for the captured generation.

Missing V5 authority stays closed before BEGIN. V5 authority supplied to an
older profile is rejected. Writer401 follows the existing rollback, then the
facade maps it to normal AuthenticationException; the site's older-profile
error handling is unchanged. No second transaction manager, callback, opener,
schema, default flag, controller, view or native/protocol path is added.

The unchanged native business constraints remain:

- trimmed, bounded and unique site names;
- credential disable only, with no PIN/card/hash or grant rewrite;
- device disable plus replacement generation and cleared pairing state;
- server permission and open-work checks for optional module state;
- an enabled module and existing site for new-device pairing, with its existing
  ten-minute validity and one-time, nonserializable confirmed result.

The separately prepared employee-credential-enrollment feature is not added by
this packet. Any later additional operation needs its own reviewed V5 authority
composition before the global hold may be reconsidered.

## Bounded evidence

One disposable synthetic journey resolves the actual providers/facades and
persists all five V5 operations, including module disable/enable and confirmed
pairing-result consumption. For each operation it deactivates/reactivates the
Admin between capture and writer resolution: all five stale commands refuse
with no additional business or audit mutation. Inactive/nonadmins resolve no
writer; a fresh generation works. All five missing-authority V5 calls and all
five older-profile calls carrying foreign V5 authority refuse. All five normal
V4 facades still work. The unmodified source fails on its first V5 site write.

The existing global Runtime refusal remains unchanged. Only invented accounts,
hashes and disposable sources are used; a test-owned process environment value
is restored after the journey. No operating data/credential/key/directory,
remote action or new CI run is involved. This is not actual login/session/
HTTP/CSRF/browser/device, physical Runtime, concurrent-worker, COMMIT-failure,
crash/restore or human recovery acceptance.

See the [combined source status](account-lifecycle-v5-core-integration.md) for
remaining provisioning, HTTP/UI, Native-boundary and operating gates. Issue30
remains open; this is a necessary component dependency, not deployment approval.
