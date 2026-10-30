# Supervisor detail-table period — partial #53

Base Main `4ac3ee1aa68a25b2acaa826f09b119f4ae0551e3`, tree
`01ae72ef661ef475c6909e193fc54a46af9c754d`. No payroll/recipient schema or export
is added, and #53 remains open.

The existing supervisor detail-table filter gains an explicit period checkbox
and inclusive Berlin **start-day** From/Through dates, with DE/EN/ZH labels.
Only the detail table changes: lifetime metrics, cards, charts, carry-in and the
independent current team roster/week plan remain unchanged and visibly labelled
as such. This is not a period balance or an accounting/approval decision.

The unchanged `lib/csv_period.php` raw `period=1&from=YYYY-MM-DD&to=YYYY-MM-DD`
parser and Berlin start-day selector are reused, not a second date algorithm.
Without a marker, old date/search queries keep their prior meaning. Malformed,
duplicate, array-shaped, impossible or reversed marked ranges refuse with no
detail rows and GET 400; raw request values cannot be repaired by normalized
Laravel bags. Period markers beyond `max_input_vars` are not silently lost.
Unchecking the period in a fresh form submission or choosing the fixed period
reset link explicitly restores the default table. The reset also remains
available when a capability/source refusal hides the table/form; it preserves
name/language/team-week selection and resets table paging. Table pages, name searches and team-week forms/links preserve an active
range or fixed invalid marker; raw rejected input is not reflected.

Current authorization and the complete current source are read as before.
Invalid times and work/whole-day-absence conflicts are diagnosed **before** date
selection; neither an outside-range error nor a name/page filter can create a
clean partial report. With such findings, Native retains its original scoped raw
diagnostic view for a valid range and explicitly says the period is not applied;
Laravel retains its original report refusal. Invalid ranges show no detail rows.

For a clean report, all original independent rollups run on the complete source.
Only then are table rows selected by Berlin start day, grouped, name-filtered and
paged by the existing ten-group rule. Split-day groups stay whole; overnight and
DST rows are neither clipped nor split, and stored credit/work values remain
unchanged. The optional `TeamReportPeriodReads` port delegates to the same snapshot
and projection. Missing capability refuses the opt-in with empty GET 503 rather
than showing an unfiltered table. General `ChronoReads`, old default contract,
SQL scope/order, parsers, writers, schema and all runtime/host settings stay intact.
Native parses only this definitions-only selector before its existing header so
an invalid-filter status can be set before output; auth/bootstrap code is not moved.

## Evidence and limits

One PHP8.5.12 / SQLite3.46.1 / Laravel13.38.0 anonymous Memory journey uses actual
old/new projections/readers/controllers, existing compiled DE/EN/ZH Blade and
extracted actual Native scoped SQL/group/search/page code with its configured
Berlin timezone. Default report parity, table reduction, unchanged complete
rollups, current direct-team/Admin scopes, ordinary-role refusal, page/search,
midnight/night/DST/open/modern and legacy credits, raw invalid input, optional
capability refusal and distant invalid/conflicting rows are covered. Deliberate
fixture seeding/phase changes are separate from reads; each read preserves
source/schema/audit/change counters, query-only/transaction cleanup and native
globals. Necessary PHP/compiled-Blade syntax and diff checks pass.

Identity is an isolated double, not auth acceptance. No full Native bootstrap,
auth/HTTP/CSRF/browser/visual/production/export/payroll acceptance, actual data,
accounts or credentials, new repository suite/manual CI/GitHub Approval, new
dependency/schema/runtime/security/host setting or deployment. Held/denied packets
are untouched. Source-only integration is not a productive rollout.
