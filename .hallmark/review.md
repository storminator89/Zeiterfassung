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

## Today and form refinement

- One shared clock/account workspace; metadata shown directly before clocking in. Bookings are visible by default.
- Native absence/reports task dialogs with Escape, explicit cancel, ordinary URL fallback, and restored task focus. CSS/JS URLs carry content hashes so prior browser caches cannot keep old interaction behavior.
- Absence form uses one compact choice row, dates, reason and footer; explanatory text is contextual.
- Correction editor follows booking selection with the two time inputs. Unique valid Berlin times copy to the existing POST fields as the local input changes; gaps and unresolved repeated hours clear stale submitted values. Repeated hours still require an explicit occurrence selection. No submit/storage/network action is triggered by choosing a time.
- Persons have four columns and a separate contextual action menu; lifecycle, LDAP and guarded deletion controls retain their original contracts.
- Browser checks: native dialog open/cancel/Escape, readonly persons menu, automatic 30 Oct 2026 +01:00 conversion, 29 Mar 2026 gap refusal, 25 Oct 2026 repeated-hour explicit selection. No requests, absences or account changes submitted.
- Updated the render check to keep the accessible balance assertion while removing the obsolete Home accordion-class requirement. Authenticated page checks still pass.

## Calendar details repair

The browser retained the pre-dialog calendar script: clicking filled old fields but only set `modal-open` on a closed native dialog. The calendar URL now has a content hash, and its handler opens the native task dialog. The own-scoped snapshot supplies the existing ledger presentation for duration, pause and note; no new query, booking or accounting formula was added. Non-booking calendar entries have no correction link. Verified by real browser click, Enter, close and Escape on two distinct records; authenticated render checks passed.

## Calendar height regression

FullCalendar sets an inline `height:100%` on its root. Without an explicitly sized parent this overrode the previous root-only CSS height and collapsed the timed grid to zero pixels, even while event nodes remained in the DOM. Added a dedicated fixed-height wrapper: 600 px desktop, 520 px mobile. Actual browser measurements confirmed 0 px before and 600/520 px after, with three positive-height event targets. Clicked a visible event, checked its details, closed it, and verified the calendar remained 600 px high. The screenshot now includes the timed grid and events, not just the modal. No booking values changed.
