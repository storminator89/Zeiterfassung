@extends('chrono.layouts.app')

@section('content')
<main class="container mx-auto px-4 py-8 chrono-page chrono-today">
    <header class="chrono-today-heading"><div><h1>Heute</h1></div><img src="assets/illustrations/time-ledger.png" alt="" width="104" height="88"></header>
    <div class="chrono-clock-layout chrono-today-workspace">
        <div class="min-w-0 chrono-clock-main">
            <section class="card bg-base-100 shadow-xl">
                <div class="card-body p-4 sm:p-6">

                    <header class="flex items-center gap-2">
                        <span class="chrono-clock-label"><x-chrono-icon name="clock" size="20" /> Zeiterfassung</span>
                        <x-chrono-help label="Arbeitszeit und Pausen"><p>Buchungen verwenden die Serverzeit. Pausen werden mit Beginn und Ende erfasst; es wird keine Mindestpause automatisch abgezogen.</p></x-chrono-help>
                    </header>
                    <div id="status" data-attendance-status class="text-2xl font-bold" role="status">{{ $page->statusLabel() }}</div>
                    <p id="message" class="text-sm" role="status" aria-live="polite"></p>
                    <div id="actions" data-state="{{ $page->state }}" class="flex flex-wrap gap-3" aria-label="Arbeitszeit buchen">
                        <button type="button" disabled data-action="start" class="btn btn-primary" @if ($page->state !== 'absent') hidden @endif><i class="fas fa-sign-in-alt" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3h7v18h-7M3 12h14M11 6l6 6-6 6"/></svg></i>Kommen</button>
                        <button type="button" disabled data-action="pause_start" class="btn btn-outline-secondary" @if ($page->state !== 'working') hidden @endif><i class="fas fa-pause" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 5v14M16 5v14"/></svg></i>Pause beginnen</button>
                        <button type="button" disabled data-action="pause_end" class="btn btn-primary" @if ($page->state !== 'paused') hidden @endif><i class="fas fa-play" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m7 4 14 8-14 8z"/></svg></i>Pause beenden</button>
                        <button type="button" disabled data-action="end" class="btn btn-primary" @if ($page->state === 'absent') hidden @endif><i class="fas fa-sign-out-alt" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H3v18h6M8 12h13M16 7l5 5-5 5"/></svg></i>Gehen</button>
                    </div>
                    <div class="chrono-metadata"><fieldset data-start-metadata class="grid sm:grid-cols-2 gap-3" disabled>
                        <legend class="sr-only">Optionale Angaben zur Buchung</legend>
                        <div><label for="attendance-location" class="block text-sm mb-1">Arbeitsort</label><select id="attendance-location" class="select select-bordered w-full form-select"><option value="office">Büro</option><option value="home_office">Home Office</option><option value="business_trip">Dienstreise</option></select></div>
                        <div><label for="attendance-note" class="block text-sm mb-1">Notiz (optional)</label><input id="attendance-note" type="text" maxlength="1000" class="input input-bordered w-full form-control"></div>

                    </fieldset></div>


                    @php($vacationAvailable=app()->bound('router') && app('router')->has('chrono.vacation'))
                    @if ($vacationAvailable)@php($vacationCopy=new \App\View\ExistingUi\VacationCopy($page->copy->language))@endif

                </div>
            </section>
        </div>
        <aside class="min-w-0 chrono-balance-strip" aria-label="Zeitkonto">
            <section class="chrono-today-account">
                <div class="card-body p-4 sm:p-6">
                    <div class="stats w-full">

                        <div class="stat"><div class="stat-title">Saldo <x-chrono-help label="Berechnung des Saldos"><p class="text-sm opacity-70 mt-2" style="white-space: normal;">{{ $page->copy->text('DASHBOARD_OVERTIME_INFO') }}</p><p>{{ $page->currentMonthName }}: {{ $page->workingDaysThisMonth }} Arbeitstage.</p></x-chrono-help></div><div data-home-balance aria-label="{{ $page->copy->text('UI_RECORDED_BALANCE') }}" data-report-period="all" data-report-presentation="{{ $page->overtimePresentation() }}" class="stat-value {{ $page->reportUnresolved || $page->reportInvalidCount > 0 || $page->positiveOvertime === null || $page->overtimeLabel() === '00:00' ? '' : ($page->positiveOvertime ? 'text-success' : 'text-error') }}">{{ $page->overtimeLabel() }}</div></div>
                        <div class="stat"><div class="stat-title">Soll heute</div><div class="stat-value">{{ $page->dailyHoursFormatted ?? '—' }}</div></div>
                    </div>

                    <a href="dashboard.php" class="link link-primary mt-4">Zur Auswertung</a>
                </div>
            </section>
        </aside>
    </div>
    <nav class="chrono-today-shortcuts" aria-label="Weitere Aufgaben">
        <a href="attendance.php?panel=absence#home-absence" data-home-dialog="home-absence"><x-chrono-icon name="calendar-check" size="20" />Abwesenheit erfassen</a>
        <a href="adjustments.php"><x-chrono-icon name="pencil" size="20" />Zeit korrigieren</a>
        <a href="attendance.php?panel=reports#home-reports" data-home-dialog="home-reports"><x-chrono-icon name="chart-bar" size="20" />Berichte &amp; Export</a>
    </nav>

    @if ($page->interactions->home)
    <section id="timeRecordsContainer" class="mt-8" aria-label="Erfasste Arbeitszeiten"><p role="status">Arbeitszeiten werden geladen …</p></section>
    @else
    <section class="mt-8" aria-label="Erfasste Arbeitszeiten"><p role="status">Die bestehende Arbeitszeittabelle wird erst nach Freigabe des authentifizierten Laravel-HTML-Fragments verbunden. Hier wird noch nichts geladen.</p></section>
    @endif
