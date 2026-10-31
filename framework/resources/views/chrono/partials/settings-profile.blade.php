@if ($settings->profile !== null)
    @php($profile = $settings->profile)
    @php($profileGerman = $page->copy->language === 'de')
    <section aria-labelledby="own-settings-profile-title" class="mt-3">
        <h3 id="own-settings-profile-title" class="visually-hidden">{{ $profileGerman ? 'Mein Profil' : $profile->text('title') }}</h3>
        @if (!$profile->available)
            <p role="status">{{ $profileGerman ? 'Profil nicht verfügbar. Bitte später erneut versuchen.' : $profile->text('unavailable') }}</p>
        @else
            <dl class="row g-3 mb-4">
                <div class="col-sm-6"><dt class="text-secondary fw-normal">{{ $profileGerman ? 'Abteilung' : $profile->text('department') }}</dt><dd>{{ $profile->referenceName('department') }}</dd></div>
                <div class="col-sm-6"><dt class="text-secondary fw-normal">{{ $profileGerman ? 'Vorgesetzte Person' : $profile->text('supervisor') }}</dt><dd>{{ $profile->referenceName('supervisor') }}</dd></div>
            </dl>
            @if ($profile->model === null)
                <p role="status">{{ $profileGerman ? 'Kein Arbeitszeitmodell für diesen Tag hinterlegt.' : $profile->text('no_model') }}</p>
            @else
                <div class="table-responsive"><table class="table table-vcenter">
                    <thead><tr><th>{{ $profileGerman ? 'Tag' : $profile->text('weekday') }}</th><th class="text-end">{{ $profileGerman ? 'Sollzeit' : $profile->text('duration') }}</th></tr></thead><tbody>
                    @foreach ($profile->model['weekday_minutes'] as $index => $minutes)<tr><td>{{ $profile->weekday($index) }}</td><td class="text-end">{{ $profile->duration($minutes) }}</td></tr>@endforeach
                    </tbody><tfoot><tr><th scope="row">{{ $profileGerman ? 'Woche' : $profile->text('weekly_total') }}</th><td class="text-end fw-semibold">{{ $profile->weeklyDuration() }}</td></tr></tfoot>
                </table></div>
            @endif
            <div class="d-flex align-items-center gap-3 mt-3">
                @if ($page->interactions->workModels)<a href="work_models.php">{{ $profileGerman ? 'Arbeitszeitmodelle' : $profile->text('models_link') }}</a>@endif
                <x-chrono-help><p>{{ $profile->text('assignment_note') }}</p><p>{{ $profile->text('model_note') }}</p>
                    <p>{{ $profile->text('as_of') }} {{ $profile->date }} (Europe/Berlin)</p>
                    @if ($profile->model !== null)<p>{{ $profile->text('model_from') }} {{ $profile->model['valid_from'] }}. {{ $profile->text('weekly_minutes') }}.</p>@else<p>{{ $profile->text('no_model') }}</p>@endif
                </x-chrono-help>
            </div>
        @endif
    </section>
@endif
