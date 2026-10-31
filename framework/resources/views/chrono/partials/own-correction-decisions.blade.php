@if (($ownCorrectionDecisions ?? null) !== null)
@php($decisionsGerman = $page->copy->language === 'de')
<details class="card mt-4" id="own-correction-decisions">
    <summary class="card-body cursor-pointer font-semibold">{{ $decisionsGerman ? 'Entschiedene Korrekturen' : $ownCorrectionDecisions->text('title') }}</summary>
    <div class="card-body pt-0">
        @if ($ownCorrectionDecisions->unavailable)
            <p role="status">{{ $decisionsGerman ? 'Entscheidungen nicht verfügbar. Bitte die Antragsliste öffnen.' : $ownCorrectionDecisions->text('unavailable') }}</p>
        @elseif ($ownCorrectionDecisions->result !== null)
            @php($decisions = $ownCorrectionDecisions->result)
            @if ($decisions['recent'] === [] && $decisions['undated'] === [])<p role="status">{{ $decisionsGerman ? 'Noch keine entschiedenen Korrekturen.' : $ownCorrectionDecisions->text('empty') }}</p>
            @else
                <div class="table-responsive"><table class="table table-vcenter">
                    <thead><tr><th>{{ $ownCorrectionDecisions->text('request') }}</th><th>{{ $decisionsGerman ? 'Status' : $ownCorrectionDecisions->text('title') }}</th><th>{{ $decisionsGerman ? 'Entschieden am' : $ownCorrectionDecisions->text('recent') }}</th></tr></thead><tbody>
                    @foreach (['recent','undated'] as $decisionGroup)
                        @foreach ($decisions[$decisionGroup] as $decision)
                        <tr><td><a href="{{ $decision['url'] }}">#{{ $decision['request_id'] }}</a></td><td>{{ $ownCorrectionDecisions->text('status_'.$decision['status']) }}</td><td>@if ($decision['datetime'] !== null)<time datetime="{{ $decision['datetime'] }}" title="{{ $decision['time_label'] }}">{{ \App\View\ExistingUi\HumanTime::full($decision['datetime']) }}</time>@else{{ $ownCorrectionDecisions->text('unknown_time') }}@endif</td></tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table></div>
                @if ($decisions['more_recent'] || $decisions['more_undated'])<p class="text-secondary mt-3">{{ $decisionsGerman ? 'Weitere Anträge in der Antragsliste.' : $ownCorrectionDecisions->text($decisions['more_recent'] ? 'more_recent' : 'more_undated') }}</p>@endif
            @endif
        @endif
        <div class="d-flex align-items-center gap-3 mt-3">
            <a href="adjustments.php?request_status=all&amp;own_requests=1">{{ $decisionsGerman ? 'Meine Anträge' : $ownCorrectionDecisions->text('all') }}</a>
            <x-chrono-help><p>{{ $ownCorrectionDecisions->text('note') }}</p>
                @if ($ownCorrectionDecisions->result !== null)
                @foreach (['recent','undated'] as $decisionGroup)@if ($decisions['more_'.$decisionGroup])<p>{{ $ownCorrectionDecisions->text('more_'.$decisionGroup) }}</p>@endif @endforeach
                <p>{{ $ownCorrectionDecisions->text('snapshot') }} <time datetime="{{ $decisions['loaded_at'] }}">{{ \App\View\ExistingUi\HumanTime::full($decisions['loaded_at']) }}</time></p>
                @endif
            </x-chrono-help>
        </div>
    </div>
</details>
@endif
