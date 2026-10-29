@extends('chrono.layouts.app')

@section('content')
<main class="container mx-auto px-4 py-8 chrono-page">
    <div class="grid md:grid-cols-3 gap-6 items-start">
        <div class="md:col-span-2 min-w-0">
            <section class="card bg-base-100 shadow-xl">
                <div class="card-body p-4 sm:p-6">
                    <header class="flex items-center gap-3 mb-4">
                        <img src="assets/kolibri_icon.png" alt="" class="w-10 h-10">
                        <h1 class="text-xl font-bold text-primary mb-0">{{ $page->copy->text('TITLE') }}</h1>
                    </header>
                    <p class="text-lg">Willkommen zurück, {{ $page->username }}!</p>
                    <div id="status" data-attendance-status class="text-2xl font-bold mt-2" role="status">{{ $page->statusLabel() }}</div>
                    <p id="message" class="text-sm opacity-70" role="status" aria-live="polite">Jede Buchung wird mit Serverzeit bestätigt.@if (! $page->interactions->attendance) Laravel-Buchungen sind in dieser Ansicht noch nicht aktiviert.@endif</p>
                    <fieldset data-start-metadata class="grid sm:grid-cols-2 gap-3 my-4" disabled>
                        <legend class="text-sm font-semibold">Beim Kommen (optional)</legend>
                        <div><label for="attendance-location" class="block text-sm mb-1">Arbeitsort</label><select id="attendance-location" class="select select-bordered w-full"><option value="office">Büro</option><option value="home_office">Home Office</option><option value="business_trip">Dienstreise</option></select></div>
                        <div><label for="attendance-note" class="block text-sm mb-1">Notiz</label><input id="attendance-note" type="text" maxlength="1000" class="input input-bordered w-full" aria-describedby="attendance-metadata-help"></div>
                        <p id="attendance-metadata-help" class="text-xs opacity-70 sm:col-span-2">Nur für den neuen Start; maximal 1000 UTF-8-Bytes. Keine sensiblen persönlichen Angaben. Bei unbestätigter Buchung bleiben diese Werte unverändert.</p>
                    </fieldset>
                    <div id="actions" data-state="{{ $page->state }}" class="flex flex-wrap gap-3 mt-3 mb-5" aria-label="Arbeitszeit buchen">
                        <button type="button" disabled data-action="start" class="btn btn-primary" @if ($page->state !== 'absent') hidden @endif><i class="fas fa-sign-in-alt"></i>Kommen</button>
                        <button type="button" disabled data-action="pause_start" class="btn btn-outline" @if ($page->state !== 'working') hidden @endif><i class="fas fa-pause"></i>Pause beginnen</button>
                        <button type="button" disabled data-action="pause_end" class="btn btn-outline" @if ($page->state !== 'paused') hidden @endif><i class="fas fa-play"></i>Pause beenden</button>
                        <button type="button" disabled data-action="end" class="btn btn-secondary" @if ($page->state === 'absent') hidden @endif><i class="fas fa-sign-out-alt"></i>Gehen</button>
                    </div>
                    <details class="text-sm mb-4">
                        <summary class="cursor-pointer font-semibold">Tatsächliche Pausen</summary>
                        <p class="mt-2">Start und Ende jeder Pause werden erfasst. Mindestpausen werden nicht automatisch abgezogen. Bitte die betrieblichen Pausenregeln beachten. Beim Gehen während einer Pause werden Pause und Arbeitstag nach Ihrer Bestätigung gemeinsam beendet.</p>
                    </details>
                    <a class="link link-primary text-sm" href="adjustments.php">Buchung vergessen oder Zeit korrigieren?</a>

                    <form id="absence-form" method="post" action="save.php" class="space-y-5 mt-6 border-t border-base-200 pt-5">
                        @csrf
                        <fieldset disabled @if ($page->interactions->absences) data-local-absence-enabled="1" @endif class="space-y-5">
                        <fieldset>
                            <legend class="text-lg font-semibold mb-3">Ereignistyp</legend>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="flex items-center p-3 border border-base-300 rounded-lg hover:bg-base-200 cursor-pointer">
                                    <input type="radio" name="beschreibung" value="Urlaub" class="radio radio-primary mr-3" checked required>
                                    <i class="fas fa-umbrella-beach text-xl mr-2"></i><span>{{ $page->copy->text('EVENT_VACATION') }}</span>
                                </label>
                                <label class="flex items-center p-3 border border-base-300 rounded-lg hover:bg-base-200 cursor-pointer">
                                    <input type="radio" name="beschreibung" value="Krankheit" class="radio radio-primary mr-3" required>
                                    <i class="fas fa-stethoscope text-xl mr-2"></i><span>{{ $page->copy->text('EVENT_SICK') }}</span>
                                </label>
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend class="text-lg font-semibold mb-3">Datumsbereich</legend>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div><label for="urlaubStart" class="block text-sm font-semibold mb-2">Von</label><input type="date" id="urlaubStart" name="urlaubStart" class="input input-bordered w-full" required></div>
                                <div><label for="urlaubEnde" class="block text-sm font-semibold mb-2">Bis</label><input type="date" id="urlaubEnde" name="urlaubEnde" class="input input-bordered w-full" required></div>
                            </div>
                        </fieldset>
                        <p class="text-sm opacity-70">Urlaub und Krankheit erhalten die Sollminuten Ihres Arbeitszeitmodells. Freie Tage und hinterlegte Feiertage werden ausgelassen. Gutschriften sind keine gestempelten Arbeitszeiten.</p>
                        @if ($page->interactions->dashboard)<p class="text-sm opacity-70"><a class="link link-primary" href="dashboard.php?holiday_inventory=1#holiday-inventory" target="_blank" rel="noopener noreferrer">{{ $page->copy->text('HOLIDAY_INVENTORY_ABSENCE_LINK') }}</a></p>@endif
                        <p id="absence-message" class="text-sm" role="status" aria-live="polite"></p>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-calendar-plus"></i>Abwesenheit erfassen</button>
                        </fieldset>
                    </form>
                </div>
            </section>
        </div>
        <aside class="min-w-0">
            <section class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow duration-300">
                <div class="card-body p-4 sm:p-6">
                    <h2 class="card-title text-2xl mb-4"><i class="fas fa-chart-bar mr-2"></i>{{ $page->copy->text('STATISTICS_WORKING_TIMES') }}</h2>
                    <div class="stats stats-vertical shadow w-full">
                        <div class="stat"><div class="stat-figure text-primary"><i class="fas fa-calendar-day fa-2x"></i></div><div class="stat-title">{{ $page->copy->text('TABLE_HEADER_WORKING_DAYS') }}</div><div class="stat-value">{{ $page->workingDaysThisMonth ?? '—' }}</div><div class="stat-desc">{{ $page->currentMonthName ?? 'Noch keine Berichtsprojektion verbunden' }}</div></div>
                        <div class="stat"><div class="stat-figure text-primary"><i class="fas fa-clock fa-2x"></i></div><div class="stat-title">{{ $page->copy->text('TABLE_HEADER_TOTAL_OVERTIME') }}</div><div data-report-period="all" data-report-presentation="{{ $page->overtimePresentation() }}" class="stat-value {{ $page->reportUnresolved || $page->reportInvalidCount > 0 || $page->positiveOvertime === null ? '' : ($page->positiveOvertime ? 'text-success' : 'text-error') }}">{{ $page->overtimeLabel() }}</div><p class="text-sm opacity-70 mt-2" style="white-space: normal;">{{ $page->copy->text('DASHBOARD_OVERTIME_INFO') }}</p></div>
                        <div class="stat"><div class="stat-figure text-primary"><i class="fas fa-business-time fa-2x"></i></div><div class="stat-title">{{ $page->copy->text('TABLE_HEADER_REGULAR_WORKING_HOURS') }}</div><div class="stat-value">{{ $page->dailyHoursFormatted ?? '—' }}</div><div class="stat-desc">{{ $page->copy->text('LABEL_HOURS_PER_DAY') }}</div></div>
                    </div>
                    @if ($page->holidaysThisWeek !== [])<div class="alert alert-info mt-4"><div><h3 class="font-bold">{{ $page->copy->text('HOLIDAYS_THIS_WEEK') }}</h3>@foreach ($page->holidaysThisWeek as $holiday)<p class="text-sm">{{ $holiday['date'] }} · {{ $holiday['name'] }}</p>@endforeach</div></div>@endif
                    <a href="dashboard.php" class="link link-primary mt-4">Zur Auswertung</a>
                </div>
            </section>
        </aside>
    </div>
    @if ($ownOpenReviewUnavailable ?? false)
    <p class="text-sm opacity-70 mt-6" role="status">{{ $page->copy->text('OWN_OPEN_REVIEW_UNAVAILABLE') }}</p>
    @elseif (($ownOpenReview ?? null) !== null && $ownOpenReview['count'] > 0)
    <section class="card bg-base-100 shadow-xl mt-6" aria-labelledby="ownOpenReviewTitle">
        <div class="card-body p-4 sm:p-6">
            <h2 id="ownOpenReviewTitle" class="card-title">{{ $page->copy->text('OWN_OPEN_REVIEW_TITLE') }}</h2>
            <p class="text-sm">{{ sprintf($page->copy->text('OWN_OPEN_REVIEW_COUNT'), $ownOpenReview['count'], $ownOpenReview['cutoff_day']) }}</p>
            <p class="text-sm opacity-70">{{ $page->copy->text('OWN_OPEN_REVIEW_NOTE') }}</p>
            <p class="text-sm opacity-70">{{ $page->copy->text('OWN_OPEN_REVIEW_ACTION') }}</p>
            <p class="text-xs opacity-70">{{ $page->copy->text('OWN_OPEN_REVIEW_SNAPSHOT') }} <time datetime="{{ $ownOpenReview['loaded_at'] }}">{{ $ownOpenReview['loaded_at'] }}</time></p>
            <ul class="space-y-2 my-3">
            @foreach ($ownOpenReview['rows'] as $row)
                <li class="text-sm">{{ $row['start_label'] ?? $page->copy->text('OWN_OPEN_REVIEW_UNKNOWN_START') }} · #{{ $row['record_id'] }}
                @if (is_string($row['record_id']) && preg_match('/\A[1-9][0-9]*\z/D', $row['record_id']) === 1 && filter_var($row['record_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false)
                    <a class="link link-primary" href="{{ 'adjustments.php?record_id='.rawurlencode($row['record_id']) }}">{{ $page->copy->text('OWN_OPEN_REVIEW_LINK') }}</a>
                @endif
                </li>
            @endforeach
            </ul>
            @if ($ownOpenReview['limited'])<p class="text-sm opacity-70">{{ $page->copy->text('OWN_OPEN_REVIEW_LIMIT') }}</p>@endif
            <a class="link text-sm" href="attendance.php">{{ $page->copy->text('OWN_OPEN_REVIEW_REFRESH') }}</a>
        </div>
    </section>
    @endif
    @if ($page->interactions->home && app()->bound('router') && app('router')->has('chrono.export-csv'))
    @include('chrono.partials.csv-period', ['copy' => $page->copy])
    @endif
    @if ($page->interactions->home)
    @include('chrono.partials.own-month-review')
    <section id="timeRecordsContainer" class="mt-8" aria-label="Erfasste Arbeitszeiten"><p role="status">Arbeitszeiten werden geladen …</p></section>
    @else
    <section class="mt-8" aria-label="Erfasste Arbeitszeiten"><p role="status">Die bestehende Arbeitszeittabelle wird erst nach Freigabe des authentifizierten Laravel-HTML-Fragments verbunden. Hier wird noch nichts geladen.</p></section>
    @endif
</main>
@endsection
