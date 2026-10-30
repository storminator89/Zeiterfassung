# Native Terminal error notice (#79)

The existing Native Terminal page redirects a confirmed administrative save to
`terminals.php?saved=1`. Its forms post back to the current URL. Before this
change a later failed/unconfirmed save could retain that query marker and show
both the error and `Änderung gespeichert`.

The success banner now also requires the existing error string to be empty.
Nothing is retried or inferred from the query marker. Error text/escaping and the
previous no-error display remain unchanged. The marker remains display metadata,
not an authoritative receipt or proof of device state.

This is only the Native counterpart of the Laravel display fix in PR #374. The
POST dispatcher, authorization/CSRF, SQL/writers/transactions, module/host gates,
site/device/credential operations, enrollment code/session handling, generation,
journal, routes, schema, runtime and UI structure/styles are unchanged. The
separately blocked device-journal #32 delta is not included or retried; #32
remains open.

## Bounded evidence and limits

PHP8.5.12: 32 evaluations of the extracted actual Native error/success alert
fragment cover GET/POST, absent/present/empty/non-scalar saved-marker presence,
and no-error/refused/unconfirmed/HTML-bearing error text. Eighteen baseline
contradictory success banners disappear. All eight no-error controls retain
byte-identical output; errors remain escaped. This is a display-fragment test,
not execution of any administrative command or a real positive POST journey.

PHP syntax and diffcheck pass; no repository tests, workflow or manual CI
dispatch is added. No bootstrap/auth/CSRF/DB/writer/HTTP/browser/visual/device/
hardware/credential/runtime/deployment acceptance is claimed. No real operation,
account, credential, key, host setting or operating instance is involved.
