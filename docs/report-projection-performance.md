# #56 — one checked parse pass for report-day projections

Bounded CPU optimization against published Main
`2d801bba605732724806ae75a9bd630dcb799fbd`, tree
`0e83cd7d71d2f55ae05a9a0a026deed5ed2a8fb3`.

`report_days` previously parsed each source row once for day amounts and again
when building work/whole-day-absence conflict events. It now builds the same
conflict events from the already computed result in the first pass, then uses
the same extracted sweep as the standalone `report_absence_conflicts` caller.
When the completed sweep already found a conflict, non-opted-in callers throw
the same exception without recomputing the whole conflict scan a second time.

There is no retained calculation, static/request cache, SQL rewrite, new
connection/transaction, date truncation, authorization or UI change. Complete
already-authorized source rows are still required before paging/date filtering.
Output row order, half-open interval order, separate users, invalid/open rows,
Berlin/DST handling, source-start-day attribution and diagnostics opt-in remain
unchanged. Invalid/conflicted data is not silently converted to valid zeroes.

This does not duplicate PR #237: its caller-owned Home planning snapshot and
`planned_minutes_snapshot` are unchanged, as are the existing Native per-day SQL
path and every planning formula. No Runtime/V5 admission is opened.

## Rehearsal

Run with PHP 8.5 from a source checkout; no framework bootstrap or database is
loaded, and only invented arrays are generated:

```sh
php framework/tools/rehearse-report-projection.php "$PWD" 10000 days
php framework/tools/rehearse-report-projection.php "$PWD" 10000 conflicts
php framework/tools/rehearse-report-projection.php "$PWD" 10000 period
```

For before/after comparison, run the same tool with its first argument pointing
to a separate exact baseline source tree. Compare `result_sha256` as well as the
source digest, timing samples and additional PHP-heap peak. The tool accepts
1–20,000 synthetic rows and takes seven samples per invocation. It checks that
input rows, default timezone and repeated results stay unchanged. Its zero SQL
count refers only to this direct projection, not a whole application request.

One local seven-sample run measured these `days` medians (milliseconds):

| Invented rows | Baseline | Candidate | Additional heap peak |
| ---: | ---: | ---: | ---: |
| 100 | 1.86 | 0.84 | about 0.1 MB for both |
| 1,000 | 18.06 | 8.59 | about 0.9 MB for both |
| 10,000 | 152.35 | 110.31 | about 9.9 MB for both |

An independent alternating 10,000-row rehearsal measured 161.44 to 94.16 ms;
standalone conflict projection was comparable (91.71 versus 88.18 ms). These are noisy local
CPU/heap observations, **not production latency, response-time or load claims**.
Disposable instrumented source copies confirmed 200 to 100 checked-calculation
calls for 100 valid rows with both diagnostic outputs; application source was
not instrumented. Returned-result digests matched before/after.

The original candidate's independent differential checks compared 1,421 invented fixture sets in six
modes (8,526 comparisons), including diagnostic output references and exception
classes/messages, modern/legacy absence, DST/night work, invalid/open values,
multiple users, duplicate IDs, associative indices and inside/outside periods.
All results matched byte-for-byte. PHP syntax and whitespace checks passed.

## Remaining scope

#56 remains open: full-history loading, SQL/query-count reduction, indexed date
ranges, whole-page memory/response measurements and production-scale validation
are not supplied here. There is no browser, authentication, operating-data,
concurrent-write, production or full V5 acceptance. No remote action, CI run,
deployment or runtime/security setting was changed for this rehearsal.

## Current-Main reanchor and bounded recheck

The existing reviewed code and CLI tool were copied without functional change
onto Main `2d801bba` after #31/#50/#55. Both functional files remain byte-identical to
the earlier independently reviewed candidate. Only this document's source basis
and clarification of historical evidence changed. The current recheck uses
coarse synthetic before/after projection and timing journeys, not another
fine-grained fixture battery. No remote mutation is part of this reanchor.
Composition against the actual merged #55 data-review slice was checked with
its existing invented-memory actual-list renderer: default and filtered pages,
page clamping and invalid selection produce byte-identical HTML and diagnostics
before/after this report-only change. #55's complete own-context scan, filter
before pagination, correction links and client/public-asset bytes stay unchanged.
This is no protected HTTP/browser or complete data-quality acceptance.
