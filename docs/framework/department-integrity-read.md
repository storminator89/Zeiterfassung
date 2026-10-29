# #34: explicit read-only department-reference inventory

Source basis: Main `ec6315a2e60f420b9b4b9db7e2a5681654ab9cd6`, tree
`4e2c122a0fd3ca563d625893b9c86ca21b8c3637`. This is a bounded diagnostic in the
existing Laravel Admin user-management card, not a migration or new deletion
policy. Existing per-row reference warnings, user search/paging and ordinary
business administration remain unchanged.

## Functional difference

The previous warnings concern only the current ten-row user search/page.
An explicitly requested GET now examines department references of **all**
users in one current, authenticated read-only snapshot. The normal Admin GET
does not perform this additional scan. A collapsed existing-style details panel
contains the explicit start/reload link; no background request or auto-retry.

The existing `admin_reference_status` helper distinguishes resolved references,
explicitly unassigned NULL, missing numeric IDs and invalid/unknown references.
NULL is not presented as an orphan. A resolved department with an empty name
remains resolved. No new interpretation of department membership is introduced.

Full counts continue while at most25 problem rows are returned, ordered by
user ID. A canonical positive-ID cursor provides subsequent pages. Each page
is a **new** snapshot, not a frozen multi-request export; concurrent changes
can alter the sequence, so restart the check for a new complete view. Counts
and problem rows within one result share the same snapshot. Problem references whose user primary key is nonpositive are counted separately
and visibly marked for an offline check; the existing positive-ID editor and
cursor are not widened or pointed at those rows. The diagnostic
is independent of the separately read ordinary user table, not a claim that
the whole Admin page is atomically rendered.

User IDs and valid missing department IDs are emitted as exact decimal strings,
including IDs beyond JavaScript's safe integer range. Malformed raw assignment
text, credentials, hashes and tokens are not projected. Existing escaped user
names identify the affected row. The existing `data-read-user` modal action
opens the original details; it is not a new write, mapping confirmation or
preselected replacement. All mutation/role/last-recovery protections remain.

## Authority and failure boundaries

Only a current **local Admin** may obtain this user-data inventory. Directory
Admins retain their existing business administration but receive neither the
panel nor its data. A manually supplied diagnostic query is refused before
the diagnostic call. The canonical Reader rechecks current source/role and
the existing provider/password/V5-generation authority in the read snapshot.
The stable `ChronoReads` interface, providers, routes and writers are unchanged;
an optional narrow read capability is used.

The helper requires an already-active query-only transaction. It opens no
database, application, transaction, file or network source. SQL selects only
user ID/name/department reference and its joined department ID. The statement
is closed in a finally block. Output is bounded but scan time is not: every
explicit page scans the source population; no production performance claim.

Malformed query/cursor data gives a fixed400. Lost authentication/authorization
retains the protected response. Other failures or an absent optional reader
produce an explicit unavailable state, never zero/healthy counts or raw
diagnostics. POST error rendering does not trigger this GET-only scan.

## Evidence and remaining issue requirements

One coarse PHP8.5.12 / Laravel13.38.0 / SQLite3.46.1 Memory journey exercises
the actual helper, canonical Reader, AdminController, DTO and compiled whole
Blade with43 invented rows. It finds37 issues, including two unaddressable nonpositive
user-ID rows, and lists the remaining35 across25/10 pages, including a
large exact ID; global counts survive a one-row ordinary search. NULL and empty
department names, malformed/zero/negative references, fixed query errors,
escaping, missing raw/credential fields, default-no-scan, current role/source
refusal, LDAP document exclusion, unavailable-not-healthy and unchanged source
rows/schema/query-only state are checked. No new repository test suite.

The journey supplies a fake RequestGuard and named Memory connection, not an
actual login, SessionGuard/provider/V5 runtime admission, HTTP/browser/visual,
concurrency, real-data, migration or operating acceptance. The unchanged
production authority composition is assessed by source review, not claimed
as newly executed by this fixture. Browser acceptance remains user-owned.

Remaining #34 work: explicit confirmation of real legacy mappings and any
versioned migration/FK activation plan. The existing refuse-if-assigned
department deletion rule is unchanged. This inventory does not select successor
departments, traverse supervisor chains, repair data, activate foreign keys,
prove semantic correctness or attest an entire installation as migration-ready.
No real account, credential, database file, installation or deployment is made.
