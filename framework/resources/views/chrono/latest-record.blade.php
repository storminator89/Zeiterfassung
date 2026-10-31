{{-- Native get_latest_record.php fragment. Prepared own values are escaped here. --}}
@if ($latestRecord->entry !== null)
<div class="card-body">
    <h3 class="card-title text-2xl mb-4"><x-chrono-icon name="clock" size="18" />{{ $latestRecord->copy->text('LATEST_TIME_RECORD') }}</h3>
    @if ($latestRecord->entry['invalid'])
    <div class="alert alert-warning mb-4" role="alert">
        <span>Datenqualität: {{ $latestRecord->entry['error'] }} Keine verlässliche Dauer.
        <a class="link" href="adjustments.php?record_id={{ $latestRecord->entry['id'] }}">Korrektur / Storno</a></span>
    </div>
    @endif
    @if ($latestRecord->entry['timestamp_review'] ?? false)
    <div class="alert alert-warning mb-4" role="alert" data-latest-timestamp-review>
        <span>{{ $latestRecord->copy->text('LATEST_TIMESTAMP_REVIEW_HINT') }}
        <a class="link" href="adjustments.php?record_id={{ $latestRecord->entry['id'] }}">{{ $latestRecord->copy->text('OWN_OPEN_REVIEW_LINK') }}</a></span>
    </div>
    @endif
    <dl class="row g-3 mb-0">
        <div class="col-12"><dt class="text-secondary fw-normal">{{ $latestRecord->copy->text('TABLE_HEADER_DURATION') }}</dt><dd class="h2 mb-0">{{ $latestRecord->entry['duration'] }}</dd></div>
        <div class="col-sm-6"><dt class="text-secondary fw-normal">{{ $latestRecord->copy->text('TABLE_HEADER_START_TIME') }}</dt><dd>{{ \App\View\ExistingUi\HumanTime::full($latestRecord->entry['start']) }}</dd></div>
        <div class="col-sm-6"><dt class="text-secondary fw-normal">{{ $latestRecord->copy->text('TABLE_HEADER_END_TIME') }}</dt><dd>{{ \App\View\ExistingUi\HumanTime::full($latestRecord->entry['end']) }}</dd></div>
        <div class="col-sm-6"><dt class="text-secondary fw-normal">{{ $latestRecord->copy->text('TABLE_HEADER_BREAK') }}</dt><dd>{{ $latestRecord->entry['pause'] }} {{ $latestRecord->copy->text('LABEL_MINUTES') }}</dd></div>
        <div class="col-sm-6"><dt class="text-secondary fw-normal">{{ $latestRecord->copy->text('TABLE_HEADER_LOCATION') }}</dt><dd>{{ ['office'=>'Büro','home_office'=>'Home Office','business_trip'=>'Dienstreise'][$latestRecord->entry['location']] ?? $latestRecord->entry['location'] }}</dd></div>
        <div class="col-12"><dt class="text-secondary fw-normal">{{ $latestRecord->copy->text('TABLE_HEADER_COMMENT') }}</dt><dd>{{ $latestRecord->entry['comment'] }}</dd></div>
    </dl>
</div>
@else
<p>Kein Zeiteintrag gefunden.</p>
@endif
