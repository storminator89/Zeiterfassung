/* One server-authoritative attendance controller for the familiar header and
 * start page. No client timestamps or localStorage work-time calculations.
 */
(() => {
    'use strict';
    const ready = callback => document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', callback, {once: true}) : callback();
    ready(() => {
        // A second script tag must not register a second booking handler.
        if (document.documentElement.dataset.attendanceController === 'ready') return;
        const initial = document.querySelector('[data-attendance-state], #actions[data-state]');
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        const userId = Number(document.body?.dataset.attendanceUser);
        if (!initial || !token || !Number.isSafeInteger(userId) || userId < 1) return;
        document.documentElement.dataset.attendanceController = 'ready';

        const labels = {absent: 'Nicht angemeldet', working: 'Arbeitszeit läuft', paused: 'Pause läuft'};
        const allowed = {absent: ['start'], working: ['pause_start', 'end'], paused: ['pause_end', 'end']};
        const buttonSelector = '[data-attendance-action], #actions [data-action]';
        const pendingKey = 'chrono-attendance-pending';
        let state = initial.dataset.attendanceState || initial.dataset.state;
        if (!Object.hasOwn(allowed, state)) state = 'absent';
        let busy = false, refreshing = null, revision = 0, pending = null;
        const actionOf = button => button.dataset.attendanceAction || button.dataset.action;
        const buttons = () => [...document.querySelectorAll(buttonSelector)];

        // Preserve this exact event for a safe retry after an interrupted request
        // or same-tab navigation. Reuse requires both the server-rendered account
        // ID and the CSRF token to match; login can retain an existing token.
        try {
            const stored = JSON.parse(sessionStorage.getItem(pendingKey) || 'null');
            if (stored?.userId === userId && stored.session === token && /^[A-Za-z0-9_-]{16,80}$/.test(stored.event?.event_id)
                && ['start', 'pause_start', 'pause_end', 'end'].includes(stored.event?.action)
                && ['0', '1'].includes(stored.event?.confirm_end_pause)) pending = stored.event;
            else sessionStorage.removeItem(pendingKey);
        } catch (ignored) { /* Private browsing may disable storage; in-page retries still work. */ }
        function rememberPending() {
            try {
                if (pending) sessionStorage.setItem(pendingKey, JSON.stringify({userId, session: token, event: pending}));
                else sessionStorage.removeItem(pendingKey);
            } catch (ignored) { /* Do not block bookings on unavailable browser storage. */ }
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
        function render(nextState = state) {
            if (!Object.hasOwn(allowed, nextState)) throw new Error('Unbekannter Anwesenheitsstatus. Bitte Seite neu laden.');
            state = nextState;
            document.querySelectorAll('[data-attendance-state]').forEach(element => { element.dataset.attendanceState = state; });
            const actions = document.getElementById('actions');
            if (actions) actions.dataset.state = state;
            document.querySelectorAll('[data-attendance-status], #status').forEach(element => { element.textContent = labels[state]; });
            buttons().forEach(button => {
                const action = actionOf(button);
                // An uncertain event must be acknowledged with the same ID,
                // even if a later status snapshot already reflects its effect.
                button.hidden = pending ? action !== pending.action : !allowed[state].includes(action);
                button.disabled = busy;
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
                    if (!data.success || !Object.hasOwn(allowed, data.state)) throw new Error('Anwesenheitsstatus konnte nicht geladen werden. Bitte neu anmelden.');
                    if (startedAt === revision && !busy) { render(data.state); renderRecent(data.records); }
                })().finally(() => { refreshing = null; });
            }
            await refreshing;
            if (includeTable && typeof window.updateTimeRecordsTable === 'function') {
                const updated = await window.updateTimeRecordsTable();
                if (!updated) throw new Error('Arbeitszeittabelle konnte nicht aktualisiert werden.');
            }
        }
        function refreshQuietly() {
            if (!document.hidden && !busy) void refresh().catch(error => { message(error.message); });
        }

        document.addEventListener('click', async event => {
            const button = event.target.closest(buttonSelector);
            if (!button || button.hidden || busy) return;
            event.preventDefault();
            const action = actionOf(button);
            if (pending && pending.action !== action) {
                message('Unbestätigte Buchung zuerst mit derselben Aktion erneut versuchen.');
                return;
            }
            if (!pending && !allowed[state].includes(action)) return;
            const endPause = action === 'end' && state === 'paused';
            if (endPause && !pending && !window.confirm('Pause und Arbeitszeit gemeinsam beenden?')) return;
            if (!pending) {
                if (typeof crypto.randomUUID !== 'function') {
                    message('Sichere Ereignis-ID nicht verfügbar. Bitte einen aktuellen Browser verwenden.');
                    return;
                }
                pending = {event_id: crypto.randomUUID(), action, confirm_end_pause: endPause ? '1' : '0'};
                rememberPending();
            }
            busy = true; revision++; render();
            let confirmed = false;
            try {
                const response = await request('attendance.php', {method: 'POST', redirect: 'error',
                    body: new URLSearchParams({...pending, csrf_token: token})});
                if (!response.ok && response.status < 500) { pending = null; rememberPending(); }
                const data = await readJson(response);
                if (!response.ok) throw new Error(data.message || 'Buchung wurde nicht bestätigt.');
                if (!data.success || !Object.hasOwn(allowed, data.state)) throw new Error('Keine gültige Buchungsbestätigung erhalten. Dieselbe Aktion erneut versuchen.');
                pending = null; rememberPending(); confirmed = true;
                render(data.state);
                message([data.message, data.local_time].filter(Boolean).join(' '));
            } catch (error) {
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

        render();
        if (pending) message('Eine Buchung wurde noch nicht bestätigt. Dieselbe Aktion erneut versuchen.');
        window.addEventListener('pageshow', refreshQuietly);
        window.addEventListener('focus', refreshQuietly);
        document.addEventListener('visibilitychange', refreshQuietly);
        window.setInterval(refreshQuietly, 30000);
        refreshQuietly();
    });
})();
