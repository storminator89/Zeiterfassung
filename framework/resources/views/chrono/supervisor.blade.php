@extends('chrono.layouts.app')

@section('content')
<link rel="stylesheet" href="assets/css/supervisor_ui.css?v={{ substr(hash_file('sha256', public_path('assets/css/supervisor_ui.css')), 0, 12) }}">
{{-- Native supervisor UI only. Route/chrome and existing Chart asset admission remain separate; no executable CDN is added. --}}

<div class="chrono-supervisor">
    <div class="container mx-auto px-4 py-8">
            <header class="page-header mb-4"><h1 class="page-title"><x-chrono-icon name="users" size="24" /> Team</h1></header>

            @if (in_array($page->role, ['supervisor', 'admin'], true) && isset($supervisor->report['team_status']))
                <section class="card mb-4" aria-labelledby="teamStatusTitle">
                    <div class="card-header"><h2 id="teamStatusTitle" class="card-title">{{ $supervisor->text('TEAM_STATUS_TITLE') }}</h2><x-chrono-help><p class="text-sm opacity-70">{{ $supervisor->text('TEAM_STATUS_SNAPSHOT_NOTE') }} <time datetime="{{ $supervisor->report['team_status']['loaded_at'] }}">{{ $supervisor->report['team_status']['loaded_at'] }}</time></p></x-chrono-help></div><div class="card-body">

                        <ul class="chrono-team-presence" aria-label="{{ $supervisor->text('TEAM_STATUS_TITLE') }}">
                            @forelse ($supervisor->report['team_status']['people'] as $person)
                                @php
                                    $stateLabel = match ($person['state']) {
                                        'working' => 'TEAM_STATUS_WORKING', 'paused' => 'TEAM_STATUS_PAUSED',
                                        'absent' => 'TEAM_STATUS_ABSENT', default => 'TEAM_STATUS_UNKNOWN',
                                    };
                                @endphp
                                <li><span>{{ $person['username'] }}</span><span class="badge bg-secondary-lt">{{ $supervisor->text($stateLabel) }}</span></li>
                            @empty
                                <li class="text-secondary">{{ $supervisor->text('TEAM_STATUS_EMPTY') }}</li>
                            @endforelse
                        </ul>
                    </div>
                </section>
            @endif

            @if (! in_array($page->role, ['supervisor', 'admin'], true))
                <div class="alert alert-warning shadow-lg">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>{{ $supervisor->text('NOT_SUPERVISOR_MESSAGE') }}</span>
                    </div>
                </div>
            @elseif ($supervisor->periodUnavailable)
                <div class="alert alert-error mb-4" role="alert">{{ $supervisor->text('SUPERVISOR_PERIOD_UNAVAILABLE') }} <a class="link" href="{{ $supervisor->periodResetUrl() }}">{{ $supervisor->text('SUPERVISOR_PERIOD_CLEAR') }}</a></div>
            @elseif (! $supervisor->report['available'])
                @if ($supervisor->periodInvalid)<div class="alert alert-error mb-4" role="alert">{{ $supervisor->text('SUPERVISOR_PERIOD_INVALID') }}</div>
                @elseif ($supervisor->period !== null)<p class="text-sm opacity-70 mb-4">{{ $supervisor->text('SUPERVISOR_PERIOD_NOT_APPLIED') }}</p>@endif
                @if ($supervisor->period !== null || $supervisor->periodInvalid)<p class="mb-4"><a class="link" href="{{ $supervisor->periodResetUrl() }}">{{ $supervisor->text('SUPERVISOR_PERIOD_CLEAR') }}</a></p>@endif
                @if ($supervisor->report['absence_conflicts'] !== [])
                <div class="alert alert-warning mb-4" role="alert"><span><strong>Klärung offen.</strong> Arbeitszeit und ganztägige Abwesenheit überschneiden sich im freigegebenen Berichtsumfang. Diese Auswertung enthält deshalb keine abschließenden Summen. Bitte die betroffenen Originaleinträge in der <a class="link" href="history.php">Historie</a> prüfen und die Korrektur durch die betroffene Person mit anschließender Vier-Augen-Freigabe klären. Die <a class="link" href="index.php">eigene Arbeitszeitliste</a> und <a class="link" href="adjustments.php">Korrekturanträge</a> bleiben verfügbar; es wurden keine Zeiten gelöscht oder gekürzt.</span></div>
                @else
                <div class="alert alert-warning mb-4" role="alert"><span><strong>Datenqualität: {{ count($supervisor->report['invalid_records']) }} ungültige Zeiteinträge.</strong> Diese Auswertung enthält deshalb keine abschließenden Summen. Bitte die Originaleinträge in der <a class="link" href="history.php">Historie</a> prüfen und durch die betroffene Person mit anschließender Vier-Augen-Freigabe klären. Die <a class="link" href="index.php">eigene Arbeitszeitliste</a> und <a class="link" href="adjustments.php">Korrekturanträge</a> bleiben verfügbar; es wurden keine Zeiten gelöscht oder gekürzt.</span></div>
                @endif
            @else
                <!-- Time Records Table Section -->
                <div class="mb-4">
                    <div class="card">
                        <div class="card-header"><h2 class="card-title">{{ $supervisor->text('ACTUAL_WORKED_TIMES') }}</h2><x-chrono-help><p class="text-sm opacity-70 mb-4">{{ $supervisor->text('SUPERVISOR_PERIOD_NOTE') }}</p></x-chrono-help></div>
                        <div class="card-body">
                            <!-- Search and Filter Bar -->

                            @if ($supervisor->periodInvalid)<div class="alert alert-error mb-4" role="alert">{{ $supervisor->text('SUPERVISOR_PERIOD_INVALID') }}</div>@endif
                            <form method="get" action="supervisor.php" class="chrono-team-filters mb-3">
                                @foreach ($supervisor->reviewQuery() as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                                @if ($supervisor->absenceWeek !== null)<input type="hidden" name="team_date" value="{{ $supervisor->absenceWeek['from'] }}"><input type="hidden" name="team_page" value="{{ $supervisor->absenceWeek['page'] }}">@endif
                                <input type="hidden" name="lang" value="{{ $supervisor->language }}">
                                <label class="chrono-team-period-toggle"><input class="form-check-input" type="checkbox" name="period" value="1" @checked($supervisor->period !== null || $supervisor->periodInvalid)><span>Zeitraum eingrenzen</span></label>
                                <div class="chrono-team-date"><label class="form-label" for="supervisor-period-from">{{ $supervisor->text('SUPERVISOR_PERIOD_FROM') }}</label><input class="form-control" type="date" id="supervisor-period-from" name="from" min="0001-01-01" max="9999-12-31" value="{{ $supervisor->period['from'] ?? '' }}"></div>
                                <div class="chrono-team-date"><label class="form-label" for="supervisor-period-to">{{ $supervisor->text('SUPERVISOR_PERIOD_TO') }}</label><input class="form-control" type="date" id="supervisor-period-to" name="to" min="0001-01-01" max="9999-12-31" value="{{ $supervisor->period['to'] ?? '' }}"></div>
                                @if ($supervisor->period !== null || $supervisor->periodInvalid)<a class="btn btn-outline-secondary align-self-end" href="{{ $supervisor->periodResetUrl() }}">{{ $supervisor->text('SUPERVISOR_PERIOD_CLEAR') }}</a>@endif
                                <div class="chrono-team-search"><label class="form-label" for="userSearchInput">Person</label>
                                    <div class="input-group">
                                        <input type="search"
                                            id="userSearchInput"
                                            name="user_search"
                                            value="{{ $supervisor->search }}"
                                            class="form-control"
                                            aria-label="{{ $supervisor->text('SEARCH_USERS') }}"
                                            placeholder="Name suchen">
                                        <button type="submit" class="btn btn-outline-secondary" aria-label="{{ $supervisor->text('SEARCH_USERS') }}">
                                            <i class="fas fa-search" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="6"/><path d="m15 15 5 5"/></svg></i>
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <div class="overflow-x-auto">
                                <table class="table w-full">
                                    <thead>
                                        <tr>
                                            <th class="w-8"></th> <!-- Expand/Collapse Icon -->
                                            <th>{{ $supervisor->language === 'de' ? 'Person' : $supervisor->text('TABLE_HEADER_USERNAME') }}</th>
                                            <th>{{ $supervisor->text('TABLE_HEADER_DATE') }}</th>
                                            <th>{{ $supervisor->language === 'de' ? 'Arbeitszeit' : $supervisor->text('TABLE_HEADER_TOTAL_DURATION') }}</th>
                                            <th>{{ $supervisor->language === 'de' ? 'Pause' : $supervisor->text('TABLE_HEADER_TOTAL_BREAK') }}</th>
                                            <th>{{ $supervisor->text('TABLE_HEADER_LOCATION') }}</th>
                                            <th>{{ $supervisor->language === 'de' ? 'Saldo' : $supervisor->text('TABLE_HEADER_OVERTIME') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="timeRecordsBody">
                                        @forelse ($supervisor->report['groups'] as $group)
                                            <tr class="group-header hover:bg-base-200 cursor-pointer" data-user-id="{{ $group['user_id'] }}" data-date="{{ $group['date'] }}">
                                                <td><button type="button" class="btn btn-outline-secondary btn-sm chrono-team-expand" aria-expanded="false" aria-controls="{{ $group['detail_id'] }}" aria-label="Buchungen von {{ $group['username'] }} am {{ $group['date_formatted'] }}"><i class="fas fa-chevron-right transition-transform duration-200" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m9 5 7 7-7 7"/></svg></i></button></td>
                                                <td>{{ $group['username'] }}</td>
                                                <td>{{ $group['date_formatted'] }}</td>
                                                <td>{{ $group['duration_formatted'] }}</td>
                                                <td>{{ $group['total_break'] }} min</td>
                                                <td>{{ $supervisor->groupLocation($group) }}</td>
                                                <td class="{{ $group['overtime_minutes'] >= 0 ? 'text-success' : 'text-error' }}">{{ $group['overtime_formatted'] }}</td>
                                            </tr>
                                            <tr id="{{ $group['detail_id'] }}" class="detail-row hidden" data-parent="{{ $group['user_id'] }}_{{ $group['date'] }}" hidden>
                                                <td colspan="7" class="p-0">
                                                    <div class="bg-base-200/50 p-4">
                                                        <table class="table w-full">
                                                            <thead><tr>
                                                                <th>{{ $supervisor->text('TABLE_HEADER_START_TIME') }}</th>
                                                                <th>{{ $supervisor->text('TABLE_HEADER_END_TIME') }}</th>
                                                                <th>{{ $supervisor->text('TABLE_HEADER_DURATION') }}</th>
                                                                <th>{{ $supervisor->text('TABLE_HEADER_BREAK') }}</th>
                                                                <th>{{ $supervisor->text('TABLE_HEADER_LOCATION') }}</th>
                                                                <th>{{ $supervisor->text('TABLE_HEADER_COMMENT') }}</th>
                                                            </tr></thead>
                                                            <tbody>
                                                                @foreach ($group['entries'] as $entry)
                                                                <tr>
                                                                    <td>{{ $entry['start_formatted'] }}</td>
                                                                    <td>{{ $entry['end_formatted'] }}</td>
                                                                    <td>{{ $entry['duration_formatted'] }}</td>
                                                                    <td>{{ $entry['pause'] }} min</td>
                                                                    <td>{{ $supervisor->locationLabel($entry['standort']) }}</td>
                                                                    <td>{{ $entry['beschreibung'] }}</td>
                                                                </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="7" class="text-center opacity-70">{{ $supervisor->text('NO_ENTRIES_FOUND') }}</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @if ($supervisor->report['total_pages'] > 1)
                    <div class="flex justify-center mt-4">
                        <div class="btn-group">
                            @if ($supervisor->report['page'] > 1)
                                <a href="{{ $supervisor->pageUrl($supervisor->report['page'] - 1) }}" class="btn btn-sm">&laquo;</a>
                            @else
                                <span class="btn btn-sm btn-disabled" aria-disabled="true">&laquo;</span>
                            @endif
                            @php
                                // Native pagination window: presentation only, no report arithmetic.
                                $maxVisible = 5;
                                $start = max(1, min($supervisor->report['page'] - (int) floor($maxVisible / 2), $supervisor->report['total_pages'] - $maxVisible + 1));
                                $end = min($start + $maxVisible - 1, $supervisor->report['total_pages']);
                            @endphp
                            @if ($start > 1)
                                <a href="{{ $supervisor->pageUrl(1) }}" class="btn btn-sm">1</a>
                                @if ($start > 2)<span class="btn btn-sm btn-disabled">...</span>@endif
                            @endif
                            @for ($i = $start; $i <= $end; $i++)
                                <a href="{{ $supervisor->pageUrl($i) }}" class="btn btn-sm{{ $i === $supervisor->report['page'] ? ' btn-active' : '' }}" @if ($i === $supervisor->report['page']) aria-current="page" @endif>{{ $i }}</a>
                            @endfor
                            @if ($end < $supervisor->report['total_pages'])
                                @if ($end < $supervisor->report['total_pages'] - 1)<span class="btn btn-sm btn-disabled">...</span>@endif
                                <a href="{{ $supervisor->pageUrl($supervisor->report['total_pages']) }}" class="btn btn-sm">{{ $supervisor->report['total_pages'] }}</a>
                            @endif
                            @if ($supervisor->report['page'] < $supervisor->report['total_pages'])
                                <a href="{{ $supervisor->pageUrl($supervisor->report['page'] + 1) }}" class="btn btn-sm">&raquo;</a>
                            @else
                                <span class="btn btn-sm btn-disabled" aria-disabled="true">&raquo;</span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
                <details class="chrono-disclosure mb-4" id="team-statistics"><summary>Zeitkonten</summary>
                    <div class="chrono-team-accounts">
                        <div class="chrono-team-account-toolbar">
                            <label class="chrono-team-account-search"><span class="visually-hidden">Person suchen</span><input type="search" id="searchInput" class="form-control search-input" placeholder="Person suchen" aria-label="Person suchen"></label>
                            <div class="btn-group" aria-label="Saldo filtern">
                                <button type="button" class="btn btn-outline-secondary filter-badge active" data-filter="all" aria-pressed="true">Alle</button>
                                <button type="button" class="btn btn-outline-secondary filter-badge" data-filter="overtime" aria-pressed="false">Plus</button>
                                <button type="button" class="btn btn-outline-secondary filter-badge" data-filter="undertime" aria-pressed="false">Minus</button>
                            </div>
                            <x-chrono-help label="Berechnung der Zeitkonten"><p>{{ $page->copy->text('DASHBOARD_OVERTIME_INFO') }}</p></x-chrono-help>
                        </div>
                        <div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th>Person</th><th>Saldo</th><th>Std. / Tag</th><th class="text-end">Aktionen</th></tr></thead>
                            <tbody id="overtimeCards">
                            @forelse ($supervisor->report['overtime'] as $userId => $data)
                                <tr class="stats-card" data-overtime-minutes="{{ $data['overtime_minutes'] }}"><td class="chrono-team-person">{{ $data['username'] }}</td><td class="{{ substr($data['ueberstunden'], 0, 1) === '-' ? 'text-error' : 'text-success' }}">{{ $data['ueberstunden'] }}</td><td>{{ $supervisor->report['details'][$userId]['regelarbeitszeit'] ?? '—' }}</td><td class="text-end"><button type="button" class="btn btn-outline-secondary btn-sm" onclick="showDetails('{{ $userId }}')">Details</button></td></tr>
                            @empty
                                <tr><td colspan="4" class="text-secondary">{{ $supervisor->text('NO_ENTRIES_FOUND') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table></div>
                        <p id="teamAccountEmpty" class="text-secondary mt-3" role="status" hidden>Keine Personen für diesen Filter.</p>
                        <details class="chrono-team-diagrams mt-3" id="team-diagrams"><summary>Diagramme anzeigen</summary>
                            <div class="row g-3 mt-1">
                                <section class="col-lg-6"><h3 class="h4">Saldo pro Person</h3><div class="chart-container chrono-team-chart"><canvas id="overtimeChart"></canvas></div></section>
                                <section class="col-lg-6"><h3 class="h4">Regelarbeitszeit pro Tag</h3><div class="chart-container chrono-team-chart"><canvas id="regularHoursChart"></canvas></div></section>
                            </div>
                        </details>
                    </div>
                </details>
            @endif
            @if ($supervisor->absenceWeek !== null)
                @php
                    $absenceWeek = $supervisor->absenceWeek;
                @endphp
                <details class="chrono-disclosure mb-6" @if (request()->has('team_date') || request()->has('team_page') || $absenceWeek['incomplete'] || $absenceWeek['truncated']) open @endif><summary id="teamAbsenceTitle">{{ $supervisor->text('TEAM_ABSENCE_TITLE') }}</summary>
                    <div class="card-body">
                        <x-chrono-help><p class="text-sm opacity-70">{{ $supervisor->text('TEAM_ABSENCE_NOTE') }}</p></x-chrono-help>
                        <p class="text-sm">{{ (new \DateTimeImmutable($absenceWeek['from']))->format('d.m.Y') }} bis {{ (new \DateTimeImmutable($absenceWeek['through']))->format('d.m.Y') }}</p>
                        <form method="get" action="supervisor.php" class="d-flex flex-wrap align-items-end gap-3 my-4">
                            <input type="hidden" name="lang" value="{{ $supervisor->language }}">
                            <input type="hidden" name="user_search" value="{{ $supervisor->search }}">
                            @foreach ($supervisor->reviewQuery() as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                            @foreach ($supervisor->periodQuery() as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                            <input type="hidden" name="page" value="{{ $supervisor->report['page'] }}">
                            <div><label class="form-label" for="team-absence-date">{{ $supervisor->text('TEAM_ABSENCE_DATE') }}</label><input class="form-control" type="date" id="team-absence-date" name="team_date" value="{{ $absenceWeek['from'] }}"></div>
                            <button class="btn btn-primary align-self-end">{{ $supervisor->text('TEAM_ABSENCE_SHOW') }}</button>
                        </form>
                        <nav class="flex flex-wrap gap-3 mb-4" aria-label="{{ $supervisor->text('TEAM_ABSENCE_NAV') }}">
                            <a class="btn btn-outline-secondary" href="{{ $supervisor->absenceWeekUrl($absenceWeek['previous']) }}">{{ $supervisor->text('TEAM_ABSENCE_PREVIOUS') }}</a>
                            <a class="btn btn-outline-secondary" href="{{ $supervisor->absenceWeekUrl($absenceWeek['today']) }}">{{ $supervisor->text('TEAM_ABSENCE_TODAY') }}</a>
                            <a class="btn btn-outline-secondary" href="{{ $supervisor->absenceWeekUrl($absenceWeek['next']) }}">{{ $supervisor->text('TEAM_ABSENCE_NEXT') }}</a>
                        </nav>
                        @if ($absenceWeek['incomplete'] || $absenceWeek['truncated'])<p class="alert alert-warning mb-4" role="status">{{ $supervisor->text('TEAM_ABSENCE_INCOMPLETE') }}</p>@endif
                        <div class="overflow-x-auto"><table class="table table-zebra w-full">
                            <caption class="text-sm text-left mb-2">{{ $supervisor->text('TEAM_ABSENCE_LEGEND') }}</caption>
                            <thead><tr><th scope="col">{{ $supervisor->language === 'de' ? 'Person' : $supervisor->text('TABLE_HEADER_USERNAME') }}</th>@foreach ($absenceWeek['days'] as $day)<th scope="col"><time datetime="{{ $day }}">{{ (new \DateTimeImmutable($day))->format('d.m.') }}</time></th>@endforeach</tr></thead>
                            <tbody>@forelse ($absenceWeek['people'] as $person)<tr><th scope="row">{{ $person['username'] }}</th>@foreach ($person['days'] as $absent)<td>{{ $absent ? $supervisor->text('TEAM_ABSENCE_MARKER') : '—' }}</td>@endforeach</tr>@empty<tr><td colspan="8">{{ $supervisor->text('TEAM_STATUS_EMPTY') }}</td></tr>@endforelse</tbody>
                        </table></div>
                        @if ($absenceWeek['pages'] > 1)<p class="text-sm my-3">{{ $supervisor->text('TEAM_ABSENCE_PAGE') }} {{ $absenceWeek['page'] }} / {{ $absenceWeek['pages'] }}</p>
                        <nav class="flex flex-wrap gap-3" aria-label="{{ $supervisor->text('TEAM_ABSENCE_PEOPLE') }}">
                            @if ($absenceWeek['page'] > 1)<a class="btn btn-outline-secondary" href="{{ $supervisor->absenceWeekUrl($absenceWeek['from'], $absenceWeek['page'] - 1) }}">{{ $supervisor->text('TEAM_ABSENCE_PEOPLE_PREVIOUS') }}</a>@endif
                            @if ($absenceWeek['page'] < $absenceWeek['pages'])<a class="btn btn-outline-secondary" href="{{ $supervisor->absenceWeekUrl($absenceWeek['from'], $absenceWeek['page'] + 1) }}">{{ $supervisor->text('TEAM_ABSENCE_PEOPLE_NEXT') }}</a>@endif
                        </nav>@endif
                    </div>
                </details>
            @endif

            <details class="chrono-disclosure mb-6" @if ($supervisor->review !== null) open @endif><summary>Buchungen prüfen</summary><div class="mt-4">
            @include('chrono.partials.team-time-review')
            </div></details>


        </div>

    @if (in_array($page->role, ['supervisor', 'admin'], true) && $supervisor->report['available'])
    <!-- Enhanced Modal -->
    <dialog id="details_modal" class="modal">
        <div class="modal-box">
            <div class="modal-header d-flex justify-content-between align-items-center mb-3">
                <h3 class="modal-title" id="modal_title">Detaillierte Übersicht</h3>
                <form method="dialog">
                    <button type="submit" class="btn-close" aria-label="Schließen"></button>
                </form>
            </div>
            <x-chrono-help><p class="text-sm opacity-70 mt-2" style="white-space: normal;">{{ $page->copy->text('DASHBOARD_OVERTIME_INFO') }}</p></x-chrono-help>
            <div id="modal_content" class="space-y-6">
                <!-- Content will be dynamically inserted here -->
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>Schließen</button>
        </form>
    </dialog>

    <script @if ($page->cspNonce !== null) nonce="{{ $page->cspNonce }}" @endif>
function escapeHTML(value) {
    const node = document.createElement('span');
    node.textContent = String(value ?? '');
    return node.innerHTML;
}

        const detaillierteDaten = {{ \Illuminate\Support\Js::from($supervisor->report['details']) }};

        function showDetails(userId) {
            const modal = document.getElementById('details_modal');
            const modalContent = document.getElementById('modal_content');
            const modalTitle = document.getElementById('modal_title');
            const userData = detaillierteDaten[userId];

            if (userData) {
                modalTitle.textContent = `Übersicht für ${userData.username}`;
                modalContent.innerHTML = `
                    <dl class="row mb-0">
                        <dt class="col-sm-7 text-secondary">Regelarbeitszeit pro Tag</dt><dd class="col-sm-5">${escapeHTML(userData.regelarbeitszeit)} h</dd>
                        <dt class="col-sm-7 text-secondary">Gearbeitete Tage</dt><dd class="col-sm-5">${escapeHTML(userData.total_days)}</dd>
                        <dt class="col-sm-7 text-secondary">Arbeitsstunden insgesamt</dt><dd class="col-sm-5">${escapeHTML(userData.total_hours.toFixed(2))} h</dd>
                        <dt class="col-sm-7 text-secondary">Durchschnitt pro Tag</dt><dd class="col-sm-5">${escapeHTML(userData.avg_hours_per_day.toFixed(2))} h</dd>
                        <dt class="col-sm-7 text-secondary">Saldo</dt><dd class="col-sm-5">${escapeHTML(userData.ueberstunden)}</dd>
                    </dl>
                `;
            } else {
                modalContent.innerHTML = '<div class="alert alert-warning">Keine detaillierten Daten verfügbar.</div>';
            }

            modal.showModal();
        }

        // Initialize Charts
        function initializeCharts() {
            const overtimeCanvas = document.getElementById('overtimeChart');
            const regularHoursCanvas = document.getElementById('regularHoursChart');
            if (typeof Chart === 'undefined' || !overtimeCanvas || !regularHoursCanvas || Chart.getChart(overtimeCanvas)) return;

            // Convert the PHP array to the correct format for charts
            const overtimeData = Object.values({{ \Illuminate\Support\Js::from($supervisor->report['overtime']) }}).map(user => ({
                label: user.username,
                value: user.overtime_minutes / 60
            }));

            // Overtime Chart
            new Chart(overtimeCanvas, {
                type: 'bar',
                data: {
                    labels: overtimeData.map(d => d.label),
                    datasets: [{
                        label: 'Überstunden',
                        data: overtimeData.map(d => d.value),
                        backgroundColor: overtimeData.map(d => d.value >= 0 ?
                            'rgba(72, 187, 120, 0.7)' : 'rgba(245, 101, 101, 0.7)'),
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Stunden'
                            }
                        }
                    }
                }
            });

            // Convert the PHP array to the correct format for charts
            const workingHoursData = Object.values({{ \Illuminate\Support\Js::from($supervisor->report['details']) }}).map(user => ({
                label: user.username,
                value: user.total_hours
            }));

            // Working Hours Chart
            new Chart(regularHoursCanvas, {
                type: 'bar',
                data: {
                    labels: Object.values({{ \Illuminate\Support\Js::from($supervisor->report['details']) }}).map(d => d.username),
                    datasets: [{
                        label: 'Regelarbeitszeit (Stunden)',
                        data: Object.values({{ \Illuminate\Support\Js::from($supervisor->report['details']) }}).map(d => d.regelarbeitszeit),
                        backgroundColor: 'rgba(66, 153, 225, 0.7)',
                        borderColor: 'rgba(66, 153, 225, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Stunden pro Tag'
                            }
                        }
                    }
                }
            });
        }

        document.getElementById('team-diagrams')?.addEventListener('toggle', function () {
            if (this.open && typeof Chart !== 'undefined') {
                initializeCharts();
                ['overtimeChart', 'regularHoursChart'].forEach(id => Chart.getChart(id)?.resize());
            }
        });

        let cardSearchTerm = '';
        let cardFilter = 'all';

        function filterCards() {
            const rows = [...document.querySelectorAll('#overtimeCards [data-overtime-minutes]')];
            let visible = 0;
            rows.forEach(row => {
                const username = row.querySelector('.chrono-team-person')?.textContent.toLowerCase() ?? '';
                const minutes = Number(row.dataset.overtimeMinutes);
                const matchesType = cardFilter === 'all' || (cardFilter === 'overtime' && minutes > 0) || (cardFilter === 'undertime' && minutes < 0);
                row.hidden = !(username.includes(cardSearchTerm) && matchesType);
                if (!row.hidden) visible++;
            });
            const empty = document.getElementById('teamAccountEmpty');
            if (empty) empty.hidden = rows.length === 0 || visible > 0;
        }

        // JavaScript only enhances existing server-rendered cards and detail rows.
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                cardSearchTerm = searchInput.value.toLowerCase();
                searchInput.addEventListener('input', function() {
                    cardSearchTerm = this.value.toLowerCase();
                    filterCards();
                });
            }

            document.querySelectorAll('.filter-badge').forEach(badge => {
                badge.addEventListener('click', function() {
                    cardFilter = this.dataset.filter;
                    document.querySelectorAll('.filter-badge').forEach(item => { item.classList.remove('active'); item.setAttribute('aria-pressed', 'false'); });
                    this.classList.add('active');
                    this.setAttribute('aria-pressed', 'true');
                    filterCards();
                });
            });
            filterCards();

            const tableBody = document.getElementById('timeRecordsBody');
            if (tableBody) {
                tableBody.querySelectorAll('.group-header').forEach(header => {
                    const detailRow = header.nextElementSibling;
                    if (!detailRow || !detailRow.classList.contains('detail-row')) return;
                    const toggle = header.querySelector('button[aria-expanded]');
                    const chevron = header.querySelector('.fa-chevron-right');

                    if (!toggle) return;
                    toggle.addEventListener('click', function() {
                        const expanded = toggle.getAttribute('aria-expanded') === 'true';
                        detailRow.hidden = expanded;
                        detailRow.classList.toggle('hidden', expanded);
                        toggle.setAttribute('aria-expanded', String(!expanded));
                        if (chevron) chevron.style.transform = expanded ? '' : 'rotate(90deg)';
                    });
                });
            }

            if (document.getElementById('team-diagrams')?.open) initializeCharts();
        });
    </script>
    @endif
</div>
@endsection
