@extends('chrono.layouts.app')

@section('content')
@php
    $dashboardRequestedTab = request()->query('view');
    $dashboardDefaultTab = request()->has('absence_year') || request()->has('holiday_year') || request()->query('holiday_inventory') === '1' ? 'absences' : 'calendar';
    $dashboardTab = in_array($dashboardRequestedTab, ['calendar', 'analysis', 'absences'], true) ? $dashboardRequestedTab : $dashboardDefaultTab;
    $dashboardTabQuery = ['calendar_date' => $dashboard->range['start']->format('Y-m-d')];
    if (isset($absenceYearData['year']) && is_string($absenceYearData['year']) && preg_match('/\A[0-9]{4}\z/D', $absenceYearData['year']) && (int) $absenceYearData['year'] >= 1) {
        $dashboardTabQuery['absence_year'] = (string) $absenceYearData['year'];
    }
    if (isset($holidayInventory['year']) && is_int($holidayInventory['year']) && $holidayInventory['year'] >= 1 && $holidayInventory['year'] <= 9999) {
        $dashboardTabQuery['holiday_year'] = (string) $holidayInventory['year'];
    }
    $dashboardDay = static function ($value): string {
        if (is_string($value) && preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/D', $value, $parts) && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return $parts[3].'.'.$parts[2].'.'.$parts[1];
        }
        return is_string($value) ? $value : '—';
    };
