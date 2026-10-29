# #53 — Existing CSV date-range form follows the selected UI language

Source basis: actual Main `79a31ae9d6cde340598a70bd65691273a41424ad`, tree
`49d9a54ecf9f79c722e5d39be8967e2639c27e03`, after PR #353.

The existing own-record Native and Laravel CSV date-range form previously kept
all five texts in German even when the existing UI language was English or
Chinese. Both now use their existing language dictionaries for the title, two
field labels, submit button and explanation. The five values are identical
between the Native constants and Laravel Copy dictionary in each language.
Native uses the existing `h()` and Blade its normal escaped interpolation.

The German texts and rendered German forms are byte-identical. All existing
markup, closed-details state, field names, required/date bounds, GET action,
new-context attributes and existing display gates remain unchanged. The
explanation in every language retains own-only Berlin start-day selection,
whole overnight shifts, full-own-snapshot quality checks that can block a
range for errors outside it, and no payroll-format promise.

No query parsing, reader, controller, SQL, date/absence/sum/CSV calculation,
serializer, CSV bytes or filename, download/error behavior, identity, security,
writer, session, runtime, host, dependency or route changes. Existing endpoint
errors and other unrelated hardcoded legacy text are not translated here. This
is a small usability fix, not the team/payroll schema or complete #53 acceptance.

## Coarse evidence and limits

PHP 8.5.12 / Laravel 13.38.0: one isolated render-only journey, repeated for the
three existing languages, evaluates the actual extracted Native form fragment
with the actual Native language file and compiles/evaluates the actual Blade
partial with its existing Copy DTO and matching Composerlock dependencies.
It verifies all five labels agree, occur once, are valid UTF-8, existing form
structure/attributes stay exact, the German output stays byte-identical and no
session or timezone changes occur. Relevant PHP/compiled-Blade syntax and full
source-tree preservation are checked. No new repository tests, workflow or
manual CI dispatch.

No app/bootstrap, authentication/authorization/CSRF, database, HTTP response,
real download, browser/visual/layout/locale-switch or production acceptance is
claimed. No real accounts, credentials, keys or operational data are used, no
server is started and no deployment occurs. #53 remains partial/open.
