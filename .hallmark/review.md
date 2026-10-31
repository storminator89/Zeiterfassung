# Chrono redesign, 31 October 2026

## Direction

Ready template: official Tabler 1.7.1 (MIT), vendored locally with SHA256 provenance.
Reference: https://docs.tabler.io/ui/components/card and https://docs.tabler.io/ui/forms/elements.
Existing DaisyUI and main.css imports were replaced in the application shell, as requested.
All routes, form field names, handlers, validation and accounting remain in place.

## Historical comparison

Compared repository commit `54c0adb` (31 March 2025) with `260ad79`.
The older header had Home, Dashboard, Settings, Admin, Supervisor, About and Logout.
Its core task was direct Kommen/Gehen, a compact statistics view and a bookings table.
The modern workflows are retained, but daily work is separated from administration and diagnostics.

## Composition

- One horizontal task header, collapsed to an accessible menu on small screens.
- Today: clock controls, compact balance panel, then the ledger.
- Overview: three period figures and a calendar with a single toolbar.
- Vacation: one request action, bounded form, aligned filters and request list.
- Corrections: request list, contextual form, active filter disclosure.
- Work model: current schedule, then creation and version history.
- Team: status and actual times, with optional analyses.
- Settings: ordinary preferences, profile and security controls.
- Administration: stable section tabs, tables and contextual forms.
- Terminals: module switch, devices and access management, optional setup forms.
- Authentication/setup: the same Tabler form template and local assets.

## Copy changes

| Before | After |
| --- | --- |
| Home | Heute |
| Dashboard | Übersicht |
| Änderungshistorie (navigation) | Verlauf |
| Übersicht Vorgesetzte | Team |
| Admin | Verwaltung |
| Fremde Anträge prüfen | Zur Freigabe |
| LOGIN | Anmelden |
| Erfasster Saldo | Saldo (full accessible name retained) |

## Verification

Static Hallmark check: 0 FAIL, 0 REFLEX, 0 WARN on authored CSS and tokens.
The bundled render tier could not run because puppeteer-core is unavailable.
Actual authenticated browser inspection uses the existing browser tooling, not a simulated HTML mock.
All 34 final Blade templates passed compilation and PHP syntax parsing. Read-only render checks cover ten page/fragment routes and all five admin sections. Actual desktop/mobile inspection covered the expanded ledger, API setup, model creation and history details; the calendar was also inspected in dark mode. Existing booking and terminal controls remain intact. No production bookings, API activation or LDAP actions were submitted during visual QA.

## Final refinements

- Bookings: one compact day row, responsive interval list, pencil/history actions; no nested table headings.
- Models: real weekly total, seven-day visual plan, readable hours, model metadata behind details.
- History: human activity labels; audit rows with the same explicit stamping-event UUID and actor share a heading within the loaded page. Every original row and its comparison remains inside the activity details; pagination, filters and exports are unchanged. No heuristic time-based grouping.
- System: bounded contextual API and holiday forms, adjacent checkbox labels, separate diagnostics.
- Three original transparent illustrations generated with the built-in image tool; paths, prompts and SHA256 are recorded in assets/illustrations/provenance.json.
