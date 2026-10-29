@if ($settings->profile !== null)
    @php($profile = $settings->profile)
    <div class="divider my-8">{{ $profile->text('title') }}</div>
    <section aria-labelledby="own-settings-profile-title">
        <h3 id="own-settings-profile-title" class="text-xl font-semibold mb-3">{{ $profile->text('title') }}</h3>
        @if (!$profile->available)
            <p role="status">{{ $profile->text('unavailable') }}</p>
        @else
            <p class="text-sm opacity-70 mb-4">{{ $profile->text('as_of') }} {{ $profile->date }} · Europe/Berlin</p>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                <div><dt class="font-semibold">{{ $profile->text('department') }}</dt><dd>{{ $profile->referenceName('department') }}</dd></div>
                <div><dt class="font-semibold">{{ $profile->text('supervisor') }}</dt><dd>{{ $profile->referenceName('supervisor') }}</dd></div>
            </dl>
            <p class="text-sm opacity-70 mb-4">{{ $profile->text('assignment_note') }}</p>
            @if ($profile->model === null)
                <p>{{ $profile->text('no_model') }}</p>
            @else
                <p class="font-semibold">{{ $profile->text('model_from') }} {{ $profile->model['valid_from'] }}</p>
                <div class="overflow-x-auto mt-2">
                    <table class="table w-full">
                        <caption class="text-left">{{ $profile->text('weekly_minutes') }}</caption>
                        <thead><tr><th>{{ $profile->text('weekday') }}</th><th>{{ $profile->text('duration') }}</th></tr></thead>
                        <tbody>
                            @foreach ($profile->model['weekday_minutes'] as $index => $minutes)
                                <tr><td>{{ $profile->weekday($index) }}</td><td>{{ $profile->duration($minutes) }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr><th scope="row">{{ $profile->text('weekly_total') }}</th><td>{{ $profile->weeklyDuration() }}</td></tr></tfoot>
                    </table>
                </div>
            @endif
            <p class="text-sm opacity-70 mt-4">{{ $profile->text('model_note') }} @if ($page->interactions->workModels)<a href="work_models.php">{{ $profile->text('models_link') }}</a>@endif</p>
        @endif
    </section>
@endif
