@extends('chrono.layouts.app')

@section('content')
<main class="container mx-auto px-4 py-8 chrono-page chrono-today">
    <div class="chrono-clock-layout">
        <div class="min-w-0 chrono-clock-main">
            <section class="card bg-base-100 shadow-xl">
                <div class="card-body p-4 sm:p-6">
                    <img src="assets/illustrations/time-ledger.png" class="chrono-clock-illustration" alt="" width="120" height="120" loading="eager">
                    <header class="flex items-center gap-2 mb-2">
                        <h1 class="text-xl font-bold mb-0"><x-chrono-icon name="clock" size="24" /> Heute</h1>
                        <x-chrono-help label="Arbeitszeit und Pausen"><p>Buchungen verwenden die Serverzeit. Pausen werden mit Beginn und Ende erfasst; es wird keine Mindestpause automatisch abgezogen.</p></x-chrono-help>
                    </header>
                    <div id="status" data-attendance-status class="text-2xl font-bold mt-2" role="status">{{ $page->statusLabel() }}</div>
                    <p id="message" class="text-sm" role="status" aria-live="polite"></p>
                    <div id="actions" data-state="{{ $page->state }}" class="flex flex-wrap gap-3 mt-3 mb-5" aria-label="Arbeitszeit buchen">
                        <button type="button" disabled data-action="start" class="btn btn-primary" @if ($page->state !== 'absent') hidden @endif><i class="fas fa-sign-in-alt" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3h7v18h-7M3 12h14M11 6l6 6-6 6"/></svg></i>Kommen</button>
                        <button type="button" disabled data-action="pause_start" class="btn btn-outline-secondary" @if ($page->state !== 'working') hidden @endif><i class="fas fa-pause" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 5v14M16 5v14"/></svg></i>Pause beginnen</button>
                        <button type="button" disabled data-action="pause_end" class="btn btn-outline-secondary" @if ($page->state !== 'paused') hidden @endif><i class="fas fa-play" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m7 4 14 8-14 8z"/></svg></i>Pause beenden</button>
                        <button type="button" disabled data-action="end" class="btn btn-secondary" @if ($page->state === 'absent') hidden @endif><i class="fas fa-sign-out-alt" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H3v18h6M8 12h13M16 7l5 5-5 5"/></svg></i>Gehen</button>
                    </div>
                    <details class="chrono-metadata"><summary>Arbeitsort &amp; Notiz</summary><fieldset data-start-metadata class="grid sm:grid-cols-2 gap-3 my-4" disabled>
                        <legend class="sr-only">Optionale Angaben zur Buchung</legend>
                        <div><label for="attendance-location" class="block text-sm mb-1">Arbeitsort</label><select id="attendance-location" class="select select-bordered w-full form-select"><option value="office">Büro</option><option value="home_office">Home Office</option><option value="business_trip">Dienstreise</option></select></div>
                        <div><label for="attendance-note" class="block text-sm mb-1">Notiz (optional)</label><input id="attendance-note" type="text" maxlength="1000" class="input input-bordered w-full form-control"></div>

                    </fieldset></details>
                    <a class="link link-primary text-sm" href="adjustments.php">Korrektur beantragen</a>

                    @php($vacationAvailable=app()->bound('router') && app('router')->has('chrono.vacation'))
                    @if ($vacationAvailable)@php($vacationCopy=new \App\View\ExistingUi\VacationCopy($page->copy->language))@endif

                </div>
            </section>
        </div>
        <aside class="min-w-0 chrono-balance-strip" aria-label="Zeitkonto">
            <section class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow duration-300">
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

    @if ($page->interactions->home)
    <section id="timeRecordsContainer" class="mt-8" aria-label="Erfasste Arbeitszeiten"><p role="status">Arbeitszeiten werden geladen …</p></section>
    @else
    <section class="mt-8" aria-label="Erfasste Arbeitszeiten"><p role="status">Die bestehende Arbeitszeittabelle wird erst nach Freigabe des authentifizierten Laravel-HTML-Fragments verbunden. Hier wird noch nichts geladen.</p></section>
    @endif
