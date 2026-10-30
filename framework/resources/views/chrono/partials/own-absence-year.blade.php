<details id="own-absence-year" class="mb-6 text-sm border border-base-300 rounded-box p-3" @if ($absenceYearState !== 'idle') open @endif>
    <summary class="cursor-pointer font-semibold">{{ $absenceYearCopy['title'] }}</summary>
    <p class="mt-3">{{ $absenceYearCopy['note'] }}</p><p class="mt-2 opacity-70">{{ $absenceYearCopy['limit'] }}</p>
    @if ($absenceYearState === 'idle')
        <a class="btn btn-outline btn-sm mt-3" href="{{ chrono_own_absence_year_link($absenceYearCurrent, 1, $absenceYearContext) }}">{{ $absenceYearCopy['load'] }}</a>
    @else
        @php($absenceYearSelected = $absenceYearData['year'] ?? $absenceYearCurrent)
        <form method="get" action="dashboard.php#own-absence-year" class="flex flex-wrap items-end gap-2 mt-3">
            @foreach ($absenceYearContext as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            <label for="own-absence-year-input">{{ $absenceYearCopy['year'] }}<input id="own-absence-year-input" type="number" name="absence_year" min="1" max="9999" step="1" required value="{{ $absenceYearSelected }}" class="input input-bordered input-sm w-28"></label>
            <button type="submit" class="btn btn-outline btn-sm">{{ $absenceYearCopy['show'] }}</button>
        </form>
        @if (in_array($absenceYearState, ['invalid', 'unavailable'], true))
            <p role="status" class="mt-3">{{ $absenceYearCopy[$absenceYearState] }}</p>
        @elseif ($absenceYearData !== null)
            <p class="mt-3 opacity-70">{{ $absenceYearCopy['loaded_at'].': '.$absenceYearData['loaded_at'] }}</p>
            @if ($absenceYearData['state'] !== 'current')<p role="status" class="alert alert-warning mt-3">{{ $absenceYearCopy['quality'] }}</p>@endif
            @if ($absenceYearData['unknown_date_records'] > 0)<p class="mt-3">{{ $absenceYearCopy['unknown_note'] }} ({{ $absenceYearData['unknown_date_records'] }})</p>@endif
            <div class="overflow-x-auto mt-3"><table class="table w-full"><thead><tr>@foreach (['kind', 'phase', 'records', 'days', 'minutes'] as $key)<th>{{ $absenceYearCopy[$key] }}</th>@endforeach</tr></thead><tbody>
                @foreach ($absenceYearData['summary'] as $kind => $phases)@foreach ($phases as $phase => $values)
                    <tr><td>{{ $absenceYearCopy[$kind] }}</td><td>{{ $absenceYearCopy[$phase] }}</td><td>{{ $values['records'] }}</td><td>{{ $values['positive_days'] ?? $absenceYearCopy[$absenceYearData['state']] }}</td><td>{{ $values['minutes'] ?? $absenceYearCopy[$absenceYearData['state']] }}</td></tr>
                @endforeach @endforeach
            </tbody></table></div>
            <h3 class="font-semibold mt-4">{{ $absenceYearCopy['details'] }} ({{ $absenceYearData['total_records'] }})</h3>
            @if ($absenceYearData['rows'] === [])<p class="mt-2">{{ $absenceYearCopy['empty'] }}</p>
            @else
                <div class="overflow-x-auto mt-2"><table class="table w-full"><thead><tr>@foreach (['record', 'date', 'kind', 'phase', 'minutes', 'state'] as $key)<th>{{ $absenceYearCopy[$key] }}</th>@endforeach</tr></thead><tbody>
                    @foreach ($absenceYearData['rows'] as $row)
                        <tr><td>@if ($row['id'] !== null)<a class="link" href="{{ 'adjustments.php?record_id='.rawurlencode($row['id']) }}" aria-label="{{ $absenceYearCopy['review'].' #'.$row['id'] }}">#{{ $row['id'] }}</a>@else — @endif</td><td>{{ $row['day'] ?? $absenceYearCopy['unknown'] }}</td><td>{{ $absenceYearCopy[$row['kind']] }}</td><td>{{ $absenceYearCopy[$row['phase']] }}</td><td>{{ $row['minutes'] ?? $absenceYearCopy['incomplete'] }}</td><td>{{ $absenceYearCopy[$row['state']] }}</td></tr>
                    @endforeach
                </tbody></table></div>
            @endif
            <nav class="flex flex-wrap gap-3 mt-3" aria-label="{{ $absenceYearCopy['details'] }}">
                @if ($absenceYearData['page'] > 1)<a class="link" href="{{ chrono_own_absence_year_link($absenceYearSelected, $absenceYearData['page'] - 1, $absenceYearContext) }}">{{ $absenceYearCopy['previous'] }}</a>@endif
                <span>{{ $absenceYearCopy['page'].' '.$absenceYearData['page'].' '.$absenceYearCopy['of'].' '.$absenceYearData['pages'] }}</span>
                @if ($absenceYearData['page'] < $absenceYearData['pages'])<a class="link" href="{{ chrono_own_absence_year_link($absenceYearSelected, $absenceYearData['page'] + 1, $absenceYearContext) }}">{{ $absenceYearCopy['next'] }}</a>@endif
            </nav>
        @endif
    @endif
</details>
