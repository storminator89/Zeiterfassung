/* One server-authoritative attendance controller for the familiar header and
 * start page. No client timestamps or localStorage work-time calculations.
 */
(() => {
    'use strict';
    const ready = callback => document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', callback, {once: true}) : callback();
    ready(() => {
        // A second script tag must not register a second booking handler.
        if (['ready', 'blocked'].includes(document.documentElement.dataset.attendanceController)) return;
        const initial = document.querySelector('[data-attendance-state], #actions[data-state]');
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        const userId = Number(document.body?.dataset.attendanceUser);
        if (!initial || !token || !Number.isSafeInteger(userId) || userId < 1) return;
        document.documentElement.dataset.attendanceController = 'ready';

        const labels = {absent: 'Nicht angemeldet', working: 'Arbeitszeit läuft', paused: 'Pause läuft'};
        const allowed = {absent: ['start'], working: ['pause_start', 'end'], paused: ['pause_end', 'end']};
        const buttonSelector = '[data-attendance-action], #actions [data-action]';
        const pendingKey = 'chrono-attendance-pending';
        const recordString = value => typeof value === 'string' && /^[1-9][0-9]*$/.test(value);
        let state = initial.dataset.attendanceState || initial.dataset.state;
        if (!Object.hasOwn(allowed, state)) state = 'absent';
        let expectedRecord = state !== 'absent' && recordString(document.body.dataset.attendanceRecord)
            ? document.body.dataset.attendanceRecord : null;
        let busy = false, refreshing = null, revision = 0, pending = null;
        let pendingVerified = true;
        const actionOf = button => button.dataset.attendanceAction || button.dataset.action;
        const buttons = () => [...document.querySelectorAll(buttonSelector)];

        // Preserve this exact event for a safe retry after an interrupted request
        // or same-tab navigation. Reuse requires both the server-rendered account
        // ID and the CSRF token to match; login can retain an existing token.
        function blockUnverifiedPending() {
            pendingVerified = false;
            document.documentElement.dataset.attendanceController = 'blocked';
            buttons().forEach(button => { button.disabled = true; });
            message('Buchungen bleiben gesperrt: Ein vorhandener oder nicht prüfbarer Vorgang darf nicht überschrieben oder einer anderen Anmeldung zugeordnet werden. Bitte Browserspeicher und ursprüngliche Buchung klären; danach bewusst neu laden. Keine automatische Wiederholung.');
        }
        try {
            const raw = sessionStorage.getItem(pendingKey);
            const stored = raw === null ? null : JSON.parse(raw);
            if (raw !== null) {
                if (stored?.userId !== userId || stored.session !== token
                    || typeof stored.event?.event_id !== 'string' || !/^[A-Za-z0-9_-]{16,80}$/.test(stored.event.event_id)
                    || !['start', 'pause_start', 'pause_end', 'end'].includes(stored.event?.action)
                    || !['0', '1'].includes(stored.event?.confirm_end_pause)
                    || (Object.hasOwn(stored.event, 'expected_record') && !recordString(stored.event.expected_record))) {
                    blockUnverifiedPending();
                    // Preserve foreign/malformed values, never delete to unblock.
                } else {
                    pending = stored.event; // Legacy omission must replay unchanged, never bind to a new record.
                }
            }
        } catch (ignored) {
            // Even if storage becomes readable later, a new event must not
            // overwrite an older uncertain event that this document never saw.
            blockUnverifiedPending();
        }
        function rememberPending() {
            try {
                if (pending) {
                    const value = JSON.stringify({userId, session: token, event: pending});
                    sessionStorage.setItem(pendingKey, value);
                    // A successful in-page assignment is not proof that Reload
                    // can recover this exact event. Never POST without readback.
                    return sessionStorage.getItem(pendingKey) === value;
                }
                sessionStorage.removeItem(pendingKey);
                return true;
            } catch (ignored) { return false; }
        }
        async function request(url, options = {}) {
            const controller = new AbortController();
            const timeout = window.setTimeout(() => controller.abort(), 15000);
            try { return await fetch(url, {...options, signal: controller.signal}); }
            catch (error) {
                throw new Error(error.name === 'AbortError' ? 'Keine Antwort vom Server erhalten.' : 'Verbindung zum Server unterbrochen.');
            } finally { window.clearTimeout(timeout); }
        }
        async function readJson(response) {
            try { return await response.json(); }
            catch (error) { throw new Error('Keine gültige Serverantwort erhalten. Bitte neu laden und Anmeldung prüfen.'); }
        }
        function message(text) {
            document.querySelectorAll('#message, #attendance-message').forEach(element => { element.textContent = text; });
        }
        // Presentation only. Never restore remembered amounts after a booking,
        // failed refresh or manual correction: only a deliberate page navigation
        // can obtain fresh numeric summaries without discarding unsaved forms.
        const reportPeriods = ['all', 'week', 'month', 'year'];
        let reportFinality = null;
        let reportRefreshNeeded = false;
        const reportValues = () => [...document.querySelectorAll('[data-report-period]')];
        function reportNotice() {
            const notice = document.getElementById('report-finality-notice');
            const copy = document.querySelector('[data-report-finality-message]');
            if (!notice || !copy) return;
            const unresolved = reportFinality && reportPeriods.some(period => reportFinality[period]);
            const stale = reportRefreshNeeded || reportValues().some(element => element.dataset.reportPresentation === 'refresh-needed');
            if (unresolved) {
                copy.textContent = 'Klärung offen: Arbeitszeit und ganztägige Abwesenheit überschneiden sich. Betroffene Summen sind bis zur manuellen Vier-Augen-Klärung nicht abschließend. Originalzeiten bleiben unverändert.';
                notice.hidden = false;
            } else if (stale) {
                copy.textContent = 'Anzeigeaktualisierung nötig: Die markierten Summen sind nicht abschließend. Bitte ungespeicherte Eingaben zuerst sichern und die Seite bewusst neu laden. Eine frühere Buchungswarnung ist keine aktuelle Konfliktprüfung.';
                notice.hidden = false;
            } else if (reportFinality) { notice.hidden = true; }
        }
        function invalidateReportValues() {
            reportFinality = null;
            reportRefreshNeeded = true;
            reportValues().forEach(element => {
                element.dataset.reportPresentation = 'refresh-needed';
                element.textContent = 'Aktualisierung nötig';
                element.classList.remove('text-success', 'text-error');
            });
            reportNotice();
        }
        function applyReportFinality(value) {
            if (!value || !reportPeriods.every(period => typeof value[period] === 'boolean')) { invalidateReportValues(); return; }
            reportFinality = value;
            reportValues().forEach(element => {
                const period = element.dataset.reportPeriod;
                if (value[period]) {
                    element.dataset.reportPresentation = 'unresolved';
                    element.textContent = 'Klärung offen';
                    element.classList.remove('text-success', 'text-error');
                } else if (element.dataset.reportPresentation === 'unresolved') {
                    element.dataset.reportPresentation = 'refresh-needed';
                    element.textContent = 'Aktualisierung nötig';
                }
            });
            reportNotice();
        }
        function render(nextState = state, nextRecord = expectedRecord) {
            if (!pendingVerified) return; // An absence refresh must not unlock attendance.
            if (!Object.hasOwn(allowed, nextState)) throw new Error('Unbekannter Anwesenheitsstatus. Bitte Seite neu laden.');
            state = nextState;
            expectedRecord = state !== 'absent' && recordString(nextRecord) ? nextRecord : null;
            document.querySelectorAll('[data-attendance-state]').forEach(element => { element.dataset.attendanceState = state; });
            const actions = document.getElementById('actions');
            if (actions) actions.dataset.state = state;
            document.querySelectorAll('[data-attendance-status], #status').forEach(element => { element.textContent = labels[state]; });
            buttons().forEach(button => {
                const action = actionOf(button);
                // An uncertain event must be acknowledged with the same ID,
                // even if a later status snapshot already reflects its effect.
                button.hidden = pending ? action !== pending.action : !allowed[state].includes(action);
                button.disabled = busy || (!pending && action !== 'start' && expectedRecord === null);
                button.classList.toggle('loading', busy && action === pending?.action);
            });
        }
        function renderRecent(records) {
            const body = document.getElementById('recent-records');
            if (!body || !Array.isArray(records)) return;
            const fragment = document.createDocumentFragment();
            for (const record of records) {
                const row = document.createElement('tr');
                const values = [record.absence_kind || 'Arbeitszeit', record.absence_kind
                    ? `${String(record.startzeit).slice(0, 10)} · ${record.planned_minutes} Sollminuten` : record.startzeit,
                record.absence_kind ? 'Gutschrift' : record.endzeit || 'Läuft', record.pause];
                for (const value of values) {
                    const cell = document.createElement('td');
                    cell.textContent = value ?? '';
                    row.append(cell);
                }
                const cell = document.createElement('td'), link = document.createElement('a');
                link.href = `adjustments.php?record_id=${Number(record.id)}`;
                link.textContent = 'Beantragen';
                cell.append(link); row.append(cell); fragment.append(row);
            }
            body.replaceChildren(fragment);
        }
        async function refresh(includeTable = false) {
            if (busy) return;
            // Overlapping focus/pageshow events share one read. A mutation's
            // revision invalidates any older response still in flight.
            if (!refreshing) {
                const startedAt = revision;
                refreshing = (async () => {
                    const response = await request('attendance.php?format=status', {cache: 'no-store', redirect: 'error'});
                    if (!response.ok) throw new Error('Anzeige konnte nicht aktualisiert werden. Bitte Seite neu laden.');
                    const data = await readJson(response);
                    if (!data.success || !Object.hasOwn(allowed, data.state)
                        || (data.state === 'absent' ? data.expected_record !== null : !recordString(data.expected_record))) {
                        throw new Error('Anwesenheitsstatus und Sitzungsbindung konnten nicht geladen werden. Bitte neu laden.');
                    }
                    if (startedAt === revision && !busy) { render(data.state, data.expected_record); renderRecent(data.records); applyReportFinality(data.report_finality); }
                })().finally(() => { refreshing = null; });
            }
            await refreshing;
            if (includeTable && typeof window.updateTimeRecordsTable === 'function') {
                const updated = await window.updateTimeRecordsTable();
                if (!updated) throw new Error('Arbeitszeittabelle konnte nicht aktualisiert werden.');
            }
        }
        function refreshQuietly() {
            if (!document.hidden && !busy) void refresh().catch(error => { invalidateReportValues(); message(error.message); });
        }

        document.addEventListener('click', async event => {
            const button = event.target.closest(buttonSelector);
            if (!pendingVerified || !button || button.hidden || button.disabled || busy) return;
            event.preventDefault();
            const action = actionOf(button);
            if (pending && pending.action !== action) {
                message('Unbestätigte Buchung zuerst mit derselben Aktion erneut versuchen.');
                return;
            }
            if (!pending && !allowed[state].includes(action)) return;
            if (!pending && action !== 'start' && expectedRecord === null) {
                message('Sitzungsbindung noch nicht bestätigt. Bitte Status neu laden.');
                return;
            }
            const endPause = action === 'end' && state === 'paused';
            if (endPause && !pending && !window.confirm('Pause und Arbeitszeit gemeinsam beenden?')) return;
            if (!pending) {
                if (typeof crypto.randomUUID !== 'function') {
                    message('Sichere Ereignis-ID nicht verfügbar. Bitte einen aktuellen Browser verwenden.');
                    return;
                }
                pending = {event_id: crypto.randomUUID(), action, confirm_end_pause: endPause ? '1' : '0',
                    ...(action === 'start' ? {} : {expected_record: expectedRecord})};
            }
            // Applies to new events AND retries. Failure keeps the same in-page
            // identity and any stored uncertain event, but sends no mutation.
            if (!rememberPending()) {
                render();
                message('Dieser Buchungsversuch wurde nicht gesendet: Der Vorgang kann im Browserspeicher nicht unverändert gesichert werden. Er bleibt auf dieser Seite erhalten. Bereits unbestätigte Vorgänge nicht löschen; bitte Browserspeicher und Anmeldung prüfen und dieselbe Aktion erneut versuchen.');
                return;
            }
            busy = true; revision++; render();
            let confirmed = false;
            try {
                const response = await request('attendance.php', {method: 'POST', redirect: 'error',
                    body: new URLSearchParams({...pending, csrf_token: token})});
                if (!response.ok && response.status < 500 && ![401, 403].includes(response.status)) { expectedRecord = null; pending = null; rememberPending(); }
                const data = await readJson(response);
                if (!response.ok) throw new Error(data.message || 'Buchung wurde nicht bestätigt.');
                if (!data.success || !Object.hasOwn(allowed, data.state)) throw new Error('Keine gültige Buchungsbestätigung erhalten. Dieselbe Aktion erneut versuchen.');
                pending = null; rememberPending(); confirmed = true;
                render(data.state, null); // Cached acknowledgment is not a current session binding.
                // This may be a historical cached warning. It proves neither a
                // current conflict nor current numeric totals; the fresh read
                // below owns current period flags. Invalidate before it can fail.
                if (data.overlap_warning) invalidateReportValues();
                message([data.overlap_warning ? 'Buchungsbestätigung (bei Wiederholung historisch); aktueller Konfliktstand wird getrennt geprüft.' : '', data.message, data.local_time].filter(Boolean).join(' '));
            } catch (error) {
                // A lost/malformed end acknowledgement may already be committed.
                // Keep its exact pending event, but never leave old numbers
                // looking final while waiting for that same-event retry/read.
                if (pending?.action === 'end') invalidateReportValues();
                message(pending ? `${error.message || 'Keine Bestätigung erhalten.'} Dieselbe Aktion erneut versuchen.`
                    : error.message || 'Buchung wurde nicht bestätigt.');
            } finally {
                busy = false; render();
            }
            if (confirmed || !pending) {
                try { await refresh(true); }
                catch (error) { message(`${confirmed ? 'Buchung bestätigt. ' : ''}${error.message}`); }
            }
        });

        const absenceForm = document.getElementById('absence-form');
        let absenceBusy = false;
        absenceForm?.addEventListener('submit', async event => {
            event.preventDefault();
            if (absenceBusy) return;
            const status = document.getElementById('absence-message');
            const submitters = [...absenceForm.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]')];
            absenceBusy = true; submitters.forEach(button => { button.disabled = true; });
            try {
                const response = await request('save.php', {method: 'POST', redirect: 'error', body: new FormData(absenceForm)});
                const data = await readJson(response);
                if (status) status.textContent = data.message || 'Keine Bestätigung erhalten.';
                if (response.ok && data.success) {
                    absenceForm.reset();
                    try { await refresh(true); }
                    catch (error) { if (status) status.textContent += ` ${error.message}`; }
                }
            } catch (error) {
                if (status) status.textContent = 'Keine Bestätigung erhalten. Seite neu laden und vorhandene Buchungen prüfen.';
            } finally {
                absenceBusy = false; submitters.forEach(button => { button.disabled = false; });
            }
        });

        // The independent native absence form retains its existing inline handler.
        // Only attendance stays blocked until deliberate reload after clarification.
        if (!pendingVerified) return;
        render();
        if (pending) {
            if (pending.action === 'end') invalidateReportValues();
            message('Eine Buchung wurde noch nicht bestätigt. Dieselbe Aktion erneut versuchen.');
        }
        window.addEventListener('pageshow', refreshQuietly);
        window.addEventListener('focus', refreshQuietly);
        document.addEventListener('visibilitychange', refreshQuietly);
        window.setInterval(refreshQuietly, 30000);
        refreshQuietly();
    });
})();
