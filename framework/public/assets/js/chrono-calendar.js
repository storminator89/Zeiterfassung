/* Existing read-only dashboard Calendar; official FullCalendar Standard 7.1.3. */
(function () {
    'use strict';

    function mount(element, options) {
        if (!window.FullCalendar || window.FullCalendar.version !== '7.1.3') {
            throw new Error('The pinned Calendar asset is unavailable.');
        }
        const timeOptions = {timeZone: 'Europe/Berlin', hour: '2-digit', minute: '2-digit'};
        const time = value => new Date(value).toLocaleTimeString('de-DE', timeOptions);
        const modalTime = value => new Date(value).toLocaleTimeString('de-DE', timeOptions);
        const modal = document.getElementById('scheduleModal');
        modal.querySelectorAll('[data-calendar-close]').forEach(close => close.addEventListener('click', () => modal.close()));

        const calendar = new window.FullCalendar.Calendar(element, {
            initialView: 'timeGridWeek',
            initialDate: options.weekStart,
            timeZone: 'Europe/Berlin',
            locale: options.language,
            colorScheme: options.theme === 'dark' ? 'dark' : 'light',
            firstDay: 1,
            weekends: true,
            headerToolbar: false,
            footerToolbar: false,
            height: '100%',
            eventMinHeight: 28,
            eventShortHeight: 28,
            slotMinTime: '06:00:00',
            slotMaxTime: '22:00:00',
            allDaySlot: true,
            editable: false,
            selectable: false,
            navLinks: false,
            defaultTimedEventDuration: '00:30:00',
            forceEventDuration: false,
            eventClass: 'chrono-calendar-event',
            eventContent: function (info) {
                const values = info.event.extendedProps.chrono;
                const label = document.createElement('span');
                if (!info.event.allDay) {
                    const clock = document.createElement('span');
                    clock.className = 'event-time';
                    // The half-hour block is geometry only, never a stored end.
                    clock.textContent = time(values.start) + ' - ' + (values.end === null ? options.missingEndLabel : time(values.displayEnd));
                    label.append(clock, document.createTextNode(' '));
                }
                const title = document.createElement('span');
                title.className = info.event.allDay ? '' : 'event-title';
                title.textContent = info.event.title;
                label.append(title);
                return {domNodes: [label]};
            },
            eventDidMount: function (info) {
                info.el.style.borderColor = info.event.extendedProps.chrono.borderColor;
            },
            eventClick: function (info) {
                const values = info.event.extendedProps.chrono;
                document.getElementById('startTime').textContent = values.startDate + (info.event.allDay ? '' : ' ' + modalTime(values.start));
                document.getElementById('endTime').textContent = !info.event.allDay && values.end === null
                    ? options.missingEndLabel
                    : values.endDate + (info.event.allDay ? '' : ' ' + modalTime(values.displayEnd));
                document.getElementById('eventTitle').textContent = info.event.allDay ? info.event.title : 'Arbeitszeit';
                document.getElementById('eventDescription').textContent = values.note || '—';
                document.getElementById('eventDurationRow').hidden = values.durationLabel == null;
                document.getElementById('eventDuration').textContent = values.durationLabel ?? '—';
                document.getElementById('eventPauseRow').hidden = info.event.allDay || values.pause == null;
                document.getElementById('eventPause').textContent = values.pause == null ? '—' : values.pause + ' min';
                const correction = document.getElementById('eventCorrection');
                correction.hidden = !/^[1-9][0-9]*$/.test(values.recordId || '');
                if (correction.hidden) correction.removeAttribute('href');
                else correction.href = 'adjustments.php?record_id=' + encodeURIComponent(values.recordId);
                document.getElementById('eventLocation').textContent = ({office: 'Büro', home_office: 'Home Office', business_trip: 'Dienstreise'})[values.location] || values.location || '—';
                if (!modal.open) modal.showModal();
            },
            events: options.events,
        });
        calendar.render();
        return calendar;
    }

    window.ChronoCalendar = Object.freeze({mount: mount});
}());
