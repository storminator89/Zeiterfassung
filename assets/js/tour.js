class TimeTour {
    constructor(button) {
        this.button = button;
        this.tour = null;
        this.button.addEventListener('click', () => this.start());
        this.checkAndStartTour();
    }

    visibleTarget(selector) {
        return [...document.querySelectorAll(selector)].find(element => {
            const style = window.getComputedStyle(element);
            return !element.hidden && style.display !== 'none' && style.visibility !== 'hidden'
                && element.getClientRects().length > 0;
        });
    }

    start() {
        if (!window.Shepherd?.Tour || window.Shepherd.activeTour) return;
        this.tour = new window.Shepherd.Tour({
            useModalOverlay: true,
            defaultStepOptions: {
                cancelIcon: {enabled: true},
                classes: 'class-1 class-2',
                scrollTo: {behavior: 'smooth', block: 'center'}
            }
        });
        this.initTourSteps();
        const remember = () => {
            try { localStorage.setItem('tourCompleted', 'true'); }
            catch (ignored) { /* The tour also works when storage is disabled. */ }
        };
        this.tour.on('complete', remember);
        this.tour.on('cancel', remember);
        this.tour.start();
    }

    initTourSteps() {
        const steps = [{id: 'welcome', text: 'Willkommen bei der Zeiterfassung! Lassen Sie uns eine kurze Tour machen.'}];
        const actions = this.visibleTarget('#startButton') || this.visibleTarget('#actions')
            || this.visibleTarget('[data-attendance-action]');
        const status = this.visibleTarget('#timer') || this.visibleTarget('#status');
        const statistics = this.visibleTarget('.stats');
        const records = this.visibleTarget('#timeRecordsContainer');
        if (actions) steps.push({
            id: 'time-buttons',
            text: 'Hier können Sie Ihre Arbeitszeit und tatsächliche Pausen starten oder beenden. Jede Buchung wird mit Serverzeit bestätigt.',
            attachTo: {element: actions, on: 'bottom'}
        });
        if (status) steps.push({
            id: 'timer',
            text: 'Hier sehen Sie Ihren bestätigten Anwesenheitsstatus: nicht angemeldet, Arbeitszeit läuft oder Pause läuft.',
            attachTo: {element: status, on: 'bottom'}
        });
        if (statistics) steps.push({
            id: 'statistics', text: 'Diese Karte zeigt Ihre aktuellen Statistiken.',
            attachTo: {element: statistics, on: 'left'}
        });
        if (records) steps.push({
            id: 'time-records',
            text: 'Hier finden Sie Ihre erfassten Arbeitszeiten. Nachträgliche Änderungen und Stornierungen erfolgen über einen begründeten Korrekturantrag.',
            attachTo: {element: records, on: 'top'}
        });
        steps.forEach((step, index) => {
            const buttons = [];
            if (index > 0) buttons.push({text: 'Zurück', action: () => this.tour.back()});
            const last = index === steps.length - 1;
            buttons.push({text: last ? 'Fertig' : 'Weiter', action: () => last ? this.tour.complete() : this.tour.next()});
            this.tour.addStep({...step, buttons});
        });
    }

    checkAndStartTour() {
        let completed = false;
        try { completed = Boolean(localStorage.getItem('tourCompleted')); }
        catch (ignored) { /* An unavailable preference must not break the page. */ }
        if (!completed) window.setTimeout(() => this.start(), 500);
    }
}

const initializeTimeTour = () => {
    const button = document.getElementById('tourButton');
    if (!button || !window.Shepherd?.Tour || window.timeTour) return;
    window.timeTour = new TimeTour(button);
};
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeTimeTour, {once: true});
else initializeTimeTour();
