# #54 — generic recorded team-absence week in the existing Laravel UI

This additive read feature extends the existing `supervisor.php` page, not the
Native page, deployment, schema or V5 runtime. Source basis: Main `83717e8e`,
exact tree `c177eba798eef8e77f23a0500eb843da6c78dfa6`. It is a partial #54
contribution, not issue closure or a complete staffing/leave approval system.

## Visible and data-minimizing contract

The existing authorized supervisor page now shows Monday–Sunday for a selected
Berlin week, with independent previous/current/next-week and roster navigation.
Each cell says only **Absent** or **—**. The marker means a stored positive,
completed absence-credit day, not a live presence observation, leave request,
substitution, approval, sickness diagnosis or promise that an unmarked person is
available. Zero/cancelled credits do not become absence days. Holidays and ordinary
work rows are not absence markers. The ordinary historical report and its existing
contents are unchanged; this packet does not claim to remove health information
from that older report.

`team_date` selects only the week, using the existing calendar helper and its
safe invalid-date fallback. `team_page` selects at most50 people at a time,
ordered by username/ID; malformed pages use1 and excessive pages clamp to the last
page. These controls do not alter the independent historical report's filter/page.
Both sets of navigation preserve the other's selection. No JavaScript, new asset,
route switch, optional secret, external feed or new authentication mechanism is
introduced.

## Authorization and source handling

The new fixed `ChronoReads::teamAbsenceWeek()` delegates to the existing admitted
named read snapshot. The current actor is reloaded in that same snapshot; only
Admin or Supervisor may proceed. The roster and absence query both retain the
existing **Admin or current direct-team** predicate. There is no implicit own-row
exception, arbitrary person selector, ancestor team, stale-session role or cached
roster authorization. A reassigned member leaves the next snapshot's plan.

Only username, seven dates and boolean markers leave the reader for each person;
no user/record IDs, raw timestamps, notes, diagnosis/absence-kind strings, passwords,
source metadata or credentials enter the new planning DTO. SQL distinguishes the
existing explicit vacation/sickness kinds and their legacy description fallback,
but exposes neither kind. Blade escapes usernames and all display values.

The existing strict persisted timestamp parser determines the actual instant and
Berlin date; text-prefix filtering and server/browser timezone are not used.
Numeric casts make SQLite's no-affinity UDF comparison independent of PDO's
string parameter binding. Local-midnight week endpoints preserve seven dates
across both DST changes, not an assumed168-hour window.

At most1001 candidate rows are materialized for the selected roster; only the
first1000 are processed. An extra row sets a visible truncation warning. LIMIT
bounds returned/materialized rows, not SQLite scan or timestamp-UDF work; the
query may evaluate more stored rows. An unreadable stored absence timestamp for a
selected person, or an open/invalid candidate, marks the view incomplete rather
than fabricating a day. Unknown-date candidates may conservatively warn even when
their actual week cannot be established. Empty cells are never availability
proof. This bounded projection does not repair or reconcile source rows.

The existing page remains `Cache-Control: no-store`. No PDO/file/opener capability
escapes, no writer is resolved and no database mutation is performed by this read.
V5 identity handling inherits the existing snapshot gate; this feature does not
open V5, alias it to V4 or broaden any Runtime/Setup/API/Native capability.

## Bounded evidence and remaining work

One coarse invented in-memory journey runs the actual provider, reader, controller
and Blade with a synthetic guard and a public test-only app key, never operating
accounts or keys. It covers current direct-team/Admin scope, role/team changes,
57-person50-row paging, oversized/malformed arguments, Berlin offset/DST dates,
zero/invalid/truncated input, generic DTO and HTML field allowlisting, escaping,
navigation and unchanged database change count. Compilation/syntax, exact source
preservation and independent source review are separate checks.

This is not real HTTP/browser/session/CSRF, multiworker, source admission, physical
keys/files, Native-page, deployment or recovery acceptance. No external calendar
feed, substitution/approval rules, leave entitlement, pending application, capacity
calculation or legal working-time policy is supplied. Those require separately
specified contracts and scope. The existing report may still do unbounded history
work; this packet makes no whole-page performance or completeness guarantee.
