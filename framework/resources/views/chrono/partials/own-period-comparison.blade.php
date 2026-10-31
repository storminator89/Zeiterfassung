@if (($ownPeriodComparison ?? null) !== null)
@php($comparisonGerman = $page->copy->language === 'de')
<details class="card mt-4" id="own-period-comparison" @if ($ownPeriodComparison->requested) open @endif>
    <summary class="card-body cursor-pointer font-semibold">{{ $comparisonGerman ? 'Zeitraumvergleich' : $ownPeriodComparison->text('title') }}</summary>
    <div class="card-body pt-0">
        <form method="get" action="attendance.php#own-period-comparison" class="row g-3 align-items-end mb-4">
            <div class="col-sm-6 col-md-4"><label class="form-label" for="comparison-from">{{ $ownPeriodComparison->text('from') }}</label><input id="comparison-from" name="compare_from" type="date" min="0001-01-01" max="9999-12-31" value="{{ $ownPeriodComparison->from }}" required class="form-control"></div>
            <div class="col-sm-6 col-md-4"><label class="form-label" for="comparison-through">{{ $comparisonGerman ? 'Bis' : $ownPeriodComparison->text('through') }}</label><input id="comparison-through" name="compare_through" type="date" min="0001-01-01" max="9999-12-31" value="{{ $ownPeriodComparison->through }}" required class="form-control"></div>
            <div class="col-sm-auto"><button class="btn btn-outline-secondary" type="submit">{{ $comparisonGerman ? 'Vergleichen' : $ownPeriodComparison->text('check') }}</button></div>
        </form>
        @if ($ownPeriodComparison->invalid)<p class="text-danger" role="status">{{ $ownPeriodComparison->text('invalid') }}</p>
        @elseif ($ownPeriodComparison->unavailable)<p role="status">{{ $comparisonGerman ? 'Vergleich nicht verfügbar. Bitte später erneut versuchen.' : $ownPeriodComparison->text('unavailable') }}</p>
        @elseif ($ownPeriodComparison->result !== null)
            @php($comparison = $ownPeriodComparison->result)
            @if ($comparison['totals'] === null)
                <p role="status">{{ $comparisonGerman ? 'Dieser Zeitraum kann noch nicht ausgewertet werden.' : $ownPeriodComparison->text('blocked') }}</p>
                <div class="table-responsive mb-3"><table class="table table-vcenter"><tbody>
                @foreach ($comparison['issues'] as $key => $count)
                    @if ($count > 0)<tr><td>{{ $comparisonGerman ? ['unknown_start'=>'Buchungen ohne klares Datum','open'=>'Offene Buchungen','invalid'=>'Buchungen mit ungültigen Angaben','conflict'=>'Überschneidende Buchungen','model'=>'Tage ohne gültiges Arbeitszeitmodell'][$key] : sprintf($ownPeriodComparison->text($key==='invalid'?'invalid_rows':$key),$count) }}</td><td class="text-end">{{ $count }}</td></tr>@endif
                @endforeach
                </tbody></table></div>
            @else
                @php($totals = $comparison['totals'])
                <dl class="row g-3 mb-4">
                    @foreach (['work','credit','target','difference'] as $key)<div class="col-6 col-md-3"><dt class="text-secondary fw-normal">{{ $comparisonGerman ? ['work'=>'Arbeitszeit','credit'=>'Gutschrift','target'=>'Soll','difference'=>'Differenz'][$key] : $ownPeriodComparison->text($key) }}</dt><dd class="h3 mb-0">{{ $ownPeriodComparison->minutes($totals[$key.'_minutes']) }}</dd></div>@endforeach
                </dl>
                @if ($totals['unrecorded_target_days'] > 0)<p class="text-secondary">{{ $comparisonGerman ? 'Solltage ohne Buchung: '.$totals['unrecorded_target_days'] : sprintf($ownPeriodComparison->text('missing'),$totals['unrecorded_target_days']) }}</p>@endif
                <details class="mt-3"><summary class="cursor-pointer">{{ $comparisonGerman ? 'Tage anzeigen' : $ownPeriodComparison->text('details') }}</summary>
                    <div class="table-responsive mt-3"><table class="table table-vcenter">
                        <thead><tr><th>{{ $comparisonGerman ? 'Datum' : $ownPeriodComparison->text('day') }}</th>@foreach (['work','credit','target'] as $key)<th>{{ $comparisonGerman ? ['work'=>'Arbeitszeit','credit'=>'Gutschrift','target'=>'Soll'][$key] : $ownPeriodComparison->text($key) }}</th>@endforeach<th>{{ $comparisonGerman ? 'Buchungen' : $ownPeriodComparison->text('records') }}</th></tr></thead><tbody>
                        @foreach ($comparison['days'] as $day => $values)<tr><td>{{ substr(\App\View\ExistingUi\HumanTime::full($day.'T00:00:00'), 0, 10) }}</td>@foreach (['work','credit','target'] as $key)<td>{{ $ownPeriodComparison->minutes($values[$key.'_minutes']) }}</td>@endforeach<td>{{ $values['records'] }}</td></tr>@endforeach
                        </tbody>
                    </table></div>
                </details>
            @endif
        @endif
        <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
            <a href="adjustments.php">{{ $comparisonGerman ? 'Korrektur beantragen' : $ownPeriodComparison->text('correction') }}</a><a href="work_models.php">{{ $comparisonGerman ? 'Arbeitszeitmodelle' : $ownPeriodComparison->text('model_link') }}</a>
            <x-chrono-help><p>{{ $ownPeriodComparison->text('note') }}</p>
                @if ($ownPeriodComparison->result !== null)
                @if ($comparison['totals'] === null)<p>{{ $ownPeriodComparison->text('blocked') }}</p>@else<p>{{ sprintf($ownPeriodComparison->text('missing'),$totals['unrecorded_target_days']) }}</p>@endif
                <p>{{ $ownPeriodComparison->text('snapshot') }} <time datetime="{{ $comparison['loaded_at'] }}">{{ \App\View\ExistingUi\HumanTime::full($comparison['loaded_at']) }}</time></p>
                @endif
            </x-chrono-help>
        </div>
    </div>
</details>
@endif
