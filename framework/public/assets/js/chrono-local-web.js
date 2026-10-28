/* Minimal startup gate around the EXISTING attendance client. Not a new booking engine. */
(() => {
    'use strict';
    // A later storage repair must not silently unblock this document.
    if (['ready', 'blocked'].includes(document.documentElement.dataset.attendanceController)) return;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const userId = Number(document.body.dataset.attendanceUser);
    let blocked = false;
    try {
        const raw = sessionStorage.getItem('chrono-attendance-pending');
        if (raw !== null) {
            const pending = JSON.parse(raw);
            blocked = pending?.userId !== userId || pending?.session !== token
                || typeof pending?.event?.event_id !== 'string'
                || !/^[A-Za-z0-9_-]{16,80}$/.test(pending?.event?.event_id)
                || !['start', 'pause_start', 'pause_end', 'end'].includes(pending?.event?.action)
                || !['0', '1'].includes(pending?.event?.confirm_end_pause)
                || (Object.hasOwn(pending.event, 'expected_record')
                    && (typeof pending.event.expected_record !== 'string' || !/^[1-9][0-9]*$/.test(pending.event.expected_record)));
            // Omitted legacy binding is preserved for the server's replay-first decision.
        }
    } catch (ignored) {
        blocked = true; // Malformed/inaccessible storage is not permission to lose an uncertain event.
    }
    if (blocked) {
        document.documentElement.dataset.attendanceController = 'blocked';
        document.querySelectorAll('[data-attendance-action], #actions [data-action]').forEach(button => { button.disabled = true; });
        document.querySelectorAll('#message, #attendance-message').forEach(element => {
            element.textContent = 'Buchungen bleiben gesperrt: Ein vorhandener oder nicht prüfbarer Vorgang darf nicht überschrieben oder einer anderen Anmeldung zugeordnet werden. Bitte Browserspeicher und ursprüngliche Buchung klären; danach bewusst neu laden. Keine automatische Wiederholung.';
        });
        return; // Never load the booking client when pending cannot safely be adopted.
    }
    const script = document.createElement('script');
    script.src = 'assets/js/attendance.js';
    script.addEventListener('load', () => {
        // Absence uses the existing client too. Never enable a bare form when
        // pending storage blocked that client or initialization did not finish.
        const fields = document.querySelector('#absence-form [data-local-absence-enabled="1"]');
        if (document.documentElement.dataset.attendanceController === 'ready' && fields) fields.disabled = false;
    });
    script.addEventListener('error', () => {
        document.querySelectorAll('#message, #attendance-message').forEach(element => {
            element.textContent = 'Buchungsoberfläche konnte nicht geladen werden. Bitte neu laden.';
        });
    });
    document.head.append(script);
})();