<div class="chrono-tools">
                    @if ($page->holidaysThisWeek !== [])<details class="chrono-holiday-note"><summary>{{ $page->copy->text('HOLIDAYS_THIS_WEEK') }}</summary><div>@foreach ($page->holidaysThisWeek as $holiday)<p class="text-sm">{{ $holiday['date'] }} {{ $holiday['name'] }}</p>@endforeach</div></details>@endif
                    <dialog id="home-absence" class="chrono-home-dialog" aria-labelledby="home-absence-title" @if(request()->query('panel') === 'absence') open data-open-home-dialog @endif>
                    <header class="chrono-home-dialog-header"><h2 id="home-absence-title"><x-chrono-icon name="calendar-check" size="22" /> Abwesenheit erfassen</h2><a href="attendance.php" data-close-home-dialog class="btn btn-icon btn-ghost-secondary" aria-label="Dialog schließen">×</a></header>
                    <form id="absence-form" method="post" action="save.php" class="chrono-absence-form">
                        @csrf
                        <fieldset disabled @if ($page->interactions->absences) data-local-absence-enabled="1" @endif class="chrono-absence-fields">
                            <legend class="sr-only">Abwesenheit</legend>
                            <fieldset class="chrono-absence-kind"><legend class="sr-only">Art der Abwesenheit</legend>
                                <label><input type="radio" name="beschreibung" value="Urlaub" class="form-check-input" checked required><x-chrono-icon name="plane" size="18" />{{ $page->copy->text('EVENT_VACATION') }}</label>
                                <label><input type="radio" name="beschreibung" value="Krankheit" class="form-check-input" required><x-chrono-icon name="calendar-off" size="18" />{{ $page->copy->text('EVENT_SICK') }}</label>
                            </fieldset>
                            <div class="chrono-form-pair">
                                <div><label for="urlaubStart" class="form-label">Von</label><input type="date" id="urlaubStart" name="urlaubStart" class="form-control" required></div>
                                <div><label for="urlaubEnde" class="form-label">Bis</label><input type="date" id="urlaubEnde" name="urlaubEnde" class="form-control" required></div>
                            </div>
                            @if ($vacationAvailable)
                            <input type="hidden" name="vacation_command_id" value="{{ bin2hex(random_bytes(16)) }}">
                            <div><label class="form-label" for="home-vacation-reason">{{ $vacationCopy->text('home_reason') }}</label><textarea id="home-vacation-reason" name="vacation_reason" maxlength="2000" class="form-control" rows="2"></textarea></div>
                            @endif
                            <p id="absence-message" class="text-sm" role="status" aria-live="polite"></p>
                            <footer class="chrono-dialog-footer">
                                <x-chrono-help label="Abwesenheiten und Feiertage"><p>Urlaub und Krankheit erhalten die Sollminuten Ihres Arbeitszeitmodells. Freie Tage und hinterlegte Feiertage werden ausgelassen. Gutschriften sind keine gestempelten Arbeitszeiten.</p>@if($vacationAvailable)<p>{{ $vacationCopy->text('home_note') }} <a href="vacation.php">{{ $vacationCopy->text('open') }}</a></p>@endif @if($page->interactions->dashboard)<p><a href="dashboard.php?holiday_inventory=1#holiday-inventory" target="_blank" rel="noopener noreferrer">{{ $page->copy->text('HOLIDAY_INVENTORY_ABSENCE_LINK') }}</a></p>@endif</x-chrono-help>
                                <a href="attendance.php" data-close-home-dialog class="btn btn-outline-secondary">Abbrechen</a>
                                <button type="submit" class="btn btn-primary">Abwesenheit erfassen</button>
                            </footer>
                        </fieldset>
                    </form>
                    </dialog>
    <dialog id="home-reports" class="chrono-home-dialog chrono-secondary-tools" aria-labelledby="home-reports-title" @if (request()->query('panel') === 'reports' || ($ownMonthReview->requested ?? false) || ($ownPeriodComparison->requested ?? false) || request()->has('export_from')) open data-open-home-dialog @endif>
    <header class="chrono-home-dialog-header"><h2 id="home-reports-title"><x-chrono-icon name="chart-bar" size="22" /> Berichte &amp; Export</h2><a href="attendance.php" data-close-home-dialog class="btn btn-icon btn-ghost-secondary" aria-label="Dialog schließen">×</a></header>
    @if ($ownOpenReviewUnavailable ?? false)
    <p class="text-sm opacity-70 mt-6" role="status">{{ $page->copy->text('OWN_OPEN_REVIEW_UNAVAILABLE') }}</p>
    @elseif (($ownOpenReview ?? null) !== null && $ownOpenReview['count'] > 0)
    <section class="card bg-base-100 shadow-xl mt-6" aria-labelledby="ownOpenReviewTitle">
        <div class="card-body p-4 sm:p-6">
            <h2 id="ownOpenReviewTitle" class="card-title">{{ $page->copy->text('OWN_OPEN_REVIEW_TITLE') }}</h2>
            <p class="text-sm">{{ sprintf($page->copy->text('OWN_OPEN_REVIEW_COUNT'), $ownOpenReview['count'], $ownOpenReview['cutoff_day']) }}</p>
            <x-chrono-help><p class="text-sm opacity-70">{{ $page->copy->text('OWN_OPEN_REVIEW_NOTE') }}</p></x-chrono-help>
            <p class="text-sm opacity-70">{{ $page->copy->text('OWN_OPEN_REVIEW_ACTION') }}</p>
            <x-chrono-help><p class="text-xs opacity-70">{{ $page->copy->text('OWN_OPEN_REVIEW_SNAPSHOT') }} <time datetime="{{ $ownOpenReview['loaded_at'] }}">{{ $ownOpenReview['loaded_at'] }}</time></p></x-chrono-help>
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
    @include('chrono.partials.own-period-comparison')
    @include('chrono.partials.own-correction-decisions')
    @endif
    </dialog>
</div>
</main>
@endsection
