<section id="team-time-review" class="card bg-base-100 shadow-xl mb-8" aria-labelledby="teamTimeReviewTitle">
    <div class="card-body">
        <h3 id="teamTimeReviewTitle" class="card-title">{{ $supervisor->text('TEAM_REVIEW_TITLE') }}</h3>
        <x-chrono-help><p class="text-sm opacity-70">{{ $supervisor->text('TEAM_REVIEW_NOTE') }}</p></x-chrono-help>
        @if ($supervisor->review === null)
            <p class="mt-3"><a class="btn btn-outline" href="{{ $supervisor->reviewUrl(true) }}">{{ $supervisor->text('TEAM_REVIEW_OPEN') }}</a></p>
        @else
            <p class="mt-3"><a class="btn btn-outline" href="{{ $supervisor->reviewUrl(false) }}">{{ $supervisor->text('TEAM_REVIEW_CLOSE') }}</a></p>
            @if ($supervisor->review['state'] === 'invalid')
                <p class="alert alert-error mt-3" role="alert">{{ $supervisor->text('TEAM_REVIEW_INVALID') }}</p>
            @elseif ($supervisor->review['state'] === 'unavailable')
                <p class="alert alert-warning mt-3" role="status">{{ $supervisor->text('TEAM_REVIEW_UNAVAILABLE') }}</p>
            @else
                @php($reviewData = $supervisor->review['data'])
                <p class="mt-3">{{ sprintf($supervisor->text('TEAM_REVIEW_COUNT'), $reviewData['total']) }}</p>
                <x-chrono-help><p class="text-sm opacity-70">{{ $supervisor->text('TEAM_REVIEW_ORIGINAL_NOTE') }}</p></x-chrono-help>
                @if ($reviewData['truncated'])<p class="alert alert-warning mt-3" role="status">{{ $supervisor->text('TEAM_REVIEW_TRUNCATED') }}</p>@endif
                @if ($reviewData['rows'] === [])
                    <p class="mt-3" role="status">{{ $supervisor->text('TEAM_REVIEW_EMPTY') }}</p>
                @else
                    <div class="overflow-x-auto mt-3"><table class="table table-zebra w-full">
                        <thead><tr><th scope="col">{{ $supervisor->text('TABLE_HEADER_USERNAME') }}</th><th scope="col">{{ $supervisor->text('TEAM_REVIEW_RECORD') }}</th><th scope="col">{{ $supervisor->text('TEAM_REVIEW_START') }}</th><th scope="col">{{ $supervisor->text('TEAM_REVIEW_END') }}</th><th scope="col">{{ $supervisor->text('TEAM_REVIEW_PAUSE') }}</th><th scope="col">{{ $supervisor->text('TEAM_REVIEW_FINDINGS') }}</th></tr></thead>
                        <tbody>@foreach ($reviewData['rows'] as $row)
                            <tr><th scope="row">{{ $row['username'] }} <span class="block text-xs opacity-70">#{{ $row['user_id'] }}</span></th><td>#{{ $row['id'] }}</td>
                                @foreach (['start_original', 'end_original', 'pause_original'] as $field)<td><code style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $row[$field] }}</code></td>@endforeach
                                <td><ul class="list-disc pl-5">
                                    @foreach (['invalid' => 'TEAM_REVIEW_INVALID_ROW', 'local_time' => 'TEAM_REVIEW_LOCAL_TIME', 'work_overlap' => 'TEAM_REVIEW_WORK_OVERLAP', 'absence_conflict' => 'TEAM_REVIEW_ABSENCE_CONFLICT'] as $flag => $label)
                                        @if ($row[$flag])<li>{{ $supervisor->text($label) }}</li>@endif
                                    @endforeach
                                    @if ($row['truncated'])<li>{{ $supervisor->text('TEAM_REVIEW_TRUNCATED_VALUE') }}</li>@endif
                                </ul></td>
                            </tr>
                        @endforeach</tbody>
                    </table></div>
                @endif
            @endif
        @endif
    </div>
</section>
