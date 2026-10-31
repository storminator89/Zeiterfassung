@if (($ownMonthReview ?? null) !== null)
@php($monthReviewGerman = $page->copy->language === 'de')
<details class="card mt-4" id="own-month-review" @if ($ownMonthReview->requested) open @endif>
    <summary class="card-body cursor-pointer font-semibold">{{ $monthReviewGerman ? 'Monatscheck' : $ownMonthReview->text('title') }}</summary>
    <div class="card-body pt-0">
        <form method="get" action="attendance.php#own-month-review" class="row g-3 align-items-end mb-4">
            <div class="col-sm-6 col-md-4"><label class="form-label" for="own-review-month">{{ $ownMonthReview->text('month') }}</label><input id="own-review-month" name="month_review" type="month" min="0001-01" max="9999-12" value="{{ $ownMonthReview->month }}" required class="form-control"></div>
            <div class="col-sm-auto"><button class="btn btn-outline-secondary" type="submit">{{ $monthReviewGerman ? 'Prüfen' : $ownMonthReview->text('check') }}</button></div>
        </form>
        @if ($ownMonthReview->invalid)<p class="text-danger" role="status">{{ $monthReviewGerman ? 'Bitte einen gültigen Monat und eine gültige Seite wählen.' : $ownMonthReview->text('invalid') }}</p>
        @elseif ($ownMonthReview->unavailable)<p role="status">{{ $monthReviewGerman ? 'Monatscheck nicht verfügbar. Bitte später erneut versuchen.' : $ownMonthReview->text('unavailable') }}</p>
        @elseif ($ownMonthReview->result !== null)
            @php($monthReview = $ownMonthReview->result)
            @php($monthCounts = $monthReview['counts'])
            @php($closedCounts = $monthReview['closed_counts'])
            @php($monthFindings = $monthReview['findings'] ?? null)
            <div class="table-responsive mb-4"><table class="table table-vcenter">
                <thead><tr><th>{{ $monthReviewGerman ? 'Vorgang' : $ownMonthReview->text('month') }}</th><th>{{ $monthReviewGerman ? 'Im Monat' : $ownMonthReview->text('selected') }}</th><th>{{ $monthReviewGerman ? 'Früher' : $ownMonthReview->text('earlier') }}</th><th>{{ $monthReviewGerman ? 'Datum unklar' : $ownMonthReview->text('unclear') }}</th></tr></thead>
                <tbody>
                    <tr><td>{{ $monthReviewGerman ? 'Offene Buchungen' : $ownMonthReview->text('kind_open') }}</td><td>{{ $monthCounts['open_selected'] }}</td><td>{{ $monthCounts['open_earlier'] }}</td><td>{{ $monthCounts['open_unclear'] }}</td></tr>
                    <tr><td>{{ $monthReviewGerman ? 'Offene Anträge' : $ownMonthReview->text('kind_request') }}</td><td>{{ $monthCounts['pending_selected'] }}</td><td>—</td><td>{{ $monthCounts['pending_unclear'] }}</td></tr>
                    @if ($closedCounts !== null)<tr><td>{{ $monthReviewGerman ? 'Buchungen mit Prüfhinweis' : $ownMonthReview->text('kind_closed') }}</td><td>{{ $closedCounts['selected'] }}</td><td>—</td><td>{{ $closedCounts['unclear'] }}</td></tr>@endif
                </tbody>
            </table></div>
            @if ($monthFindings !== null && $monthFindings['page'] > $monthFindings['total_pages'])<p role="status">{{ $monthReviewGerman ? 'Diese Seite ist nicht mehr verfügbar. Bitte zur ersten Seite wechseln.' : $ownMonthReview->text('finding_outside') }}</p>@endif
            @if (array_sum($monthCounts) + array_sum($closedCounts ?? []) === 0)<p role="status">{{ $monthReviewGerman ? 'Keine Treffer in diesen Kategorien.' : $ownMonthReview->text('empty') }}</p>
            @else
                <div class="table-responsive"><table class="table table-vcenter">
                    <thead><tr><th>{{ $monthReviewGerman ? 'Eintrag' : $ownMonthReview->text('samples') }}</th><th>{{ $monthReviewGerman ? 'Zeitraum' : $ownMonthReview->text('month') }}</th><th></th></tr></thead><tbody>
                    @foreach ($monthFindings['rows'] ?? $monthReview['samples'] as $sample)
                    <tr><td>{{ $monthReviewGerman ? ['open'=>'Offene Buchung','request'=>'Offener Antrag','closed'=>'Buchung mit Prüfhinweis'][$sample['kind']] : $ownMonthReview->text('kind_'.$sample['kind']) }} #{{ $sample['id'] }}</td><td>{{ $monthReviewGerman ? ['selected'=>'Im Monat','earlier'=>'Früher','unclear'=>'Datum unklar'][$sample['relation']] : $ownMonthReview->text($sample['relation']) }}</td><td class="text-end">@if ($sample['kind'] !== 'request')<a href="adjustments.php?record_id={{ rawurlencode($sample['id']) }}">{{ $monthReviewGerman ? 'Prüfen' : $ownMonthReview->text('correction') }}</a>@else<a href="adjustments.php?request_status=pending&amp;request_id={{ rawurlencode($sample['id']) }}">{{ $monthReviewGerman ? 'Ansehen' : $ownMonthReview->text('pending_link') }}</a>@endif</td></tr>
                    @endforeach
                    </tbody></table></div>
            @endif
            @if ($monthFindings !== null)
                @if ($monthFindings['total_pages'] > 1)
                <nav class="d-flex flex-wrap align-items-center gap-3 my-3" aria-label="{{ $ownMonthReview->text('finding_navigation') }}">
                    <span class="text-secondary">{{ $monthReviewGerman ? 'Seite '.$monthFindings['page'].' von '.$monthFindings['total_pages'] : sprintf($ownMonthReview->text('finding_page'), $monthFindings['page'], $monthFindings['total_pages'], $monthFindings['total']) }}</span>
                    @if ($monthFindings['page'] > 1)<a class="btn btn-outline-secondary btn-sm" href="{{ $ownMonthReview->findingUrl(1) }}">{{ $ownMonthReview->text('finding_first') }}</a>@endif
                    @if ($monthFindings['page'] > 1 && $monthFindings['page'] <= $monthFindings['total_pages'])<a class="btn btn-outline-secondary btn-sm" href="{{ $ownMonthReview->findingUrl($monthFindings['page'] - 1) }}">{{ $ownMonthReview->text('finding_previous') }}</a>@endif
                    @if ($monthFindings['page'] < $monthFindings['total_pages'])<a class="btn btn-outline-secondary btn-sm" href="{{ $ownMonthReview->findingUrl($monthFindings['page'] + 1) }}">{{ $ownMonthReview->text('finding_next') }}</a>@endif
                </nav>
                @elseif ($monthFindings['page'] > 1)<a class="btn btn-outline-secondary btn-sm my-3" href="{{ $ownMonthReview->findingUrl(1) }}">{{ $ownMonthReview->text('finding_first') }}</a>@endif
            @else
                @if ($monthReview['limited'])<p class="text-secondary mt-3">{{ $monthReviewGerman ? 'Weitere Treffer vorhanden.' : $ownMonthReview->text('limited') }}</p>@endif
                @if ($ownMonthReview->findingsAvailable && array_sum($monthCounts) + array_sum($closedCounts ?? []) > 0)<a class="btn btn-outline-secondary btn-sm my-3" href="{{ $ownMonthReview->findingUrl(1) }}">{{ $monthReviewGerman ? 'Treffer anzeigen' : $ownMonthReview->text('finding_all') }}</a>@endif
            @endif
        @endif
        <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
            <a href="adjustments.php?request_status=pending">{{ $monthReviewGerman ? 'Offene Anträge' : $ownMonthReview->text('pending_link') }}</a><a href="attendance.php#timeRecordsContainer">{{ $monthReviewGerman ? 'Meine Buchungen' : $ownMonthReview->text('review_link') }}</a>
            <x-chrono-help><p>{{ $ownMonthReview->text('note') }}</p>
                @if ($ownMonthReview->result !== null)<p>{{ sprintf($ownMonthReview->text('open_counts'), $monthCounts['open_selected'], $monthCounts['open_earlier'], $monthCounts['open_unclear']) }} {{ sprintf($ownMonthReview->text('pending_counts'), $monthCounts['pending_selected'], $monthCounts['pending_unclear']) }}</p>
                @if ($closedCounts !== null)<p>{{ sprintf($ownMonthReview->text('closed_counts'), $closedCounts['selected'], $closedCounts['unclear']) }}</p>@endif
                <p>{{ $ownMonthReview->text($monthFindings !== null ? 'finding_order' : 'samples') }}</p>
                @if ($monthFindings !== null)<p>{{ sprintf($ownMonthReview->text('finding_page'), $monthFindings['page'], $monthFindings['total_pages'], $monthFindings['total']) }} {{ $ownMonthReview->text('finding_live') }}</p>@endif
                <p>{{ $ownMonthReview->text('snapshot') }} <time datetime="{{ $monthReview['loaded_at'] }}">{{ \App\View\ExistingUi\HumanTime::full($monthReview['loaded_at']) }}</time></p>@endif
            </x-chrono-help>
        </div>
    </div>
</details>
@endif
