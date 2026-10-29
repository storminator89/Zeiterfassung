# NEW V5 session locks: no lease expiry, explicit stopped recovery

This is a small local-Fresh V5 composition change, not a new server/lock service,
process killer, authentication mechanism or general deployment acceptance. The
locked Laravel FileStore/FileLock is reused. V4's positive lease and existing
whole-request-bound requirement remain unchanged; defaults stay off.

## Enforced source contract

Select exactly `CHRONO_LARAVEL_SESSION_LOCK_SECONDS=0` for the admitted NEW V5
profile, plus a positive wait. FreshWeb freezes integer0 into the real Laravel
session configuration; positive V5 leases, noncanonical zero forms, booleans and nonpositive waits
refuse. Integer0 and the exact environment string`0` are the canonical inputs. V4 continues to reject zero. The registrar requires
exactly the stock FileStore for V5: its FileLock overrides `acquire` with atomic
FileStore `add` even for zero. A generic CacheLock zero fallback is not substituted.

For the locked dependency, zero maps to the FileStore non-expiring sentinel
`9999999999`, rather than current-time plus a short lease. It cannot normally
expire while the older request is alive. This relies on the pinned supported
runtime and normal trusted clock/filesystem assumptions, not a literal promise
past that sentinel or against hostile host/operator changes. A strict positive
wait remains; contenders do not steal, clear or extend another owner.

The request's current Fresh profile also rejects every per-route lease/wait
override before cookies/session work. Otherwise Laravel could replace global0
with a positive route lease. Current source routes use the selected global
policy; unknown profile/route drift stays fail-closed with a generic response.
Existing setup/token/identity, source/Writer, CSRF/auth and generation checks are
unchanged. A lock error is not a DB rollback/commit conclusion.

## Deliberate policy switch, not a rolling mixed lease

Before changing a running V5 image/configuration from a positive lease to zero,
stop and drain every user of that store and exclude restart during the switch.
Do not mix old positive-lease workers with new zero-lease workers: the new
configuration cannot change an already executing old request. This is an
operator shutdown prerequisite, not something detected by the source or proved
by a flag. Default/first NEW preparation starts no workers in the first place.

## Worker exit and recovery

This is a persistent owner lease, **not a process-held OS flock**. Normal
StartSession releases it in its existing `finally` after the session is saved.
A worker exit that skips release leaves it blocked; there is no automatic TTL
recovery. That fail-closed availability cost is intentional and must not be hidden.
No timer/healthcheck/request may force-release it.

Only an operator who has stopped and drained **all users of that same store**
and excluded restart may clear its leftover locks. The narrow
`--clear-stopped-cache-locks --stopped` web-entrypoint mode preserves every prior
preflight and then invokes the original `cache:clear file --locks` under the
exact Fresh application context. It never starts the web server or bypasses
admission to guess a private path. The ordinary operator-only default-off profile
is not a substitute for that selected store.

`--stopped` is an assertion, **not proof** of a drain. `stop web` is not discovery
of all API/CLI/other workers. `--locks` clears **ALL** cache locks in the selected
store; do not use it with a live/uncertain owner or during a restart race. Normal
cache clearing is separate, and no session/account/generation/key/setup/DB reset
is performed. Keep the service stopped if any profile or cleanup step fails.
Original uncertain events still need their exact stable receipt/ID, never a
replacement event or assumed rollback. See the concrete
[operator recovery sequence](installation-docker.md#v5-worker-exit-lock-recovery--explicit-stopped-and-isolated).

## Bounded evidence and remaining operating work

Credential-free component probes on the locked dependency reproduce positive-
lease overlap, zero-lease exclusivity under a future test clock, atomic acquisition
across four competing PHP processes, bounded waiter failure and owner-checked
release. A known-exited fixture worker leaves a zero lease; the original cache-
clear command with `file --locks` restores that stopped fixture while preserving
ordinary cache and a public dummy file. Normal cache clear does not unlock it.
These are not the actual project's App/Fresh admission, browser, SQLite/Writer,
secret/account or Docker/deployment acceptance; fixtures were deleted. Clock
simulation does not alter the host clock.

The real chosen deployment still needs trusted HTTPS/private setup handoff,
correct single-host filesystem/UID/mounts, and a rehearsed shutdown/restart-
excluded recovery procedure. No new production or complete crash/disaster-
recovery claim follows. The separate #57 4xx uncertain-pending gap is unchanged;
clearing cache locks does not solve it or authorize automatic replay.
