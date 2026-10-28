{{-- Native get_latest_record.php fragment. Prepared own values are escaped here. --}}
@if ($latestRecord->entry !== null)
<div class="card-body">
    <h3 class="card-title text-2xl mb-4"><i class="fas fa-clock mr-2"></i>{{ $latestRecord->copy->text('LATEST_TIME_RECORD') }}</h3>
    @if ($latestRecord->entry['invalid'])
    <div class="alert alert-warning mb-4" role="alert">
        <span>Datenqualität: {{ $latestRecord->entry['error'] }} Keine verlässliche Dauer.
        <a class="link" href="adjustments.php?record_id={{ $latestRecord->entry['id'] }}">Korrektur / Storno</a></span>
    </div>
    @endif
    <div class="bg-base-200 p-6 rounded-lg shadow-inner">
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2 bg-primary text-primary-content p-4 rounded-lg mb-4">
                <p class="text-lg font-semibold">{{ $latestRecord->copy->text('TABLE_HEADER_DURATION') }}:</p>
                <p class="text-3xl font-bold">{{ $latestRecord->entry['duration'] }}</p>
            </div>
            <div>
                <p class="font-semibold">{{ $latestRecord->copy->text('TABLE_HEADER_START_TIME') }}:</p>
                <p>{{ $latestRecord->entry['start'] }}</p>
            </div>
            <div>
                <p class="font-semibold">{{ $latestRecord->copy->text('TABLE_HEADER_END_TIME') }}:</p>
                <p>{{ $latestRecord->entry['end'] }}</p>
            </div>
            <div>
                <p class="font-semibold">{{ $latestRecord->copy->text('TABLE_HEADER_BREAK') }}:</p>
                <p>{{ $latestRecord->entry['pause'] }} {{ $latestRecord->copy->text('LABEL_MINUTES') }}</p>
            </div>
            <div>
                <p class="font-semibold">{{ $latestRecord->copy->text('TABLE_HEADER_LOCATION') }}:</p>
                <p>{{ $latestRecord->entry['location'] }}</p>
            </div>
            <div class="col-span-2">
                <p class="font-semibold">{{ $latestRecord->copy->text('TABLE_HEADER_COMMENT') }}:</p>
                <p>{{ $latestRecord->entry['comment'] }}</p>
            </div>
        </div>
    </div>
</div>
@else
<p>Kein Zeiteintrag gefunden.</p>
@endif
