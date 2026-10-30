@if (($ownMonthReview ?? null) !== null)
<details class="card bg-base-100 shadow-xl mt-6" id="own-month-review" @if ($ownMonthReview->requested) open @endif>
    <summary class="card-body cursor-pointer font-semibold">{{ $ownMonthReview->text('title') }}</summary>
    <div class="card-body pt-0 text-sm">
        <form method="get" action="attendance.php#own-month-review" class="flex flex-wrap items-end gap-2">
            <label class="form-control" for="own-review-month"><span class="label-text">{{ $ownMonthReview->text('month') }}</span><input id="own-review-month" name="month_review" type="month" min="0001-01" max="9999-12" value="{{ $ownMonthReview->month }}" required class="input input-bordered"></label>
            <button class="btn btn-outline" type="submit">{{ $ownMonthReview->text('check') }}</button>
        </form>
        @if ($ownMonthReview->invalid)<p role="status">{{ $ownMonthReview->text('invalid') }}</p>
        @elseif ($ownMonthReview->unavailable)<p role="status">{{ $ownMonthReview->text('unavailable') }}</p>
        @elseif ($ownMonthReview->result !== null)
            @php($monthReview = $ownMonthReview->result)
            @php($monthCounts = $monthReview['counts'])
            <p>{{ sprintf($ownMonthReview->text('open_counts'), $monthCounts['open_selected'], $monthCounts['open_earlier'], $monthCounts['open_unclear']) }}</p>
            <p>{{ sprintf($ownMonthReview->text('pending_counts'), $monthCounts['pending_selected'], $monthCounts['pending_unclear']) }}</p>
            @php($closedCounts = $monthReview['closed_counts'])
            @if ($closedCounts !== null)<p>{{ sprintf($ownMonthReview->text('closed_counts'), $closedCounts['selected'], $closedCounts['unclear']) }}</p>@endif
            @php($monthFindings = $monthReview['findings'] ?? null)
            @if ($monthFindings !== null)<p>{{ $ownMonthReview->text('finding_order') }}</p>
                @if ($monthFindings['page'] > $monthFindings['total_pages'])<p role="status">{{ $ownMonthReview->text('finding_outside') }}</p>@endif
            @endif
            @if (array_sum($monthCounts) + array_sum($closedCounts ?? []) === 0)<p role="status">{{ $ownMonthReview->text('empty') }}</p>
            @else
                @if ($monthFindings === null)<p>{{ $ownMonthReview->text('samples') }}</p>@endif
                <ul class="list-disc pl-5">
                @foreach ($monthFindings['rows'] ?? $monthReview['samples'] as $sample)<li>{{ $ownMonthReview->text('kind_'.$sample['kind']) }} #{{ $sample['id'] }} · {{ $ownMonthReview->text($sample['relation']) }}@if ($sample['kind'] !== 'request') · <a class="link" href="adjustments.php?record_id={{ rawurlencode($sample['id']) }}">{{ $ownMonthReview->text('correction') }}</a>@else · <a class="link" href="adjustments.php?request_status=pending&amp;request_id={{ rawurlencode($sample['id']) }}">{{ $ownMonthReview->text('pending_link') }}</a>@endif</li>@endforeach
                </ul>
            @endif
            @if ($monthFindings !== null)
                <nav class="flex flex-wrap items-center gap-3 my-3" aria-label="{{ $ownMonthReview->text('finding_navigation') }}">
                    <span>{{ sprintf($ownMonthReview->text('finding_page'), $monthFindings['page'], $monthFindings['total_pages'], $monthFindings['total']) }}</span>
                    @if ($monthFindings['page'] > 1)<a class="btn btn-outline btn-sm" href="{{ $ownMonthReview->findingUrl(1) }}">{{ $ownMonthReview->text('finding_first') }}</a>@endif
                    @if ($monthFindings['page'] > 1 && $monthFindings['page'] <= $monthFindings['total_pages'])<a class="btn btn-outline btn-sm" href="{{ $ownMonthReview->findingUrl($monthFindings['page'] - 1) }}">{{ $ownMonthReview->text('finding_previous') }}</a>@endif
                    @if ($monthFindings['page'] < $monthFindings['total_pages'])<a class="btn btn-outline btn-sm" href="{{ $ownMonthReview->findingUrl($monthFindings['page'] + 1) }}">{{ $ownMonthReview->text('finding_next') }}</a>@endif
                </nav><p class="opacity-70">{{ $ownMonthReview->text('finding_live') }}</p>
            @else
                @if ($monthReview['limited'])<p>{{ $ownMonthReview->text('limited') }}</p>@endif
                @if ($ownMonthReview->findingsAvailable)<a class="btn btn-outline btn-sm my-3" href="{{ $ownMonthReview->findingUrl(1) }}">{{ $ownMonthReview->text('finding_all') }}</a>@endif
            @endif
            <p class="text-xs opacity-70">{{ $ownMonthReview->text('snapshot') }} <time datetime="{{ $monthReview['loaded_at'] }}">{{ $monthReview['loaded_at'] }}</time></p>
        @endif
        <p class="opacity-70">{{ $ownMonthReview->text('note') }}</p>
        <div class="flex flex-wrap gap-3"><a class="link" href="adjustments.php?request_status=pending">{{ $ownMonthReview->text('pending_link') }}</a><a class="link" href="attendance.php#timeRecordsContainer">{{ $ownMonthReview->text('review_link') }}</a></div>
    </div>
</details>
@endif
