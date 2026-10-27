@extends('chrono.layouts.app')

@section('content')
{{-- Existing TOAST CSS only. Executable Calendar asset admission remains separate;
     this inactive view deliberately adds no external JavaScript/CDN compiler. --}}
<link rel="stylesheet" href="https://uicdn.toast.com/calendar/latest/toastui-calendar.min.css" />
    <style>
        .chrono-dashboard .stat-value {
            font-size: 2rem;
        }

        .chrono-dashboard .calendar-container {
            height: 700px;
            overflow: hidden;
            border-radius: 1rem;
        }

        .toastui-calendar-milestone,
        .toastui-calendar-task {
            display: none !important;
        }

        .chrono-dashboard .stat,
        .chrono-dashboard .stat-title,
        .chrono-dashboard .stat-value,
        .chrono-dashboard .stat-desc {
            color: white !important;
        }

        .chrono-dashboard .info-tooltip {
            position: relative;
            display: inline-block;
            cursor: help;
        }

        .chrono-dashboard .info-tooltip .tooltiptext {
            visibility: hidden;
            width: 350px;
            background-color: #2a4365;
            color: #e2e8f0;
            text-align: left;
            border-radius: 6px;
            padding: 10px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -125px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.9rem;
            line-height: 1.4;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            word-wrap: break-word;
        }

        .chrono-dashboard .info-tooltip .tooltiptext::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #555 transparent transparent transparent;
        }

        .chrono-dashboard .info-tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        /* Native Tailwind equivalents, scoped only to the existing dashboard. */
        .chrono-dashboard .text-4xl { font-size: 2.25rem; line-height: 2.5rem; }
        .chrono-dashboard .ml-2 { margin-left: .5rem; }
        .chrono-dashboard .mb-8 { margin-bottom: 2rem; }
        .chrono-dashboard .opacity-80 { opacity: .8; }
        .chrono-dashboard .shadow-lg { box-shadow: 0 10px 15px -3px rgb(0 0 0 / .1), 0 4px 6px -4px rgb(0 0 0 / .1); }
        .chrono-dashboard .bg-gradient-to-br { background-image: linear-gradient(to bottom right, var(--chrono-from), var(--chrono-to)); }
        .chrono-dashboard .from-indigo-600 { --chrono-from: #4f46e5; --chrono-to: #4338ca; }
        .chrono-dashboard .from-indigo-500 { --chrono-from: #6366f1; --chrono-to: #4f46e5; }
        .chrono-dashboard .from-indigo-400 { --chrono-from: #818cf8; --chrono-to: #6366f1; }
        .chrono-dashboard .from-blue-600 { --chrono-from: #2563eb; --chrono-to: #1d4ed8; }
        .chrono-dashboard .from-blue-500 { --chrono-from: #3b82f6; --chrono-to: #2563eb; }
        .chrono-dashboard .from-blue-400 { --chrono-from: #60a5fa; --chrono-to: #3b82f6; }
        .chrono-dashboard .from-teal-600 { --chrono-from: #0d9488; --chrono-to: #0f766e; }
        .chrono-dashboard .from-teal-500 { --chrono-from: #14b8a6; --chrono-to: #0d9488; }
        .chrono-dashboard .from-teal-400 { --chrono-from: #2dd4bf; --chrono-to: #14b8a6; }
        @media (min-width: 1024px) {
            .chrono-dashboard .lg\:grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
    </style>
    <div class="container mx-auto px-4 py-8 chrono-dashboard">
        <h2 class="text-4xl font-bold mb-8 text-center">{{ $page->copy->text('DASHBOARD_TITLE') }}</h2>

        <!-- Overall Stats -->
        <div class="mb-8">
            <h3 class="text-2xl font-semibold mb-4">{{ $page->copy->text('DASHBOARD_OVERALL_STATS') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="stat bg-gradient-to-br from-indigo-600 to-indigo-700 rounded-box shadow-lg">
                    <div class="stat-figure opacity-70">
                        <i class="fas fa-user-clock fa-3x"></i>
                    </div>
                    <div class="stat-title text-lg font-semibold opacity-80">{{ $page->copy->text('DASHBOARD_REGULAR_HOURS') }}</div>
                    <div class="stat-value">{{ $dashboard->statistics['regular_hours'] }}</div>
                    <div class="stat-desc text-sm font-medium opacity-80">{{ $page->copy->text('DASHBOARD_HOURS_PER_DAY') }}</div>
                </div>
                <div class="stat bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-box shadow-lg">
                    <div class="stat-figure opacity-70">
                        <i class="fas fa-trophy fa-3x"></i>
                    </div>
                    <div class="stat-title text-lg font-semibold opacity-80">{{ $page->copy->text('DASHBOARD_WEEKS_WORKED') }}</div>
                    <div data-report-period="all" data-report-presentation="{{ $dashboard->presentation('all') }}" class="stat-value">{{ $dashboard->value('weeks_count', 'all') }}</div>
                    <div data-report-period="all" data-report-presentation="{{ $dashboard->presentation('all') }}" class="stat-desc text-sm font-medium opacity-80">
                        {{ $dashboard->presentation('all') === 'current' ? $page->copy->text($dashboard->statistics['is_first_week'] ? 'DASHBOARD_FIRST_WEEK' : 'DASHBOARD_KEEP_GOING') : 'Buchungen bitte manuell klären.' }}
                    </div>
                </div>
                <div class="stat bg-gradient-to-br from-indigo-400 to-indigo-500 rounded-box shadow-lg">
                    <div class="stat-figure opacity-70">
                        <i class="fas fa-hourglass-half fa-3x"></i>
                    </div>
                    <div class="stat-title text-lg font-semibold opacity-80">
                        {{ $page->copy->text('DASHBOARD_TOTAL_OVERTIME') }}
                        <span class="info-tooltip">
                            <i class="fas fa-info-circle"></i>
                            <span class="tooltiptext"><span style="white-space: pre-line">{{ $page->copy->text('DASHBOARD_OVERTIME_INFO') }}</span></span>
                        </span>
                    </div>
                    <div data-report-period="all" data-report-presentation="{{ $dashboard->presentation('all') }}" class="stat-value">{{ $dashboard->value('total_overtime', 'all') }}</div>
                    <div data-report-period="year" data-report-presentation="{{ $dashboard->presentation('year') }}" class="stat-desc text-sm font-medium opacity-80">
                        {{ sprintf($page->copy->text('DASHBOARD_OVER_HOURS_YEAR'), $dashboard->value('year_overtime', 'year')) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly Overview -->
        <div class="mb-8">
            <h3 class="text-2xl font-semibold mb-4">{{ $page->copy->text('DASHBOARD_WEEKLY_OVERVIEW') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="stat bg-gradient-to-br from-blue-600 to-blue-700 rounded-box shadow-lg">
                    <div class="stat-figure opacity-70">
                        <i class="fas fa-clock fa-3x"></i>
                    </div>
                    <div class="stat-title text-lg font-semibold opacity-80">{{ $page->copy->text('DASHBOARD_WEEKLY_HOURS') }}</div>
                    <div data-report-period="week" data-report-presentation="{{ $dashboard->presentation('week') }}" class="stat-value">{{ $dashboard->value('week_hours', 'week') }}</div>
                </div>
                <div class="stat bg-gradient-to-br from-blue-500 to-blue-600 rounded-box shadow-lg">
                    <div class="stat-figure opacity-70">
                        <i class="fas fa-hourglass-half fa-3x"></i>
                    </div>
                    <div class="stat-title text-lg font-semibold opacity-80">{{ $page->copy->text('DASHBOARD_REMAINING_HOURS_WEEK') }}</div>
                    <div data-report-period="week" data-report-presentation="{{ $dashboard->presentation('week') }}" class="stat-value">{{ $dashboard->value('remaining_week', 'week') }}</div>
                </div>
                <div class="stat bg-gradient-to-br from-blue-400 to-blue-500 rounded-box shadow-lg">
                    <div class="stat-figure opacity-70">
                        <i class="fas fa-tachometer-alt fa-3x"></i>
                    </div>
                    <div class="stat-title text-lg font-semibold opacity-80">
                        {{ $page->copy->text('DASHBOARD_WEEKLY_PRODUCTIVITY') }}
                        <span class="info-tooltip">
                            <i class="fas fa-info-circle"></i>
                            <span class="tooltiptext"><span style="white-space: pre-line">{{ $page->copy->text('DASHBOARD_WEEKLY_PRODUCTIVITY_INFO') }}</span></span>
                        </span>
                    </div>
                    <div data-report-period="week" data-report-presentation="{{ $dashboard->presentation('week') }}" class="stat-value">{{ $dashboard->value('weekly_productivity', 'week') }}</div>
                </div>
            </div>
        </div>

        <!-- Monthly Overview -->
        <div class="mb-8">
            <h3 class="text-2xl font-semibold mb-4">{{ $page->copy->text('DASHBOARD_MONTHLY_OVERVIEW') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="stat bg-gradient-to-br from-teal-600 to-teal-700 rounded-box shadow-lg">
                    <div class="stat-figure opacity-70">
                        <i class="fas fa-calendar fa-3x"></i>
                    </div>
                    <div class="stat-title text-lg font-semibold opacity-80">{{ $page->copy->text('DASHBOARD_MONTHLY_HOURS') }}</div>
                    <div data-report-period="month" data-report-presentation="{{ $dashboard->presentation('month') }}" class="stat-value">{{ $dashboard->value('month_hours', 'month') }}</div>
                    <div data-report-period="month" data-report-presentation="{{ $dashboard->presentation('month') }}" class="stat-desc text-sm font-medium opacity-80">
                        {{ sprintf($page->copy->text('DASHBOARD_MONTHLY_HOURS_PROGRESS'), $dashboard->value('month_hours', 'month'), $dashboard->statistics['planned_month']) }}
                    </div>
                </div>
                <div class="stat bg-gradient-to-br from-teal-500 to-teal-600 rounded-box shadow-lg">
                    <div class="stat-figure opacity-70">
                        <i class="fas fa-chart-line fa-3x"></i>
                    </div>
                    <div class="stat-title text-lg font-semibold opacity-80">{{ $page->copy->text('DASHBOARD_AVERAGE_DAILY_HOURS') }}</div>
                    <div data-report-period="month" data-report-presentation="{{ $dashboard->presentation('month') }}" class="stat-value">{{ $dashboard->value('average_daily_hours', 'month') }}</div>
                    <div data-report-period="month" data-report-presentation="{{ $dashboard->presentation('month') }}" class="stat-desc text-sm font-medium opacity-80">
                        {{ $dashboard->presentation('month') === 'current' ? sprintf($page->copy->text('DASHBOARD_WORKED_DAYS_THIS_MONTH'), $dashboard->statistics['worked_days']) : 'Betroffene Buchungstage sind noch nicht abschließend geklärt.' }}
                    </div>
                </div>
                <div class="stat bg-gradient-to-br from-teal-400 to-teal-500 rounded-box shadow-lg">
                    <div class="stat-figure opacity-70">
                        <i class="fas fa-tachometer-alt fa-3x"></i>
                    </div>
                    <div class="stat-title text-lg font-semibold opacity-80">
                        {{ $page->copy->text('DASHBOARD_PRODUCTIVITY_SCORE') }}
                        <span class="info-tooltip">
                            <i class="fas fa-info-circle"></i>
                            <span class="tooltiptext"><span style="white-space: pre-line">{{ $page->copy->text('DASHBOARD_PRODUCTIVITY_SCORE_INFO') }}</span></span>
                        </span>
                    </div>
                    <div data-report-period="month" data-report-presentation="{{ $dashboard->presentation('month') }}" class="stat-value">{{ $dashboard->value('monthly_productivity', 'month') }}</div>
                </div>
            </div>
        </div>

        <!-- Calendar Section -->
        <div class="card bg-base-100 shadow-xl mb-8">
            <div class="card-body">
                <h3 class="card-title text-2xl mb-6">{{ $page->copy->text('DASHBOARD_CALENDAR') }}</h3>
                <div class="flex justify-between mb-6">
                    <a id="prevWeekBtn" href="{{ $dashboard->link($dashboard->range['previous']) }}" class="btn btn-primary btn-sm"><i class="fas fa-arrow-left mr-2"></i>{{ $page->copy->text('BUTTON_PREV_WEEK') }}</a>
                    <a id="todayBtn" href="{{ $dashboard->link($dashboard->range['today']) }}" class="btn btn-secondary btn-sm"><i class="fas fa-calendar-day mr-2"></i>{{ $page->copy->text('BUTTON_TODAY') }}</a>
                    <a id="nextWeekBtn" href="{{ $dashboard->link($dashboard->range['next']) }}" class="btn btn-primary btn-sm">{{ $page->copy->text('BUTTON_NEXT_WEEK') }}<i class="fas fa-arrow-right ml-2"></i></a>
                </div>
                <div id="calendar" class="calendar-container"></div>
            </div>
        </div>
    </div>

    <!-- Schedule Modal -->
    <div class="modal" id="scheduleModal">
        <div class="modal-box">
            <h3 class="font-bold text-lg" id="scheduleModalLabel">{{ $page->copy->text('MODAL_TITLE_SCHEDULE') }}</h3>
            <p class="py-4">
                <strong>{{ $page->copy->text('FORM_START') }}:</strong> <span id="startTime"></span><br>
                <strong>{{ $page->copy->text('FORM_END') }}:</strong> <span id="endTime"></span><br>
                <strong>{{ $page->copy->text('FORM_DESCRIPTION') }}:</strong> <span id="eventDescription"></span><br>
                <strong>{{ $page->copy->text('FORM_LOCATION') }}:</strong> <span id="eventLocation"></span>
            </p>
            <div class="modal-action">
                <button class="btn" onclick="closeModal()">{{ $page->copy->text('BUTTON_CLOSE') }}</button>
            </div>
        </div>
    </div>

    <script>
        // Calendar initialization
        const Calendar = tui.Calendar;
        const calendar = new Calendar('#calendar', {
            defaultView: 'week',
            useCreationPopup: false,
            useDetailPopup: false,
            isReadOnly: true,
            week: {
                dayNames: {!! \Illuminate\Support\Js::from(array_map(fn ($key) => $page->copy->text($key), ['SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'])) !!},
                startDayOfWeek: 1,
                workweek: false,
                hourStart: 6,
                hourEnd: 22,
                taskView: false,
                eventView: ['allday', 'time']
            },
            month: {
                visibleWeeksCount: 0 // Hide the month view completely
            },
            template: {
                allday: function(event) {
                    // Template strings are HTML; cached holiday names are text.
                    const label = document.createElement('span');
                    label.textContent = event.title;
                    return label.outerHTML;
                },
                time: function(event) {
                    const start = new Date(event.start);
                    const end = new Date(event.end);
                    const startTime = start.toLocaleTimeString('de-DE', {timeZone: 'Europe/Berlin', hour: '2-digit', minute: '2-digit'});
                    const endTime = end.toLocaleTimeString('de-DE', {timeZone: 'Europe/Berlin', hour: '2-digit', minute: '2-digit'});
                    const label = document.createElement('span');
                    const time = document.createElement('span');
                    time.className = 'event-time';
                    time.textContent = startTime + ' - ' + endTime;
                    const title = document.createElement('span');
                    title.className = 'event-title';
                    title.textContent = event.title;
                    label.append(time, document.createTextNode(' '), title);
                    return label.outerHTML;
                }
            },
            taskView: false,
            milestone: {
                visible: false
            },
            timezone: {
                zones: [{
                    timezoneName: 'Europe/Berlin',
                    displayLabel: 'Berlin',
                    tooltip: 'Europe/Berlin'
                }]
            },
            disableClick: true,
            disableDblClick: true,
        });

        // Server-rendered navigation reloads both bookings and cached holidays
        // for the same selected week, including an empty week and browser Back.
        // TOAST anchors its week to browser-local calendar fields, not a Berlin instant.
        calendar.setDate(new Date({!! \Illuminate\Support\Js::from($dashboard->range['start']->format('Y-m-d').'T12:00:00') !!}));
        const events = {!! \Illuminate\Support\Js::from($dashboard->events) !!};
        calendar.createEvents(events);

        function formatDate(date) {
            return date.toLocaleDateString('de-DE', {timeZone: 'Europe/Berlin', day: '2-digit', month: '2-digit', year: 'numeric'});
        }

        function showModal() {
            document.getElementById('scheduleModal').classList.add('modal-open');
        }

        function closeModal() {
            document.getElementById('scheduleModal').classList.remove('modal-open');
        }

        calendar.on('clickEvent', function(event) {
            let startDate = new Date(event.event.start);
            let endDate = new Date(event.event.end);

            const isAbsence = event.event.isAllday;
            document.getElementById('startTime').textContent = formatDate(startDate) + (isAbsence ? '' : " " + startDate.toLocaleTimeString(undefined, {timeZone: 'Europe/Berlin'}));
            document.getElementById('endTime').textContent = formatDate(endDate) + (isAbsence ? '' : " " + endDate.toLocaleTimeString(undefined, {timeZone: 'Europe/Berlin'}));
            document.getElementById('eventDescription').textContent = event.event.title;
            document.getElementById('eventLocation').textContent = event.event.location || '-';

            showModal();
        });

        // Additional styles for calendar events
        const style = document.createElement('style');
        style.textContent = `
        .toastui-calendar-event-time {
            color: #ffffff !important;
        }
        .event-time {
            font-weight: bold;
            margin-right: 5px;
        }
        .event-title {
            font-style: italic;
        }
    `;
        document.head.appendChild(style);
    </script>

@endsection
