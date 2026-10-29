# #18 — Keep the Native own-record list on an existing page

Source basis: Main `8ef8afac86ae166a31084dafe4459ced35859c1c`, tree
`3a5d377953a794b4e46dc84f2793b4c6013bc47f`.

The existing `review=1` list already clamps an out-of-range page. The ordinary
All view did not: stale page numbers displayed an empty list even with existing
own rows, while a PHP-maximum page overflowed the calculated SQL offset and
caused a SQLite datatype-mismatch exception in an anonymous in-memory probe.
This is a bounded source/runtime reproduction, not a claim about a production
incident or complete browser acceptance.

The ordinary view now counts its already-required full own context first,
clamps the requested page to `1..max(1, ceil(count/10))` in the same existing
read transaction, then executes the identical own-user, actual-instant/id-ordered
ten-row query with that safe offset. No extra query, alternate owner or smaller
diagnostic scope is introduced. An empty own list uses page 1; invalid/nonpositive
input retains the previous fallback. Review filtering still classifies the
complete own context before its unchanged pagination.

Both modes still use the full same-snapshot day/conflict context, preserving
overnight, NULL/open, invalid and ambiguous legacy-time handling. Display,
formulas, grouping, CSV, target correction links, authentication/roles and the
client's selected/confirmed-view race handling are unchanged. This is not a
query/memory optimization, complete historical data-quality proof, or stronger
auth/concurrency guarantee; the existing full historical reads remain.

The reused Native list journey runs only the actual post-bootstrap source
against invented read-only Memory rows, including a foreign row. It checks
normal-page parity, stale/oversized page fallback, unchanged review mode, empty
lists, full context and source immutability. Syntax/source evidence does not
prove real bootstrap/login/HTTP/browser/layout or production behavior. No new
repository test suite, workflow, runtime/security switch, dependency or deployment.
#18 remains open for its broader browser/state acceptance.
