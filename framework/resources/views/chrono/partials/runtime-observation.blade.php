<section id="runtime-observation" class="card bg-base-100 shadow-xl mb-8" aria-labelledby="runtime-observation-title">
    <div class="card-body">
        <h2 id="runtime-observation-title" class="card-title text-2xl mb-4">{{ $observation->text('title') }}</h2>
        <p class="text-sm opacity-70 mb-4">{{ $observation->text('scope') }}</p>
        <details @if ($observation->requested) open @endif>
            <summary>{{ $observation->text('title') }}</summary>
            <div class="flex flex-wrap gap-3 my-4">
                <a class="btn btn-outline" href="{{ $observation->url() }}">{{ $observation->text('storage_action') }}</a>
                <a class="btn btn-outline" href="{{ $observation->url(true) }}">{{ $observation->text('terminal_action') }}</a>
            </div>
            @if (!$observation->requested)
                <p>{{ $observation->text('idle') }}</p>
            @elseif ($observation->measurements === null)
                <p class="alert alert-warning" role="status">{{ $observation->text('unavailable') }}</p>
            @else
                @php($storage = $observation->measurements['storage'])
                @php($contact = $observation->measurements['terminal_contact'])
                <p class="text-sm opacity-70 mb-3">{{ $observation->text('checked_at') }}: <time>{{ $observation->checkedAt }}</time></p>
                <h3 class="font-semibold">{{ $observation->text('storage_title') }}</h3>
                <p @if ($storage['state'] !== 'sufficient') class="alert alert-warning my-3" @endif>{{ $observation->text($storage['state']) }}</p>
                <dl class="mb-4">
                    @if ($storage['available_bytes'] !== null)<div><dt>{{ $observation->text('available_bytes') }}</dt><dd>{{ $observation->number($storage['available_bytes']) }}</dd></div>@endif
                    <div><dt>{{ $observation->text('minimum_bytes') }}</dt><dd>{{ $observation->number($storage['minimum_bytes']) }}</dd></div>
                </dl>
                <h3 class="font-semibold">{{ $observation->text('terminal_title') }}</h3>
                <p>{{ $observation->text($contact['state']) }}</p>
                @if ($contact['state'] !== 'not_selected')
                    <p class="mt-3">{{ $observation->text('scanned') }}: {{ $observation->number($contact['scanned']) }}</p>
                    <div class="overflow-x-auto my-3">
                        <table class="table w-full">
                            <tbody>@foreach ($contact['counts'] as $kind => $count)<tr><th scope="row">{{ $observation->text('count_'.$kind) }}</th><td>{{ $observation->number($count) }}</td></tr>@endforeach</tbody>
                        </table>
                    </div>
                    @if ($contact['observed_age_seconds']['newest'] === null)
                        <p>{{ $observation->text('no_age') }}</p>
                    @else
                        <dl>@foreach ($contact['observed_age_seconds'] as $kind => $age)<div><dt>{{ $observation->text($kind) }}</dt><dd>{{ $observation->number($age) }}</dd></div>@endforeach</dl>
                    @endif
                    <p class="text-sm opacity-70 mt-3">{{ $observation->text('bounded_note') }}</p>
                @endif
            @endif
            <p class="text-sm opacity-70 mt-4">{{ $observation->text('unassessed') }}</p>
            <p class="text-sm opacity-70 mt-2">{{ $observation->text('runbook') }}: <code>docs/framework/operational-diagnostics.md</code></p>
        </details>
    </div>
</section>