<div class="chrono-tools">
                    @if ($page->holidaysThisWeek !== [])<details class="chrono-holiday-note"><summary>{{ $page->copy->text('HOLIDAYS_THIS_WEEK') }}</summary><div>@foreach ($page->holidaysThisWeek as $holiday)<p class="text-sm">{{ $holiday['date'] }} {{ $holiday['name'] }}</p>@endforeach</div></details>@endif
                    <details class="chrono-disclosure mt-5"><summary><x-chrono-icon name="calendar-check" size="20" /> Abwesenheit erfassen</summary>
                    <form id="absence-form" method="post" action="save.php" class="space-y-5 mt-6 border-t border-base-200 pt-5">
                        @csrf
                        <fieldset disabled @if ($page->interactions->absences) data-local-absence-enabled="1" @endif class="space-y-5">
                        <fieldset>
                            <legend class="text-lg font-semibold mb-3">Ereignistyp</legend>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="flex items-center p-3 border border-base-300 rounded-lg hover:bg-base-200 cursor-pointer">
                                    <input type="radio" name="beschreibung" value="Urlaub" class="radio radio-primary mr-3 form-check-input" checked required>
                                    <x-chrono-icon name="plane" size="18" /><span>{{ $page->copy->text('EVENT_VACATION') }}</span>
                                </label>
                                <label class="flex items-center p-3 border border-base-300 rounded-lg hover:bg-base-200 cursor-pointer">
                                    <input type="radio" name="beschreibung" value="Krankheit" class="radio radio-primary mr-3 form-check-input" required>
                                    <i class="fas fa-stethoscope text-xl mr-2" aria-hidden="true"></i><span>{{ $page->copy->text('EVENT_SICK') }}</span>
                                </label>
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend class="text-lg font-semibold mb-3">Datumsbereich</legend>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div><label for="urlaubStart" class="block text-sm font-semibold mb-2">Von</label><input type="date" id="urlaubStart" name="urlaubStart" class="input input-bordered w-full form-control" required></div>
                                <div><label for="urlaubEnde" class="block text-sm font-semibold mb-2">Bis</label><input type="date" id="urlaubEnde" name="urlaubEnde" class="input input-bordered w-full form-control" required></div>
                            </div>
                        </fieldset>
                        @if ($vacationAvailable)
                        <input type="hidden" name="vacation_command_id" value="{{ bin2hex(random_bytes(16)) }}">
                        <label class="block text-sm">{{ $vacationCopy->text('home_reason') }}<textarea name="vacation_reason" maxlength="2000" class="textarea textarea-bordered w-full form-control"></textarea></label>
                        <x-chrono-help><p class="text-sm opacity-70">{{ $vacationCopy->text('home_note') }} <a class="link link-primary" href="vacation.php">{{ $vacationCopy->text('open') }}</a></p></x-chrono-help>
                        @endif
                        <x-chrono-help><p class="text-sm opacity-70">Urlaub und Krankheit erhalten die Sollminuten Ihres Arbeitszeitmodells. Freie Tage und hinterlegte Feiertage werden ausgelassen. Gutschriften sind keine gestempelten Arbeitszeiten.</p></x-chrono-help>
                        @if ($page->interactions->dashboard)<p class="text-sm opacity-70"><a class="link link-primary" href="dashboard.php?holiday_inventory=1#holiday-inventory" target="_blank" rel="noopener noreferrer">{{ $page->copy->text('HOLIDAY_INVENTORY_ABSENCE_LINK') }}</a></p>@endif
                        <p id="absence-message" class="text-sm" role="status" aria-live="polite"></p>
                        <button type="submit" class="btn btn-primary"><x-chrono-icon name="calendar-check" size="18" />Abwesenheit erfassen</button>
                        </fieldset>
                    </form>
                    </details>
    <details class="chrono-disclosure chrono-secondary-tools" @if (($ownMonthReview->requested ?? false) || ($ownPeriodComparison->requested ?? false) || request()->has('export_from')) open @endif><summary><x-chrono-icon name="file-export" size="20" /> Auswertungen &amp; Export</summary>
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
    </details>
</div>
</main>
@endsection