@endphp
{{-- Pinned official FullCalendar Standard assets, local to the public webroot. --}}
<link rel="stylesheet" href="vendor/fullcalendar/7.1.3/skeleton.css" />
<link rel="stylesheet" href="vendor/fullcalendar/7.1.3/themes/classic/theme.css" />
<link rel="stylesheet" href="vendor/fullcalendar/7.1.3/themes/classic/palette.css" />
    <div class="container mx-auto px-4 py-8 chrono-dashboard">
        <header class="page-header mb-4"><div class="row align-items-center"><div class="col"><h1 class="page-title"><x-chrono-icon name="chart-bar" size="24" /> Übersicht</h1></div><div class="col-auto"><img src="assets/illustrations/calendar-plan.png" class="chrono-page-illustration" alt="" width="88" height="76"></div><div class="col-auto"><x-chrono-help label="Berechnung der Auswertung"><p>{{ $page->copy->text('DASHBOARD_PERIOD_BASIS') }}</p></x-chrono-help></div></div></header>
        <div class="row row-cards mb-4" @if($dashboardTab !== 'calendar') hidden @endif>
            <div class="col-sm-4"><section class="card"><div class="card-body">
                <div class="text-secondary mb-2 chrono-stat-label"><x-chrono-icon name="calendar-stats" size="20" /> Diese Woche</div>
                <div data-report-period="week" data-report-presentation="{{ $dashboard->presentation('week') }}" class="h1 mb-0">{{ $dashboard->value('week_hours', 'week') }}</div>
            </div></section></div>
            <div class="col-sm-4"><section class="card"><div class="card-body">
                <div class="text-secondary mb-2 chrono-stat-label"><x-chrono-icon name="calendar" size="20" /> Diesen Monat</div>
                <div data-report-period="month" data-report-presentation="{{ $dashboard->presentation('month') }}" class="h1 mb-0">{{ $dashboard->value('month_hours', 'month') }}</div>
            </div></section></div>
            <div class="col-sm-4"><section class="card"><div class="card-body">
                <div class="d-flex align-items-center gap-2 text-secondary mb-2"><x-chrono-icon name="clock" size="20" /> Saldo <x-chrono-help label="Berechnung des Saldos"><p>{{ $page->copy->text('DASHBOARD_OVERTIME_INFO') }}</p>
                    @if (isset($dashboard->statistics['recorded_as_of_day']) && array_key_exists('recorded_as_of_overtime', $dashboard->statistics))
                    <p data-report-period="all" data-report-presentation="{{ $dashboard->presentation('all') }}">{{ sprintf($page->copy->text('DASHBOARD_RECORDED_AS_OF'), $dashboard->statistics['recorded_as_of_day'], $dashboard->value('recorded_as_of_overtime', 'all')) }}</p>
                    @endif
                    <p data-report-period="year" data-report-presentation="{{ $dashboard->presentation('year') }}">{{ sprintf($page->copy->text('DASHBOARD_OVER_HOURS_YEAR'), $dashboard->value('year_overtime', 'year')) }}</p>
                    <p>Regelarbeitszeit: {{ $dashboard->statistics['regular_hours'] }}. Wochen mit Buchungen: {{ $dashboard->value('weeks_count', 'all') }}.</p>
                </x-chrono-help></div>
                <div data-report-period="all" data-report-presentation="{{ $dashboard->presentation('all') }}" class="h1 mb-0">{{ $dashboard->value('total_overtime', 'all') }}</div>
            </div></section></div>
        </div>

        <nav class="nav nav-tabs chrono-dashboard-tabs mb-4" aria-label="Ansicht der Übersicht">
            <a class="nav-link {{ $dashboardTab === 'calendar' ? 'active' : '' }}" href="{{ 'dashboard.php?'.http_build_query(['view'=>'calendar', ...$dashboardTabQuery]) }}" @if ($dashboardTab === 'calendar') aria-current="page" @endif><x-chrono-icon name="calendar" size="20" /> Kalender</a>
            <a class="nav-link {{ $dashboardTab === 'analysis' ? 'active' : '' }}" href="{{ 'dashboard.php?'.http_build_query(['view'=>'analysis', ...$dashboardTabQuery]) }}" @if ($dashboardTab === 'analysis') aria-current="page" @endif><x-chrono-icon name="chart-bar" size="20" /> Auswertung</a>
            <a class="nav-link {{ $dashboardTab === 'absences' ? 'active' : '' }}" href="{{ 'dashboard.php?'.http_build_query(['view'=>'absences', ...$dashboardTabQuery]) }}" @if ($dashboardTab === 'absences') aria-current="page" @endif><x-chrono-icon name="calendar-check" size="20" /> Abwesenheiten</a>
        </nav>
        <div id="dashboard-calendar" data-dashboard-pane="calendar" @if ($dashboardTab !== 'calendar') hidden @endif>
        <!-- Calendar Section -->
        <section class="card mb-4">
            <div class="card-header">
                <h2 class="card-title">{{ $page->copy->text('DASHBOARD_CALENDAR') }}</h2>
                <div class="card-actions chrono-calendar-toolbar">
                    <a id="prevWeekBtn" href="{{ $dashboard->link($dashboard->range['previous']) }}" class="btn btn-outline-secondary btn-sm">{{ $page->copy->text('BUTTON_PREV_WEEK') }}</a>
                    <a id="todayBtn" href="{{ $dashboard->link($dashboard->range['today']) }}" class="btn btn-outline-secondary btn-sm">{{ $page->copy->text('BUTTON_TODAY') }}</a>
                    <a id="nextWeekBtn" href="{{ $dashboard->link($dashboard->range['next']) }}" class="btn btn-outline-secondary btn-sm">{{ $page->copy->text('BUTTON_NEXT_WEEK') }}</a>
                </div>
            </div>
            <div class="card-body">
                <p id="calendarLoadStatus" class="text-sm opacity-70 mb-3" role="status"><span id="calendarLoadStatusText">{{ $page->copy->text('DASHBOARD_CALENDAR_LOADING') }}</span> <a class="link" href="attendance.php">{{ $page->copy->text('DASHBOARD_CALENDAR_FALLBACK') }}</a></p>
                <noscript><p class="text-sm opacity-70 mb-3">{{ $page->copy->text('DASHBOARD_CALENDAR_NO_SCRIPT') }}</p></noscript>
                <div id="calendar" class="calendar-container"></div>
            </div>
        </section>
        </div>
        <div id="dashboard-analysis" data-dashboard-pane="analysis" @if ($dashboardTab !== 'analysis') hidden @endif>
            <section class="card">
                <div class="table-responsive"><table class="table table-vcenter mb-0">
                    <thead><tr><th>Zeitraum <x-chrono-help label="Was wird angerechnet?"><p>Angerechnet sind abgeschlossene Arbeitszeiten und Abwesenheitsgutschriften. Der Anteil am Soll ist auf 0 bis 100 % begrenzt und bewertet keine Produktivität.</p><p>{{ $page->copy->text('DASHBOARD_WEEKLY_PRODUCTIVITY_INFO') }}</p><p>{{ $page->copy->text('DASHBOARD_PRODUCTIVITY_SCORE_INFO') }}</p></x-chrono-help></th><th>Woche <span class="d-block fw-normal text-secondary">{{ $dashboardDay($dashboard->statistics['week_from']) }} bis {{ $dashboardDay($dashboard->statistics['week_to']) }}</span></th><th>Monat <span class="d-block fw-normal text-secondary">{{ $dashboardDay($dashboard->statistics['month_from']) }} bis {{ $dashboardDay($dashboard->statistics['month_to']) }}</span></th></tr></thead>
                    <tbody>
                        <tr><th scope="row">Angerechnet</th><td data-report-period="week" data-report-presentation="{{ $dashboard->presentation('week') }}">{{ $dashboard->value('week_hours', 'week') }}</td><td data-report-period="month" data-report-presentation="{{ $dashboard->presentation('month') }}">{{ $dashboard->value('month_hours', 'month') }}</td></tr>
                        <tr><th scope="row" class="fw-normal">Davon Arbeit</th><td data-report-period="week" data-report-presentation="{{ $dashboard->presentation('week') }}"><span data-report-component="work">{{ $dashboard->value('week_work_hours', 'week') }}</span></td><td data-report-period="month" data-report-presentation="{{ $dashboard->presentation('month') }}"><span data-report-component="work">{{ $dashboard->value('month_work_hours', 'month') }}</span></td></tr>
                        <tr><th scope="row" class="fw-normal">Davon Abwesenheit</th><td data-report-period="week" data-report-presentation="{{ $dashboard->presentation('week') }}"><span data-report-component="absence">{{ $dashboard->value('week_absence_hours', 'week') }}</span></td><td data-report-period="month" data-report-presentation="{{ $dashboard->presentation('month') }}"><span data-report-component="absence">{{ $dashboard->value('month_absence_hours', 'month') }}</span></td></tr>
                        <tr><th scope="row" class="fw-normal">Monatssoll</th><td>—</td><td data-report-period="month" data-report-presentation="{{ $dashboard->presentation('month') }}">{{ $dashboard->value('planned_month', 'month') }}</td></tr>
                        <tr><th scope="row" class="fw-normal">Rest zum Wochensoll</th><td data-report-period="week" data-report-presentation="{{ $dashboard->presentation('week') }}">{{ $dashboard->value('remaining_week', 'week') }}</td><td>—</td></tr>
                        <tr><th scope="row" class="fw-normal">Tagesdurchschnitt</th><td>—</td><td data-report-period="month" data-report-presentation="{{ $dashboard->presentation('month') }}">{{ $dashboard->value('average_daily_hours', 'month') }}</td></tr>
                        <tr><th scope="row" class="fw-normal">Tage mit Buchungen</th><td>—</td><td data-report-period="month" data-report-presentation="{{ $dashboard->presentation('month') }}">{{ $dashboard->value('worked_days', 'month') }}</td></tr>
                        <tr><th scope="row" class="fw-normal">Anteil am Soll</th><td data-report-period="week" data-report-presentation="{{ $dashboard->presentation('week') }}">{{ $dashboard->value('weekly_productivity', 'week') }}</td><td data-report-period="month" data-report-presentation="{{ $dashboard->presentation('month') }}">{{ $dashboard->value('monthly_productivity', 'month') }}</td></tr>
                    </tbody>
                </table></div>
            </section>
        </div>
        <div id="dashboard-absences" data-dashboard-pane="absences" @if ($dashboardTab !== 'absences') hidden @endif>
        @if ($absenceYearSupported ?? false)
            @include('chrono.partials.own-absence-year')
        @endif

        @if ($holidayInventoryUnavailable ?? false)
        <section id="holiday-inventory" class="card mb-4"><div class="card-header"><h2 class="card-title">Feiertage</h2></div><div class="card-body"><p role="status">Feiertage derzeit nicht verfügbar. Bitte später erneut laden.</p></div></section>
        @elseif (($holidayInventory ?? null) !== null)
        <section id="holiday-inventory" class="card mb-4">
            <div class="card-header d-flex flex-wrap align-items-end gap-3">
                <h2 class="card-title"><x-chrono-icon name="calendar-check" size="20" /> Feiertage {{ $holidayInventory['year'] }}</h2>
                <x-chrono-help label="Hinweise zu den Feiertagen"><p>{{ $page->copy->text('HOLIDAY_INVENTORY_ACTION') }}</p><p>{{ $page->copy->text('HOLIDAY_INVENTORY_LIMIT') }}</p><p>{{ $page->copy->text('HOLIDAY_INVENTORY_UNVERIFIED') }}</p></x-chrono-help>
                <form method="get" action="dashboard.php#holiday-inventory" class="d-flex flex-wrap align-items-end gap-2 ms-auto">
                    <input type="hidden" name="calendar_date" value="{{ $dashboard->range['start']->format('Y-m-d') }}">
                    @if(isset($dashboardTabQuery['absence_year']))<input type="hidden" name="absence_year" value="{{ $dashboardTabQuery['absence_year'] }}">@endif
                    <div><label for="holiday-year" class="form-label">Jahr</label><input id="holiday-year" name="holiday_year" type="number" min="1" max="9999" step="1" required value="{{ $holidayInventory['year'] }}" class="form-control"></div>
                    <button type="submit" class="btn btn-outline-secondary">Anzeigen</button>
                </form>
            </div>
            <div class="card-body">
                @if ($holidayInventory['selection_invalid'])<p class="text-warning mb-3" role="status">Ungültiges Jahr. Bitte 1 bis 9999 wählen.</p>@endif
                @if ($holidayInventory['truncated'] || $holidayInventory['invalid_dates'])<div class="text-warning mb-3" role="status">Feiertagsdaten bitte prüfen. <x-chrono-help><p>{{ $page->copy->text('HOLIDAY_INVENTORY_DATA_ISSUE') }}</p></x-chrono-help></div>@endif
                @if (($holidayInventory['unassigned_year_rows'] ?? 0) > 0)<div class="text-warning mb-3" role="status">{{ $holidayInventory['unassigned_year_rows'] }} Einträge ohne gültiges Jahr. <x-chrono-help><p>{{ sprintf($page->copy->text('HOLIDAY_INVENTORY_UNASSIGNED_ROWS'), $holidayInventory['unassigned_year_rows']) }}</p></x-chrono-help></div>@endif
                @if ($holidayInventory['rows'] === [])<div class="d-flex align-items-center gap-3 text-secondary"><x-chrono-icon name="calendar-off" size="28" /><p class="mb-0">Für dieses Jahr sind keine Feiertage gespeichert.</p></div>@else
                <div class="table-responsive"><table class="table table-vcenter mb-0" aria-label="{{ $page->copy->text('HOLIDAY_INVENTORY_TITLE') }}"><thead><tr><th>Datum</th><th>Feiertag</th></tr></thead><tbody>
                    @foreach ($holidayInventory['rows'] as $holiday)<tr><td>{{ $dashboardDay($holiday['datum']) }}</td><td>{{ $holiday['name'] }}</td></tr>@endforeach
                </tbody></table></div>
                @endif
                @if (array_key_exists('stored_years', $holidayInventory))
                <details class="mt-3"><summary>Andere Jahre</summary><div class="mt-2 d-flex flex-wrap align-items-center gap-3">
                    @foreach ($holidayInventory['stored_years'] as $storedYear)<a class="link" href="{{ 'dashboard.php?'.http_build_query([...$dashboardTabQuery,'view'=>'absences','holiday_year'=>$storedYear['year']]).'#holiday-inventory' }}">{{ $storedYear['year'] }} ({{ $storedYear['count'] }})</a>@endforeach
                    @if ($holidayInventory['stored_years'] === [])<span class="text-secondary">Keine weiteren Jahre gespeichert.</span>@endif
                    <x-chrono-help><p>{{ $page->copy->text('HOLIDAY_INVENTORY_YEARS_NOTE') }}</p>@if ($holidayInventory['stored_years_truncated'])<p>{{ $page->copy->text('HOLIDAY_INVENTORY_YEARS_LIMITED') }}</p>@endif</x-chrono-help>
                </div></details>
                @endif
            </div>
        </section>
        @endif

        </div>
    </div>

    <!-- Schedule Modal -->
    <dialog class="modal" id="scheduleModal" aria-labelledby="scheduleModalLabel">
        <div class="modal-box">
            <h3 class="modal-title mb-3" id="scheduleModalLabel">{{ $page->copy->text('MODAL_TITLE_SCHEDULE') }}</h3>
            <dl class="row">
                <dt class="col-sm-4">{{ $page->copy->text('FORM_START') }}</dt><dd class="col-sm-8" id="startTime"></dd>
                <dt class="col-sm-4">{{ $page->copy->text('FORM_END') }}</dt><dd class="col-sm-8" id="endTime"></dd>
                <dt class="col-sm-4">{{ $page->copy->text('FORM_DESCRIPTION') }}</dt><dd class="col-sm-8" id="eventDescription"></dd>
                <dt class="col-sm-4">{{ $page->copy->text('FORM_LOCATION') }}</dt><dd class="col-sm-8" id="eventLocation"></dd>
            </dl>
            <div class="modal-action">
                <button class="btn" data-calendar-close>{{ $page->copy->text('BUTTON_CLOSE') }}</button>
            </div>
        </div>
    </dialog>

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
            missingEndLabel: {!! \Illuminate\Support\Js::from($page->copy->text('DASHBOARD_CALENDAR_MISSING_END')) !!},
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
