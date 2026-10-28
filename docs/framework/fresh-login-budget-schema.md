# NEW-only budget-ready V4 source

This source slice prepares no actual installation. It changes only the fixed
NEW anonymous source and its explicit creator checks. It does not run the CLI,
create a real database (even memory during evidence), generate/read keys, create
accounts, enable a route, bind a budget or activate Web/API/runtime. Reviewed
[A4](login-budget-writer.md) remains unbound; Web/API adapters are separate.

## Physical table before admission

The fixed `fixtures/intake_policy_v4.php` build retains its MemoryOnly guard and
complete existing V2/V3/V4 initialization. AFTER that build, it executes exactly
the existing trusted source inventory's canonical `login_attempts` CREATE SQL.
The target is the newly constructed empty anonymous source, never an old file
or runtime connection. No `security.php`, native bootstrap or secret helper is
included. Empty counters are prepared before VACUUM INTO, initial ledger,
actual schema-cookie capture and final admission manifest.

`IntakePolicyV4References::requireFreshLoginBudget(PDO)` first calls the unchanged
strict `requireFresh`: canonical structural preflight compares a PRESENT table's
columns/indexes/objects against the known reference and rejects schema drift.
It then requires exactly one physical main schema object named login_attempts
of type table, and no rows. An absent table is NOT accepted merely because the
historical schema signature normalizes absence; views, duplicate objects, rows,
query or cursor-close failures refuse the NEW source. No cast, repair, data copy,
table creation or DML occurs in this checker. Its two own statements have checked
closeCursor and are nulled in their method scope, with no returned handle/closure.

The NEW file creator selects this stricter method on:
1. the fixed anonymous source BEFORE materialization;
2. its just-created private file AFTER ledger registration, BEFORE manifest data;
3. the final admitted read-only target inside its existing read/rollback scope.

Existing registration, no-reconnect rules, identity guards, READWRITE-without-
CREATE target opening, exclusive allocation/0700/0600/nlink1, trusted ancestry,
reader/connection disconnect+null and cold-snapshot ordering remain unchanged.
No target statement/reader may be retained across that closed-file boundary.
The creator's runtime-admission.json remains exactly11keys and is written LAST,
exclusively; its actual cookie and PRAGMAs are read after all NEW schema+ledger
creation. Private runtime directories and nonsecret false-activation receipt
are unchanged. No old/no-table cookie or metadata is reused or rewritten.

## Preserve lineage, not an upgrade

V2/V3 source builders, generic `requireFresh`, `V2Preflight`, RuntimeAdmission,
ledger naming/projection, mapping JSON and application versions are untouched.
No V5/profile identity or fourth ledger row is introduced. Canonical references
already execute the SAME mapped login_attempts CREATE SQL, and preflight already
normalizes that table into signatures when absent. The V4 reference now reaches
that same physical CREATE once in its fixture; the existing later IF NOT EXISTS
statement is a no-op. Its final structure and three ledger identities should
therefore stay identical, not be silently recomputed as a new lineage. Evidence
is source/SQL-order mapping, NOT execution of SQLite or calculated real digests.
Real producer/reference/cookie acceptance requires its own later authorization.

Generic absent-vs-known-empty compatibility remains intentional for older V4
callers, and all V2/V3 behavior stays exact. The NEW budget-ready creator alone
requires physical presence+known structure+zero rows. No existing file gets DDL,
counter reset, migration, adoption retry, cookie repair or manifest/fingerprint
update. Existing instances need a separately authorized cold transition. AppKey/
Setup-token compatibility with a truly empty known table does not authorize any
actual key/token provisioning here.

## Evidence and remaining gates

Only syntax/static exact-source projections and one coarse invented checker-seam
model are supplied. The added strict method body can be exercised unchanged with
an explicitly stubbed preexisting requireFresh structural-admission precondition;
this does NOT run or certify the complete reference/preflight/creator pipeline.
No PDO connection, SQLite engine (even memory), real schema, table, transaction,
VACUUM, physical file, credential, key, request, CLI/app boot, server or CI run.

Before any binding, independently review B4 and the exact later Main+Core+A4+B4
union, preserve newer LDAP/Admin/Setup/AppKey/Token source, then review the common
Web/API caller/error/normalization/namespace transition. A4 must still refuse
missing/corrupt/cookie-drifted state; a source checker or signature alone is not
runtime provenance or a working shared budget. O(countercount), actual lock/
commit/worker/HTTPS/client acceptance, #197/native/session29/30/operational and
publication gates remain separate.
