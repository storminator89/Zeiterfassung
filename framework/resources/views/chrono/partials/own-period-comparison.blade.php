@if (($ownPeriodComparison ?? null) !== null)
<details class="card bg-base-100 shadow-xl mt-6" id="own-period-comparison" @if ($ownPeriodComparison->requested) open @endif>
    <summary class="card-body cursor-pointer font-semibold">{{ $ownPeriodComparison->text('title') }}</summary>
    <div class="card-body pt-0 text-sm">
        <form method="get" action="attendance.php#own-period-comparison" class="flex flex-wrap items-end gap-2">
            <label class="form-control" for="comparison-from"><span class="label-text">{{ $ownPeriodComparison->text('from') }}</span><input id="comparison-from" name="compare_from" type="date" min="0001-01-01" max="9999-12-31" value="{{ $ownPeriodComparison->from }}" required class="input input-bordered"></label>
            <label class="form-control" for="comparison-through"><span class="label-text">{{ $ownPeriodComparison->text('through') }}</span><input id="comparison-through" name="compare_through" type="date" min="0001-01-01" max="9999-12-31" value="{{ $ownPeriodComparison->through }}" required class="input input-bordered"></label>
            <button class="btn btn-outline" type="submit">{{ $ownPeriodComparison->text('check') }}</button>
        </form>
        @if ($ownPeriodComparison->invalid)<p role="status">{{ $ownPeriodComparison->text('invalid') }}</p>
        @elseif ($ownPeriodComparison->unavailable)<p role="status">{{ $ownPeriodComparison->text('unavailable') }}</p>
        @elseif ($ownPeriodComparison->result !== null)
            @php($comparison = $ownPeriodComparison->result)
            @if ($comparison['totals'] === null)<p role="status">{{ $ownPeriodComparison->text('blocked') }}</p><ul class="list-disc pl-5">
                @foreach ($comparison['issues'] as $key => $count) @if ($count > 0)<li>{{ sprintf($ownPeriodComparison->text($key==='invalid'?'invalid_rows':$key),$count) }}</li>@endif @endforeach
            </ul>
            @else
            @php($totals = $comparison['totals'])<dl class="grid sm:grid-cols-2 gap-2">
                @foreach (['work','credit','target','difference'] as $key)<div><dt class="opacity-70">{{ $ownPeriodComparison->text($key) }}</dt><dd class="font-semibold">{{ $ownPeriodComparison->minutes($totals[$key.'_minutes']) }}</dd></div>@endforeach
            </dl><p>{{ sprintf($ownPeriodComparison->text('missing'),$totals['unrecorded_target_days']) }}</p>
            <details><summary class="cursor-pointer font-semibold">{{ $ownPeriodComparison->text('details') }}</summary><div class="overflow-x-auto"><table class="table w-full"><thead><tr><th>{{ $ownPeriodComparison->text('day') }}</th><th>{{ $ownPeriodComparison->text('work') }}</th><th>{{ $ownPeriodComparison->text('credit') }}</th><th>{{ $ownPeriodComparison->text('target') }}</th><th>{{ $ownPeriodComparison->text('records') }}</th></tr></thead><tbody>
                @foreach ($comparison['days'] as $day => $values)<tr><td>{{ $day }}</td>@foreach (['work','credit','target'] as $key)<td>{{ $ownPeriodComparison->minutes($values[$key.'_minutes']) }}</td>@endforeach<td>{{ $values['records'] }}</td></tr>@endforeach
            </tbody></table></div></details>@endif
            <p class="text-xs opacity-70">{{ $ownPeriodComparison->text('snapshot') }} <time datetime="{{ $comparison['loaded_at'] }}">{{ $comparison['loaded_at'] }}</time></p>
        @endif
        <p class="opacity-70">{{ $ownPeriodComparison->text('note') }}</p>
        <div class="flex flex-wrap gap-3"><a class="link" href="adjustments.php">{{ $ownPeriodComparison->text('correction') }}</a><a class="link" href="work_models.php">{{ $ownPeriodComparison->text('model_link') }}</a></div>
    </div>
</details>
@endif
