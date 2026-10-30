# Stored weekly-minute presentation (#55 / #61)

The existing Native and Laravel work-model list, selected-version detail and
current-effective detail now share a presentation-only reader. Previously a
stored scalar/malformed JSON value could break the Laravel page, and coercion or
iteration order could show unsupported values as apparently ordinary weekday
minutes. This is a stored-data display defect, not a claim that the current save
form produces those values.

A supported display value has exactly seven Monday-to-Sunday, nonnegative plain
JSON number lexemes from 0 through 1440. All-zero decimal fractions emitted by
the initial model trigger (for example 480.0) remain supported. The grammar is
aligned with the existing own-work-model API; that API is unchanged. Fractions,
exponents, strings, booleans, nulls, objects, missing/extra weekdays, negatives and
out-of-range values are not guessed or coerced. A presentation input above 64KiB
is also unavailable. If the complete seven-day representation is unsupported,
all seven cells say `Nicht auswertbar`; unavailable does not mean zero.

The list/detail metadata and existing model choice, scope, paging, effective-date
precedence and links are unchanged. There is no model write, repair, accounting,
absence-credit, target/balance, API, schema, migration, route, authorization,
CSRF, runtime or host change. Other calculations can still have their previous
limitations on unsupported stored model data; this display guard is not a
validation or migration certificate and does not close #55 or #61.

## Bounded evidence

PHP8.5.12 / Laravel13.38.0 components. One external coarse journey uses the actual
Laravel controller/DTO, actual compiled Blade list/detail fragment and extracted
actual Native list/detail renderer with fabricated already-authorized row ports.
Four supported representations retain their original output. Eighteen
unsupported representations now preserve the table shape with seven unavailable
cells per list/selected/effective row instead of exceptions or coercion. Escaping
and exact IDs above 2^53 are covered. A separate synthetic in-memory table
compares the unchanged own API against the display reader: three supported and
21 unsupported representations agree, plus seven display-only type/size
refusals. No new repository test or workflow/manual
CI dispatch is added.

This is not full App/bootstrap/DB admission/auth/CSRF/HTTP/browser/visual/runtime
or production acceptance. No real accounts, credentials, keys, file databases,
server settings, device actions or deployment are involved.
