class TimeTour {
    constructor() {
        this.tour = new Shepherd.Tour({
            useModalOverlay: true,
            defaultStepOptions: {
                cancelIcon: {
                    enabled: true
                },
                classes: 'class-1 class-2',
                scrollTo: { behavior: 'smooth', block: 'center' }
            }
        });

        this.initTourSteps();
        this.addEventListeners();
        
        // Nur automatisch starten, wenn die Tour noch nie gesehen wurde
        this.checkAndStartTour();
    }

    initTourSteps() {
        this.tour.addStep({
            id: 'welcome',
            text: 'Willkommen bei der Zeiterfassung! Lassen Sie uns eine kurze Tour machen.',
            buttons: this.getButtons('first')
        });

        this.tour.addStep({
            id: 'time-buttons',
            text: 'Hier können Sie Ihre Arbeitszeit starten und beenden.',
            attachTo: {
                element: '#startButton',
                on: 'bottom'
            },
            buttons: this.getButtons()
        });

        this.tour.addStep({
            id: 'timer',
            text: 'Hier sehen Sie die aktuelle Arbeitszeit.',
            attachTo: {
                element: '#timer',
                on: 'bottom'
            },
            buttons: this.getButtons()
        });

        this.tour.addStep({
            id: 'statistics',
            text: 'Diese Karte zeigt Ihre aktuellen Statistiken.',
            attachTo: {
                element: '.stats',
                on: 'left'
            },
            buttons: this.getButtons()
        });

        this.tour.addStep({
            id: 'time-records',
            text: 'Hier finden Sie eine Übersicht Ihrer Zeiterfassung.',
            attachTo: {
                element: '#timeRecordsContainer',
                on: 'top'
            },
            buttons: this.getButtons('last')
        });
    }

    getButtons(position = 'middle') {
        const buttons = [];
        
        if (position !== 'first') {
            buttons.push({
                text: 'Zurück',
                action: this.tour.back
            });
        }

        buttons.push({
            text: position === 'last' ? 'Fertig' : 'Weiter',
            action: position === 'last' ? this.tour.complete : this.tour.next
        });

        return buttons;
    }

    addEventListeners() {
        // Tour-Button im Header - startet die Tour manuell
        document.getElementById('tourButton').addEventListener('click', () => {
            this.tour.start();
        });

        // Tour als abgeschlossen markieren wenn sie beendet wird
        this.tour.on('complete', () => {
            localStorage.setItem('tourCompleted', 'true');
        });

        // Auch als abgeschlossen markieren, wenn sie abgebrochen wird
        this.tour.on('cancel', () => {
            localStorage.setItem('tourCompleted', 'true');
        });
    }

    checkAndStartTour() {
        // Prüfe ob die Tour bereits abgeschlossen wurde
        const tourCompleted = localStorage.getItem('tourCompleted');
        
        // Starte die Tour nur, wenn sie noch nie abgeschlossen wurde
        if (!tourCompleted) {
            // Kurze Verzögerung um sicherzustellen, dass die Seite geladen ist
            setTimeout(() => {
                this.tour.start();
            }, 500);
        }
    }
}

// Tour initialisieren
document.addEventListener('DOMContentLoaded', () => {
    window.timeTour = new TimeTour();
});
