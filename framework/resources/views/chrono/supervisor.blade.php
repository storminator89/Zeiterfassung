@extends('chrono.layouts.app')

@section('content')
{{-- Native supervisor UI only. Route/chrome and existing Chart asset admission remain separate; no executable CDN is added. --}}
<style>
        /* Remove the specific background-color from [data-theme="dark"] as it's not needed */
        .chrono-supervisor {
            color: hsl(var(--bc));
        }

        /* Existing styles */
        .chrono-supervisor .modal-box {
            width: 90vw;
            max-width: 70vw;
            height: 70vh;
            max-height: 90vh;
        }

        .chrono-supervisor th {
            text-align: left !important;
        }

        /* Card animations */
        .chrono-supervisor .stats-card {
            transition: transform 0.2s ease-in-out;
        }

        .chrono-supervisor .stats-card:hover {
            transform: translateY(-5px);
        }

        /* Loading animation */
        .chrono-supervisor .loading-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 200px;
        }

        /* Search bar styles */
        .chrono-supervisor .search-container {
            margin-bottom: 1rem;
        }

        .chrono-supervisor .search-input {
            width: 100%;
            max-width: 300px;
        }

        /* Filter badges */
        .chrono-supervisor .filter-badge {
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .chrono-supervisor .filter-badge:hover {
            transform: scale(1.05);
        }

        /* Chart container */
        .chrono-supervisor .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 2rem;
        }

        /* Native utility equivalents: scoped here instead of a remote JS CSS compiler. */
        .chrono-supervisor .min-h-screen { min-height: 100vh; }
        .chrono-supervisor .text-4xl { font-size: 2.25rem; line-height: 2.5rem; }
        .chrono-supervisor .text-3xl { font-size: 1.875rem; line-height: 2.25rem; }
        .chrono-supervisor .mb-8 { margin-bottom: 2rem; }
        .chrono-supervisor .mb-12 { margin-bottom: 3rem; }
        .chrono-supervisor .gap-4 { gap: 1rem; }
        .chrono-supervisor .justify-end { justify-content: flex-end; }
        .chrono-supervisor .max-w-xs { max-width: 20rem; }
        .chrono-supervisor .col-span-full { grid-column: 1 / -1; }
        .chrono-supervisor .space-y-6 > * + * { margin-top: 1.5rem; }
        .chrono-supervisor .hover\:shadow-2xl:hover { box-shadow: 0 25px 50px -12px rgb(0 0 0 / .25); }
        .chrono-supervisor .transition-shadow { transition-property: box-shadow; }
        .chrono-supervisor .duration-300 { transition-duration: 300ms; }
        .chrono-supervisor .transition-transform { transition-property: transform; }
        .chrono-supervisor .duration-200 { transition-duration: 200ms; }
        @media (min-width: 1024px) {
            .chrono-supervisor .lg\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .chrono-supervisor .lg\:grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (min-width: 1280px) {
            .chrono-supervisor .xl\:grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }
</style>
<div class="min-h-screen bg-base-100 chrono-supervisor">
    <div class="container mx-auto px-4 py-8">
            <h2 class="text-4xl font-bold mb-8 text-center">{{ $supervisor->text('SUPERVISOR_TIMES_TITLE') }}</h2>

            @if (in_array($page->role, ['supervisor', 'admin'], true) && isset($supervisor->report['team_status']))
                <section class="card bg-base-100 shadow-xl mb-8" aria-labelledby="teamStatusTitle">
                    <div class="card-body">
                        <h3 id="teamStatusTitle" class="card-title">{{ $supervisor->text('TEAM_STATUS_TITLE') }}</h3>
                        <p class="text-sm opacity-70">{{ $supervisor->text('TEAM_STATUS_SNAPSHOT_NOTE') }} <time datetime="{{ $supervisor->report['team_status']['loaded_at'] }}">{{ $supervisor->report['team_status']['loaded_at'] }}</time></p>
                        <div class="overflow-x-auto">
                            <table class="table table-zebra w-full">
                                <thead><tr><th>{{ $supervisor->text('TABLE_HEADER_USERNAME') }}</th><th>{{ $supervisor->text('TEAM_STATUS_STATE') }}</th></tr></thead>
                                <tbody>
                                    @forelse ($supervisor->report['team_status']['people'] as $person)
                                        @php
                                            $stateLabel = match ($person['state']) {
                                                'working' => 'TEAM_STATUS_WORKING',
                                                'paused' => 'TEAM_STATUS_PAUSED',
                                                'absent' => 'TEAM_STATUS_ABSENT',
                                                default => 'TEAM_STATUS_UNKNOWN',
                                            };
                                        @endphp
                                        <tr><td>{{ $person['username'] }}</td><td><span class="badge badge-outline">{{ $supervisor->text($stateLabel) }}</span></td></tr>
                                    @empty
                                        <tr><td colspan="2" class="opacity-70">{{ $supervisor->text('TEAM_STATUS_EMPTY') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
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
            @elseif (! $supervisor->report['available'])
                @if ($supervisor->report['absence_conflicts'] !== [])
                <div class="alert alert-warning mb-4" role="alert"><span><strong>Klärung offen.</strong> Arbeitszeit und ganztägige Abwesenheit überschneiden sich im freigegebenen Berichtsumfang. Diese Auswertung enthält deshalb keine abschließenden Summen. Bitte die betroffenen Originaleinträge in der <a class="link" href="history.php">Historie</a> prüfen und die Korrektur durch die betroffene Person mit anschließender Vier-Augen-Freigabe klären. Die <a class="link" href="index.php">eigene Arbeitszeitliste</a> und <a class="link" href="adjustments.php">Korrekturanträge</a> bleiben verfügbar; es wurden keine Zeiten gelöscht oder gekürzt.</span></div>
                @else
                <div class="alert alert-warning mb-4" role="alert"><span><strong>Datenqualität: {{ count($supervisor->report['invalid_records']) }} ungültige Zeiteinträge.</strong> Diese Auswertung enthält deshalb keine abschließenden Summen. Bitte die Originaleinträge in der <a class="link" href="history.php">Historie</a> prüfen und durch die betroffene Person mit anschließender Vier-Augen-Freigabe klären. Die <a class="link" href="index.php">eigene Arbeitszeitliste</a> und <a class="link" href="adjustments.php">Korrekturanträge</a> bleiben verfügbar; es wurden keine Zeiten gelöscht oder gekürzt.</span></div>
                @endif
            @else
                <!-- Search and Filter Section -->
                <div class="mb-6">
                    <div class="flex flex-wrap gap-4 items-center justify-between">
                        <div class="search-container">
                            <input type="text"
                                id="searchInput"
                                class="input input-bordered search-input"
                                placeholder="Suche nach Mitarbeiter...">
                        </div>
                        <div class="flex gap-2 flex-wrap">
                            <span class="badge badge-primary filter-badge" data-filter="all">Alle</span>
                            <span class="badge badge-secondary filter-badge" data-filter="overtime">Überstunden</span>
                            <span class="badge badge-warning filter-badge" data-filter="undertime">Minusstunden</span>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="mb-8">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="card bg-base-100 shadow-xl">
                            <div class="card-body">
                                <h3 class="card-title">Überstunden Übersicht</h3>
                                <p class="text-sm opacity-70 mt-2" style="white-space: normal;">{{ $page->copy->text('DASHBOARD_OVERTIME_INFO') }}</p>
                                <div class="chart-container">
                                    <canvas id="overtimeChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="card bg-base-100 shadow-xl">
                            <div class="card-body">
                                <h3 class="card-title">Regelarbeitszeit pro Mitarbeiter</h3>
                                <div class="chart-container">
                                    <canvas id="regularHoursChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Overtime Cards Section -->
                <div class="mb-12">
                    <h3 class="text-2xl font-semibold mb-6">
                        <i class="fas fa-hourglass mr-2"></i>{{ $supervisor->text('TOTAL_OVERTIME_TITLE') }}
                    </h3>
                    <p class="text-sm opacity-70 mt-2" style="white-space: normal;">{{ $page->copy->text('DASHBOARD_OVERTIME_INFO') }}</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="overtimeCards">
                        @if ($supervisor->report['overtime'] !== [])
                            @foreach ($supervisor->report['overtime'] as $userId => $data)
                                <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow duration-300 fade-in stats-card" data-overtime-minutes="{{ $data['overtime_minutes'] }}">
                                    <div class="card-body">
                                        <h2 class="card-title text-lg">{{ $data['username'] }}</h2>
                                        <p class="text-3xl font-bold {{ substr($data['ueberstunden'], 0, 1) === '-' ? 'text-error' : 'text-success' }}">
                                            {{ $data['ueberstunden'] }}
                                        </p>
                                        <div class="card-actions justify-end">
                                            <button class="btn btn-primary btn-sm" onclick="showDetails('{{ $userId }}')">Details</button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <p class="col-span-full text-center opacity-70">{{ $supervisor->text('NO_ENTRIES_FOUND') }}</p>
                        @endif
                    </div>
                </div>

                <!-- Time Records Table Section -->
                <div class="mb-8">
                    <h3 class="text-2xl font-semibold mb-6">
                        <i class="fas fa-clock mr-2"></i>{{ $supervisor->text('ACTUAL_WORKED_TIMES') }}
                    </h3>
                    <div class="card bg-base-100 shadow-xl">
                        <div class="card-body">
                            <!-- Search and Filter Bar -->
                            <form method="get" action="supervisor.php" class="flex flex-wrap gap-4 mb-4">
                                <input type="hidden" name="lang" value="{{ $supervisor->language }}">
                                <div class="form-control w-full max-w-xs">
                                    <div class="input-group">
                                        <input type="search"
                                            id="userSearchInput"
                                            name="user_search"
                                            value="{{ $supervisor->search }}"
                                            class="input input-bordered w-full"
                                            aria-label="{{ $supervisor->text('SEARCH_USERS') }}"
                                            placeholder="Mitarbeiter suchen...">
                                        <button type="submit" class="btn btn-square" aria-label="{{ $supervisor->text('SEARCH_USERS') }}">
                                            <i class="fas fa-search" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <div class="overflow-x-auto">
                                <table class="table w-full">
                                    <thead>
                                        <tr>
                                            <th class="w-8"></th> <!-- Expand/Collapse Icon -->
                                            <th>{{ $supervisor->text('TABLE_HEADER_USERNAME') }}</th>
                                            <th>{{ $supervisor->text('TABLE_HEADER_DATE') }}</th>
                                            <th>{{ $supervisor->text('TABLE_HEADER_TOTAL_DURATION') }}</th>
                                            <th>{{ $supervisor->text('TABLE_HEADER_TOTAL_BREAK') }}</th>
                                            <th>{{ $supervisor->text('TABLE_HEADER_LOCATION') }}</th>
                                            <th>{{ $supervisor->text('TABLE_HEADER_OVERTIME') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="timeRecordsBody">
                                        @forelse ($supervisor->report['groups'] as $group)
                                            <tr class="group-header hover:bg-base-200 cursor-pointer" data-user-id="{{ $group['user_id'] }}" data-date="{{ $group['date'] }}">
                                                <td><button type="button" class="btn btn-ghost btn-xs" aria-expanded="false" aria-controls="{{ $group['detail_id'] }}" aria-label="Details"><i class="fas fa-chevron-right transition-transform duration-200" aria-hidden="true"></i></button></td>
                                                <td>{{ $group['username'] }}</td>
                                                <td>{{ $group['date_formatted'] }}</td>
                                                <td>{{ $group['duration_formatted'] }}</td>
                                                <td>{{ $group['total_break'] }} min</td>
                                                <td>{{ $group['location'] }}</td>
                                                <td class="{{ $group['overtime_minutes'] >= 0 ? 'text-success' : 'text-error' }}">{{ $group['overtime_formatted'] }}</td>
                                            </tr>
                                            <tr id="{{ $group['detail_id'] }}" class="detail-row hidden" data-parent="{{ $group['user_id'] }}_{{ $group['date'] }}">
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
                                                                    <td>{{ $entry['standort'] }}</td>
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
                    @if ($supervisor->report['total_records'] > 0)
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
            @endif
        </div>

    @if (in_array($page->role, ['supervisor', 'admin'], true) && $supervisor->report['available'])
    <!-- Enhanced Modal -->
    <dialog id="details_modal" class="modal">
        <div class="modal-box w-11/12 max-w-7xl bg-base-200">
            <div class="modal-header flex justify-between items-center mb-6">
                <h3 class="font-bold text-2xl" id="modal_title">Detaillierte Übersicht</h3>
                <form method="dialog">
                    <button class="btn btn-circle btn-ghost">✕</button>
                </form>
            </div>
            <p class="text-sm opacity-70 mt-2" style="white-space: normal;">{{ $page->copy->text('DASHBOARD_OVERTIME_INFO') }}</p>
            <div id="modal_content" class="space-y-6">
                <!-- Content will be dynamically inserted here -->
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>Schließen</button>
        </form>
    </dialog>

    <script>
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
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="stat bg-base-100 rounded-box shadow-lg">
                    <div class="stat-figure text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                    </div>
                    <div class="stat-title">Regelarbeitszeit</div>
                    <div class="stat-value">${escapeHTML(userData.regelarbeitszeit)}h</div>
                    <div class="stat-desc">pro Tag</div>
                </div>

                <div class="stat bg-base-100 rounded-box shadow-lg">
                    <div class="stat-figure text-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                    </div>
                    <div class="stat-title">Gearbeitete Tage</div>
                    <div class="stat-value">${userData.total_days}</div>
                    <div class="stat-desc">in diesem Zeitraum</div>
                </div>

                <div class="stat bg-base-100 rounded-box shadow-lg">
                    <div class="stat-figure text-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <div class="stat-title">Gesamtarbeitsstunden</div>
                    <div class="stat-value">${userData.total_hours.toFixed(2)}</div>
                    <div class="stat-desc">Stunden insgesamt</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="stat bg-base-100 rounded-box shadow-lg">
                    <div class="stat-figure text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                    </div>
                    <div class="stat-title">Durchschnitt pro Tag</div>
                    <div class="stat-value">${userData.avg_hours_per_day.toFixed(2)}h</div>
                    <div class="stat-desc">Arbeitsstunden</div>
                </div>

                <div class="stat bg-base-100 rounded-box shadow-lg">
                    <div class="stat-figure ${userData.ueberstunden.startsWith('-') ? 'text-error' : 'text-success'}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div class="stat-title">Überstunden</div>
                    <div class="stat-value ${userData.ueberstunden.startsWith('-') ? 'text-error' : 'text-success'}">${userData.ueberstunden}</div>
                    <div class="stat-desc">${userData.ueberstunden.startsWith('-') ? 'Minusstunden' : 'Überstunden'}</div>
                </div>
            </div>

            <div class="alert alert-info shadow-lg mt-6">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                    <h3 class="font-bold">Hinweis</h3>
                    <div class="text-xs">Diese Übersicht basiert auf den aktuellen Daten und kann sich täglich ändern.</div>
                </div>
            </div>
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
            if (typeof Chart === 'undefined' || !overtimeCanvas || !regularHoursCanvas) return;

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

        let cardSearchTerm = '';
        let cardFilter = 'all';

        function filterCards() {
            document.querySelectorAll('#overtimeCards .card').forEach(card => {
                const username = card.querySelector('.card-title')?.textContent.toLowerCase() ?? '';
                const minutes = Number(card.dataset.overtimeMinutes);
                const matchesType = cardFilter === 'all' ||
                    (cardFilter === 'overtime' && minutes > 0) ||
                    (cardFilter === 'undertime' && minutes < 0);
                card.style.display = username.includes(cardSearchTerm) && matchesType ? '' : 'none';
            });
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
                    document.querySelectorAll('.filter-badge').forEach(item => item.classList.remove('badge-accent'));
                    this.classList.add('badge-accent');
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

                    header.addEventListener('click', function() {
                        const collapsed = detailRow.classList.toggle('hidden');
                        if (toggle) toggle.setAttribute('aria-expanded', String(!collapsed));
                        if (chevron) chevron.style.transform = collapsed ? '' : 'rotate(90deg)';
                    });
                });
            }

            initializeCharts();
        });
    </script>
    @endif
</div>
@endsection
