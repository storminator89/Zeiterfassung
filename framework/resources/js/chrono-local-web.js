/* Minimal startup gate around the EXISTING attendance client. Not a new booking engine. */
(() => {
    'use strict';
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
                || !['0', '1'].includes(pending?.event?.confirm_end_pause);
        }
    } catch (ignored) {
        blocked = true; // Malformed/inaccessible storage is not permission to lose an uncertain event.
    }
    if (blocked) {
        document.querySelectorAll('[data-attendance-action], #actions [data-action]').forEach(button => { button.disabled = true; });
        document.querySelectorAll('#message, #attendance-message').forEach(element => {
            element.textContent = 'Unbestätigte Buchung aus einer anderen oder nicht prüfbaren Sitzung unverändert gespeichert. Keine automatische Wiederholung. Bitte Anmeldung und ursprüngliche Buchung zuerst klären.';
        });
        return; // Never load the old client, which would otherwise discard mismatched pending storage.
    }
    const script = document.createElement('script');
    script.src = 'assets/js/attendance.js';
    script.addEventListener('error', () => {
        document.querySelectorAll('#message, #attendance-message').forEach(element => {
            element.textContent = 'Buchungsoberfläche konnte nicht geladen werden. Bitte neu laden.';
        });
    });
    document.head.append(script);
})();
