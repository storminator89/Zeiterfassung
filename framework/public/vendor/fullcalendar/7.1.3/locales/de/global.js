/*!
FullCalendar (Vanilla JS) v7.1.3
Docs & License: https://fullcalendar.io
(c) 2026 Adam Shaw
*/
(function ({ F: globalLocales }) {
    

    function __de$affix(unitText) {
        return (unitText === 'Tag' || unitText === 'Monat') ? 'r' :
            unitText === 'Jahr' ? 's' : '';
    }
    const __de$viewHints = {
        Tag: 'Tagesansicht',
        Woche: 'Wochenansicht',
        Monat: 'Monatsansicht',
        Jahr: 'Jahresansicht',
    };
    var __de$l18 = {
        code: 'de',
        week: {
            dow: 1, // Monday is the first day of the week.
            doy: 4, // The week that contains Jan 4th is the first week of the year.
        },
        prevText: 'Zurück',
        nextText: 'Vor',
        todayText: 'Heute',
        yearText: 'Jahr',
        monthText: 'Monat',
        weekTextLong: 'Woche',
        weekTextShort: 'KW',
        dayText: 'Tag',
        listText: 'Terminübersicht',
        allDayText: 'Ganztägig',
        moreLinkText(n) {
            return '+ weitere ' + n;
        },
        noEventsText: 'Keine Ereignisse anzuzeigen',
        prevHint(unitText) {
            return `Vorherige${__de$affix(unitText)} ${unitText}`;
        },
        nextHint(unitText) {
            return `Nächste${__de$affix(unitText)} ${unitText}`;
        },
        todayHint(unitText) {
            // → Heute, Diese Woche, Dieser Monat, Dieses Jahr
            if (unitText === 'Tag') {
                return 'Heute';
            }
            return `Diese${__de$affix(unitText)} ${unitText}`;
        },
        viewHint(unitText) {
            // other button text, like Terminübersicht, already names the view
            return __de$viewHints[unitText] || unitText;
        },
        navLinkHint: 'Gehe zu $0',
        moreLinkHint(eventCnt) {
            return 'Zeige ' + (eventCnt === 1 ?
                'ein weiteres Ereignis' :
                eventCnt + ' weitere Ereignisse');
        },
        closeHint: 'Schließen',
        eventsHint: 'Ereignisse',
    };

    globalLocales.push(__de$l18);

})(FullCalendar.Shared);
