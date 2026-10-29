# #60/#79 — Own stored open-entry facts in Laravel

Source basis: Main `9619529c2a93b82b470ab6b029e631310e588d9d`, tree
`ea34d80365c445c2f06069f0e778973cf10e9413`.

The Native attendance page already offers a bounded own open-entry snapshot.
Laravel now reuses that exact unchanged `chrono_own_open_review_snapshot` helper,
not another diagnostic, threshold, reminder or booking implementation. An
optional `OwnOpenReviewReads` capability delegates inside the existing canonical
`LegacyChronoReads` snapshot, taking the actor only from its current authorized
account. The stable `ChronoReads` interface, providers/routes, identity/source/
generation checks, Runtime gates and all writers remain unchanged.

COUNT and at most five rows share the same fixed owner/open/start predicate
and the same read transaction. Admin and Supervisor still see only themselves.
One server Berlin clock supplies the date boundary and snapshot label. Old
entries outside the recent 30 can appear; invalid starts remain unknown and are
not rendered raw. Limits/count are not a scan optimization or completeness proof.
Only ID strings and formatted valid starts/unknown markers leave the helper;
no notes, location, health type, names, password/version or generation are added.

The small existing-style attendance card preserves the exact neutral Native
DE/EN/zh text: **a regular overnight shift may appear**, not evidence of forgotten
work or a rule violation. It invents no end/pause and offers no mass correction,
automatic closure, alarm, mutation control or automatic replay. Links use the
existing own correction selector; noncanonical/zero/negative/out-of-range IDs
do not become correction links or a new-entry target. Navigation is an explicit
ordinary GET, not a form submission or automatic reload.

Zero matches do not produce an “all clear” assertion. A technical failure is
shown as unavailable, never empty/clean; authentication/authorization failures
retain the protected response. Optional components without the capability show
no extra card. The HTML view alone gets separate bounded projection variables;
the shared AttendancePage DTO, header quality warning, homeSummary, status JSON,
attendance POST and all existing client hooks remain unchanged. Other existing
page reads are not claimed to be the same atomic snapshot as this card.

The #16 invalid-quality-warning direct-link slice is coordinated but separate:
it changes homeSummary, one AttendancePage argument and the layout warning, not
this card/helper. Later reanchors must compose both independently and preserve
the existing model-preview interface/method and any Calendar fallback labels.

Coarse actual-helper/read-adapter and isolated controller/compiled-Blade evidence
uses invented read-only Memory rows and fake command/auth transport. It does not
prove real bootstrap/login/session/CSRF/HTTP/browser, concurrent changes, complete
data quality or operating acceptance. No credentials are generated or production
data used. V5 Runtime stays closed; no new test suite, workflow, dependency,
host/security switch or deployment. #60 remains open for broader operating policy.
