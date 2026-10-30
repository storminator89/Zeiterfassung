@if (($ownCorrectionDecisions ?? null) !== null)
<details class="card bg-base-100 shadow-xl mt-6" id="own-correction-decisions">
    <summary class="card-body cursor-pointer font-semibold">{{ $ownCorrectionDecisions->text('title') }}</summary>
    <div class="card-body pt-0 text-sm">
        @if ($ownCorrectionDecisions->unavailable)
            <p role="status">{{ $ownCorrectionDecisions->text('unavailable') }}</p>
        @elseif ($ownCorrectionDecisions->result !== null)
            @php($decisions = $ownCorrectionDecisions->result)
            @if ($decisions['recent'] === [] && $decisions['undated'] === [])<p role="status">{{ $ownCorrectionDecisions->text('empty') }}</p>@endif
            @foreach (['recent','undated'] as $decisionGroup)
                @if ($decisions[$decisionGroup] !== [])
                    <h3 class="font-semibold">{{ $ownCorrectionDecisions->text($decisionGroup) }}</h3>
                    <ul class="space-y-2 my-3">
                    @foreach ($decisions[$decisionGroup] as $decision)<li>
                        <a class="link" href="{{ $decision['url'] }}">{{ $ownCorrectionDecisions->text('request') }} #{{ $decision['request_id'] }}</a>
                        · <span class="badge badge-outline">{{ $ownCorrectionDecisions->text('status_'.$decision['status']) }}</span>
                        · @if ($decision['datetime'] !== null)<time datetime="{{ $decision['datetime'] }}">{{ $decision['time_label'] }}</time>@else{{ $ownCorrectionDecisions->text('unknown_time') }}@endif
                    </li>@endforeach
                    </ul>
                @endif
                @if ($decisions['more_'.$decisionGroup])<p>{{ $ownCorrectionDecisions->text('more_'.$decisionGroup) }}</p>@endif
            @endforeach
            <p class="text-xs opacity-70">{{ $ownCorrectionDecisions->text('snapshot') }} <time datetime="{{ $decisions['loaded_at'] }}">{{ $decisions['loaded_at'] }}</time></p>
        @endif
        <x-chrono-help><p class="opacity-70">{{ $ownCorrectionDecisions->text('note') }}</p></x-chrono-help>
        <a class="link" href="adjustments.php?request_status=all&amp;own_requests=1">{{ $ownCorrectionDecisions->text('all') }}</a>
    </div>
</details>
@endif
