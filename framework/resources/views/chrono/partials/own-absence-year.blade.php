<section id="own-absence-year" class="card mb-4">
    <div class="card-header"><h2 class="card-title"><x-chrono-icon name="calendar-check" size="20" /> {{ $absenceYearCopy['title'] }}</h2></div><div class="card-body">
    <x-chrono-help label="Berechnung der Abwesenheiten"><p>{{ $absenceYearCopy['note'] }}</p><p>{{ $absenceYearCopy['limit'] }}</p>@if ($absenceYearData !== null)<p>{{ $absenceYearCopy['loaded_at'].': '.$absenceYearData['loaded_at'] }}</p>@endif</x-chrono-help>
    @if ($absenceYearState === 'idle')
        <a class="btn btn-outline-secondary mt-3" href="{{ chrono_own_absence_year_link($absenceYearCurrent, 1, $absenceYearContext) }}">{{ $absenceYearCopy['load'] }}</a>
    @else
        @php($absenceYearSelected = $absenceYearData['year'] ?? $absenceYearCurrent)
        <form method="get" action="dashboard.php#own-absence-year" class="d-flex flex-wrap align-items-end gap-2 mt-3 mb-3">
            @foreach ($absenceYearContext as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            <div><label for="own-absence-year-input" class="form-label">{{ $absenceYearCopy['year'] }}</label><input id="own-absence-year-input" type="number" name="absence_year" min="1" max="9999" step="1" required value="{{ $absenceYearSelected }}" class="form-control"></div>
            <button type="submit" class="btn btn-outline-secondary">{{ $absenceYearCopy['show'] }}</button>
        </form>
        @if (in_array($absenceYearState, ['invalid', 'unavailable'], true))
            <p role="status" class="mt-3">{{ $absenceYearCopy[$absenceYearState] }}</p>
        @elseif ($absenceYearData !== null)
            @if ($absenceYearData['state'] !== 'current')<div role="status" class="text-warning mt-3">Summen noch nicht abschließend. <x-chrono-help><p>{{ $absenceYearCopy['quality'] }}</p></x-chrono-help></div>@endif
            @if ($absenceYearData['unknown_date_records'] > 0)<div class="text-warning mt-3">{{ $absenceYearData['unknown_date_records'] }} Buchungen ohne eindeutiges Datum. <x-chrono-help><p>{{ $absenceYearCopy['unknown_note'] }}</p></x-chrono-help></div>@endif
            @if ($absenceYearData['total_records'] > 0)
            <div class="overflow-x-auto mt-3"><table class="table table-vcenter"><thead><tr>@foreach (['kind', 'phase', 'records', 'days', 'minutes'] as $key)<th>{{ $absenceYearCopy[$key] }}</th>@endforeach</tr></thead><tbody>
                @foreach ($absenceYearData['summary'] as $kind => $phases)@foreach ($phases as $phase => $values)
                    @if ($values['records'] > 0)
                    <tr><td>{{ $absenceYearCopy[$kind] }}</td><td>{{ $absenceYearCopy[$phase] }}</td><td>{{ $values['records'] }}</td><td>{{ $values['positive_days'] ?? $absenceYearCopy[$absenceYearData['state']] }}</td><td>{{ $values['minutes'] ?? $absenceYearCopy[$absenceYearData['state']] }}</td></tr>
                    @endif
                @endforeach @endforeach
            </tbody></table></div>
            @endif
            @if ($absenceYearData['total_records'] > 0)<h3 class="card-title mt-4">{{ $absenceYearCopy['details'] }}</h3>@endif
            @if ($absenceYearData['rows'] === [])<div class="d-flex align-items-center gap-3 text-secondary mt-2"><x-chrono-icon name="calendar-off" size="28" /><p class="mb-0">{{ $absenceYearCopy['empty'] }}</p></div>
            @else
                <div class="overflow-x-auto mt-2"><table class="table table-vcenter"><thead><tr>@foreach (['record', 'date', 'kind', 'phase', 'minutes', 'state'] as $key)<th>{{ $absenceYearCopy[$key] }}</th>@endforeach</tr></thead><tbody>
                    @foreach ($absenceYearData['rows'] as $row)
                        <tr><td>@if ($row['id'] !== null)<a class="link" href="{{ 'adjustments.php?record_id='.rawurlencode($row['id']) }}" aria-label="{{ $absenceYearCopy['review'].' #'.$row['id'] }}">#{{ $row['id'] }}</a>@else — @endif</td><td>{{ $row['day'] !== null ? (new \DateTimeImmutable($row['day']))->format('d.m.Y') : $absenceYearCopy['unknown'] }}</td><td>{{ $absenceYearCopy[$row['kind']] }}</td><td>{{ $absenceYearCopy[$row['phase']] }}</td><td>{{ $row['minutes'] ?? $absenceYearCopy['incomplete'] }}</td><td>{{ $absenceYearCopy[$row['state']] }}</td></tr>
                    @endforeach
                </tbody></table></div>
            @endif
            @if ($absenceYearData['pages'] > 1 && $absenceYearData['total_records'] > 0)
            <nav class="d-flex flex-wrap gap-3 mt-3" aria-label="{{ $absenceYearCopy['details'] }}">
                @if ($absenceYearData['page'] > 1)<a class="link" href="{{ chrono_own_absence_year_link($absenceYearSelected, $absenceYearData['page'] - 1, $absenceYearContext) }}">{{ $absenceYearCopy['previous'] }}</a>@endif
                <span>{{ $absenceYearCopy['page'].' '.$absenceYearData['page'].' '.$absenceYearCopy['of'].' '.$absenceYearData['pages'] }}</span>
                @if ($absenceYearData['page'] < $absenceYearData['pages'])<a class="link" href="{{ chrono_own_absence_year_link($absenceYearSelected, $absenceYearData['page'] + 1, $absenceYearContext) }}">{{ $absenceYearCopy['next'] }}</a>@endif
            </nav>
            @endif
        @endif
    @endif
</div></section>
