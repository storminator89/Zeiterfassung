/* Existing read-only dashboard Calendar; official FullCalendar Standard 7.1.3. */
(function () {
    'use strict';

    function mount(element, options) {
        if (!window.FullCalendar || window.FullCalendar.version !== '7.1.3') {
            throw new Error('The pinned Calendar asset is unavailable.');
        }
        const timeOptions = {timeZone: 'Europe/Berlin', hour: '2-digit', minute: '2-digit'};
        const time = value => new Date(value).toLocaleTimeString('de-DE', timeOptions);
        const modalTime = value => new Date(value).toLocaleTimeString(undefined, {timeZone: 'Europe/Berlin'});
        const modal = document.getElementById('scheduleModal');
        const close = modal.querySelector('[data-calendar-close]');
        close.addEventListener('click', function () {
            modal.classList.remove('modal-open');
        });

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
            height: 700,
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
                    clock.textContent = time(values.start) + ' - ' + time(values.displayEnd);
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
                document.getElementById('endTime').textContent = values.endDate + (info.event.allDay ? '' : ' ' + modalTime(values.displayEnd));
                document.getElementById('eventDescription').textContent = info.event.title;
                document.getElementById('eventLocation').textContent = values.location || '-';
                modal.classList.add('modal-open');
            },
            events: options.events,
        });
        calendar.render();
        return calendar;
    }

    window.ChronoCalendar = Object.freeze({mount: mount});
}());
