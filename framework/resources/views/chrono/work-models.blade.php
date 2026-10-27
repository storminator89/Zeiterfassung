@extends('chrono.layouts.app')

@section('content')
<main class="container mx-auto px-4 py-8 chrono-page">
    <h1 class="text-2xl font-bold mb-6">Arbeitszeitmodelle</h1>
    <p>Das jüngste Modell mit passendem Gültigkeitsdatum bestimmt die Sollzeit. Feiertage aus dem hinterlegten Feiertagskalender haben null Sollminuten. Bereits erfasste Abwesenheitsgutschriften bleiben unverändert und können über einen Korrekturantrag angepasst werden.</p>
    @if ($workModels->error)<div class="alert alert-error mb-6" role="alert">{{ $workModels->error }}</div>@endif
    @if ($workModels->saved)<div class="alert alert-success mb-6">Arbeitszeitmodell gespeichert.</div>@endif

    @if ($page->role === 'admin')
    <section class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Neues Modell hinterlegen</h2>
        <p class="opacity-70">Ein neues Modell ersetzt keine Historie. Bei gleichem Gültigkeitsdatum gilt die zuletzt gespeicherte Version. Rückwirkende Modelle ändern Sollzeit-Auswertungen, aber keine gebuchten Gutschriften.</p>
        <form method="post">
            @csrf
            <fieldset @disabled(! $workModels->enabled)>
                <label class="block text-sm font-semibold mt-4 mb-2" for="user_id">Person</label>
                <select class="select select-bordered w-full" id="user_id" name="user_id">
                    @foreach ($workModels->targetOptions as $u)
                    <option value="{{ (int) $u['id'] }}">{{ $u['username'] }}</option>
                    @endforeach
                </select>
                <label class="block text-sm font-semibold mt-4 mb-2" for="valid_from">Gültig ab</label>
                <input class="input input-bordered w-full" type="date" id="valid_from" name="valid_from" value="{{ $workModels->defaultValidFrom }}" required>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach (\App\View\ExistingUi\WorkModelsPage::DAYS as $i => $day)
                    <div>
                        <label for="model-day{{ $i + 1 }}" class="block text-sm font-semibold mt-4 mb-2">{{ $day }} (Minuten)</label>
                        <input id="model-day{{ $i + 1 }}" class="input input-bordered w-full" name="day{{ $i + 1 }}" type="number" min="0" max="1440" value="0" required>
                    </div>
                    @endforeach
                </div>
                <label class="block text-sm font-semibold mt-4 mb-2" for="reason">Begründung</label>
                <textarea class="textarea textarea-bordered w-full" id="reason" name="reason" minlength="5" maxlength="1000" required></textarea>
                <div class="flex flex-wrap gap-3 mt-5"><button class="btn btn-primary">Modell speichern</button></div>
            </fieldset>
        </form>
    </section>
    @endif

    <section class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Hinterlegte Modelle</h2>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Person</th><th>Gültig ab</th>@foreach (\App\View\ExistingUi\WorkModelsPage::DAYS as $day)<th>{{ substr($day, 0, 2) }}</th>@endforeach<th>Begründung</th></tr></thead>
                <tbody>
                    @foreach ($workModels->models as $m)
                    <tr><td>{{ $m['username'] }}</td><td>{{ $m['valid_from'] }}</td>@foreach ($m['minutes'] as $v)<td>{{ $v }} min</td>@endforeach<td>{{ $m['reason'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
