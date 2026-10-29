@extends('chrono.layouts.app')

@section('content')
{{-- Pinned official FullCalendar Standard assets, local to the public webroot. --}}
<link rel="stylesheet" href="vendor/fullcalendar/7.1.3/skeleton.css" />
<link rel="stylesheet" href="vendor/fullcalendar/7.1.3/themes/classic/theme.css" />
<link rel="stylesheet" href="vendor/fullcalendar/7.1.3/themes/classic/palette.css" />
    <style>
        .chrono-dashboard .stat-value {
            font-size: 2rem;
        }

        .chrono-dashboard .calendar-container {
            height: 700px;
            overflow: hidden;
            border-radius: 1rem;
        }

        .chrono-dashboard .chrono-calendar-event .event-time {
            font-weight: bold;
            margin-right: 5px;
        }

        .chrono-dashboard .chrono-calendar-event .event-title {
            font-style: italic;
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
        <p class="text-sm mb-6">{{ $page->copy->text('DASHBOARD_PERIOD_BASIS') }}</p>

        @if ($holidayInventoryUnavailable ?? false)
        <details id="holiday-inventory" class="mb-6 text-sm border border-base-300 rounded-box p-3" @if ($holidayInventoryOpen ?? false) open @endif>
            <summary class="cursor-pointer font-semibold">{{ $page->copy->text('HOLIDAY_INVENTORY_TITLE') }} · {{ $page->copy->text('HOLIDAY_INVENTORY_UNVERIFIED') }}</summary>
            <p class="mt-3" role="status">{{ $page->copy->text('HOLIDAY_INVENTORY_UNAVAILABLE') }}</p>
        </details>
        @elseif (($holidayInventory ?? null) !== null)
        <details id="holiday-inventory" class="mb-6 text-sm border border-base-300 rounded-box p-3" @if ($holidayInventoryOpen ?? false) open @endif>
            <summary class="cursor-pointer font-semibold">{{ $page->copy->text('HOLIDAY_INVENTORY_TITLE') }} · {{ $holidayInventory['year'] }} · {{ $page->copy->text('HOLIDAY_INVENTORY_UNVERIFIED') }}</summary>
            <p class="mt-3">{{ $page->copy->text('HOLIDAY_INVENTORY_ACTION') }}</p>
            <p class="mt-2 opacity-70">{{ $page->copy->text('HOLIDAY_INVENTORY_LIMIT') }}</p>
            <form method="get" action="dashboard.php#holiday-inventory" class="flex flex-wrap items-end gap-2 mt-3">
                <input type="hidden" name="calendar_date" value="{{ $dashboard->range['start']->format('Y-m-d') }}">
                <div><label for="holiday-year" class="block">{{ $page->copy->text('HOLIDAY_INVENTORY_YEAR') }}</label><input id="holiday-year" name="holiday_year" type="number" min="1" max="9999" step="1" required value="{{ $holidayInventory['year'] }}" class="input input-bordered input-sm w-28"></div>
                <button type="submit" class="btn btn-outline btn-sm">{{ $page->copy->text('HOLIDAY_INVENTORY_SHOW') }}</button>
            </form>
            @if ($holidayInventory['selection_invalid'])<p class="mt-2" role="status">{{ $page->copy->text('HOLIDAY_INVENTORY_INVALID_YEAR') }}</p>@endif
            <p class="mt-3 font-semibold">{{ sprintf($page->copy->text('HOLIDAY_INVENTORY_COUNT'), count($holidayInventory['rows'])) }}</p>
            @if ($holidayInventory['truncated'] || $holidayInventory['invalid_dates'])<p class="mt-2">{{ $page->copy->text('HOLIDAY_INVENTORY_DATA_ISSUE') }}</p>@endif
            @if ($holidayInventory['rows'] === [])<p class="mt-2">{{ $page->copy->text('HOLIDAY_INVENTORY_EMPTY') }}</p>@else
            <ul class="list-disc pl-5 mt-2 max-h-64 overflow-y-auto" aria-label="{{ $page->copy->text('HOLIDAY_INVENTORY_TITLE') }}">
                @foreach ($holidayInventory['rows'] as $holiday)<li>{{ $holiday['datum'] }} · {{ $holiday['name'] }}</li>@endforeach
            </ul>
            @endif
        </details>
        @endif

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
                    <p class="text-sm opacity-70 mt-2" style="white-space: normal;">{{ $page->copy->text('DASHBOARD_OVERTIME_INFO') }}</p>
                    <div data-report-period="year" data-report-presentation="{{ $dashboard->presentation('year') }}" class="stat-desc text-sm font-medium opacity-80">
                        {{ sprintf($page->copy->text('DASHBOARD_OVER_HOURS_YEAR'), $dashboard->value('year_overtime', 'year')) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly Overview -->
        <div class="mb-8">
            <h3 class="text-2xl font-semibold mb-4">{{ $page->copy->text('DASHBOARD_WEEKLY_OVERVIEW') }}</h3>
            <p class="text-sm mb-4">{{ sprintf($page->copy->text('DASHBOARD_PERIOD_RANGE'), $dashboard->statistics['week_from'], $dashboard->statistics['week_to']) }}</p>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="stat bg-gradient-to-br from-blue-600 to-blue-700 rounded-box shadow-lg">
                    <div class="stat-figure opacity-70">
                        <i class="fas fa-clock fa-3x"></i>
                    </div>
                    <div class="stat-title text-lg font-semibold opacity-80">{{ $page->copy->text('DASHBOARD_WEEKLY_HOURS') }}</div>
                    <div data-report-period="week" data-report-presentation="{{ $dashboard->presentation('week') }}" class="stat-value">{{ $dashboard->value('week_hours', 'week') }}</div>
                    <div data-report-period="week" data-report-presentation="{{ $dashboard->presentation('week') }}" class="stat-desc text-sm font-medium opacity-80" style="white-space:normal">
                        <span class="block" data-report-component="work">{{ sprintf($page->copy->text('DASHBOARD_COMPLETED_WORK'), $dashboard->value('week_work_hours', 'week')) }}</span>
                        <span class="block" data-report-component="absence">{{ sprintf($page->copy->text('DASHBOARD_ABSENCE_CREDIT'), $dashboard->value('week_absence_hours', 'week')) }}</span>
                    </div>
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
            <p class="text-sm mb-4">{{ sprintf($page->copy->text('DASHBOARD_PERIOD_RANGE'), $dashboard->statistics['month_from'], $dashboard->statistics['month_to']) }}</p>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="stat bg-gradient-to-br from-teal-600 to-teal-700 rounded-box shadow-lg">
                    <div class="stat-figure opacity-70">
                        <i class="fas fa-calendar fa-3x"></i>
                    </div>
                    <div class="stat-title text-lg font-semibold opacity-80">{{ $page->copy->text('DASHBOARD_MONTHLY_HOURS') }}</div>
                    <div data-report-period="month" data-report-presentation="{{ $dashboard->presentation('month') }}" class="stat-value">{{ $dashboard->value('month_hours', 'month') }}</div>
                    <div data-report-period="month" data-report-presentation="{{ $dashboard->presentation('month') }}" class="stat-desc text-sm font-medium opacity-80" style="white-space:normal">
                        <span class="block" data-report-component="work">{{ sprintf($page->copy->text('DASHBOARD_COMPLETED_WORK'), $dashboard->value('month_work_hours', 'month')) }}</span>
                        <span class="block" data-report-component="absence">{{ sprintf($page->copy->text('DASHBOARD_ABSENCE_CREDIT'), $dashboard->value('month_absence_hours', 'month')) }}</span>
                    </div>
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
                <p id="calendarLoadStatus" class="text-sm opacity-70 mb-3" role="status"><span id="calendarLoadStatusText">{{ $page->copy->text('DASHBOARD_CALENDAR_LOADING') }}</span> <a class="link" href="attendance.php">{{ $page->copy->text('DASHBOARD_CALENDAR_FALLBACK') }}</a></p>
                <noscript><p class="text-sm opacity-70 mb-3">{{ $page->copy->text('DASHBOARD_CALENDAR_NO_SCRIPT') }}</p></noscript>
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
                <button class="btn" data-calendar-close>{{ $page->copy->text('BUTTON_CLOSE') }}</button>
            </div>
        </div>
    </div>

    <script src="vendor/fullcalendar/7.1.3/all/global.js"></script>
    <script src="vendor/fullcalendar/7.1.3/themes/classic/global.js"></script>
    <script src="vendor/fullcalendar/7.1.3/locales/de/global.js"></script>
    <script src="vendor/fullcalendar/7.1.3/locales/zh-tw/global.js"></script>
    <script src="assets/js/chrono-calendar.js"></script>
    <script>
        (function () {
        const calendarRoot = document.getElementById('calendar');
        const calendarStatus = document.getElementById('calendarLoadStatus');
        const calendarStatusText = document.getElementById('calendarLoadStatusText');
        try {
        if (!window.ChronoCalendar || typeof window.ChronoCalendar.mount !== 'function') {
            throw new Error('Calendar renderer unavailable.');
        }
        window.ChronoCalendar.mount(document.getElementById('calendar'), {
            weekStart: {!! \Illuminate\Support\Js::from($dashboard->range['start']->format('Y-m-d')) !!},
            language: {!! \Illuminate\Support\Js::from($page->copy->language === 'zh' ? 'zh-tw' : $page->copy->language) !!},
            theme: {!! \Illuminate\Support\Js::from($page->theme) !!},
            events: {!! \Illuminate\Support\Js::from($dashboard->calendarEvents()) !!},
        });
        if (calendarStatus) { calendarStatus.hidden = true; }
        } catch {
            try { if (calendarRoot) { calendarRoot.replaceChildren(); } } catch {}
            if (calendarStatusText) { calendarStatusText.textContent = {!! \Illuminate\Support\Js::from($page->copy->text('DASHBOARD_CALENDAR_UNAVAILABLE')) !!}; }
            if (calendarStatus) { calendarStatus.hidden = false; }
        }
        }());
    </script>

@endsection
