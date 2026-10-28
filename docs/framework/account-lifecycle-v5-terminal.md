# Dormant V5 terminal account authority slice

Base source: Web-generation tree `63f5a704c49e011080f5da06043b80c5f3397d3a`.
This is **not V5 readiness, runtime activation, a Native boundary, or device QA**.
`RuntimeDatabase` still refuses V5 before opening a source. No profile/default,
route, setup, existing database, secret loader, device or key is changed. Native
`lib/terminal_core.php` and the V2/V3/V4 terminal branch are byte-preserved.

The separate private `AccountLifecycleTerminalWriterOperations` trait extends
only a directly composed V5 writer, behind the still-closed runtime admission:

* Pair and heartbeat retain device-only authorization and the existing single
  writer lifecycle; they cannot carry account identity or issue account grants.
* Identify consumes the authenticated nonce and device/person limits outside the
  grant transaction, including unsuccessful attempts. One credential/user JOIN
  captures explicit active/integer-generation authority before PIN verification.
* Successful PIN verification is not a grant. The sole existing IMMEDIATE lock
  rechecks enabled module/device generation, enabled credential/site/version,
  account active status and the captured generation. Only then is the explicit
  `account_generation` grant INSERT performed. Response/status is returned only
  after the existing writer confirms COMMIT. No private PIN or root is retained
  in the intermediate capsule or response.
* Event, replay and receipt recheck current active account and strict integer
  grant generation in the same lock as the unchanged credential/device/expiry/
  payload checks in the existing core. Reactivation cannot revive an old grant,
  including an already-used grant's replay or historical receipt. Receipt remains
  read-only evidence and never becomes replay-or-create. Inactive accounts'
  historical rows are retained, not deleted or hidden from separately authorized
  history readers.

The new Main `bbb5d055` enrollment method `createTerminalDevicePairing` remains
V5-blocked by `executeLocked` until its authenticated Admin facade is separately
bound to captured account generation. This slice does not authorize that route.
Other Web/API/enrollment/sync/Native enforcement and recovery/rollback acceptance
must be completed and independently reviewed before any V5 activation.

A support-only invented component journey checks signed requests, failed PIN
limits, grant/event/replay/receipt, deactivation/reactivation non-revival, a
prepared-identity/transition/finalize handoff, and independent device/credential
revocation. It is not HTTP/browser, real-key/device, concurrent, injected COMMIT
failure, restore, human recovery or all-channel acceptance. No new CI is run.
