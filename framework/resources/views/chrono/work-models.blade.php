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
                <select class="select select-bordered w-full" id="user_id" name="user_id" @required($workModels->previewEnabled)>
                    @if ($workModels->previewEnabled)<option value="">Person auswählen</option>@endif
                    @foreach ($workModels->targetOptions as $u)
                    <option value="{{ (int) $u['id'] }}" @selected(($workModels->previewForm['user_id'] ?? '') === (string) $u['id'])>{{ $u['username'] }}</option>
                    @endforeach
                </select>
                <label class="block text-sm font-semibold mt-4 mb-2" for="valid_from">Gültig ab</label>
                <input class="input input-bordered w-full" type="date" id="valid_from" name="valid_from" value="{{ $workModels->previewForm['valid_from'] ?? $workModels->defaultValidFrom }}" required>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach (\App\View\ExistingUi\WorkModelsPage::DAYS as $i => $day)
                    <div>
                        <label for="model-day{{ $i + 1 }}" class="block text-sm font-semibold mt-4 mb-2">{{ $day }} (Minuten)</label>
                        <input id="model-day{{ $i + 1 }}" class="input input-bordered w-full" name="day{{ $i + 1 }}" type="number" min="0" max="1440" value="{{ $workModels->previewForm['day'.($i + 1)] ?? '0' }}" required>
                    </div>
                    @endforeach
                </div>
                <label class="block text-sm font-semibold mt-4 mb-2" for="reason">Begründung</label>
                <textarea class="textarea textarea-bordered w-full" id="reason" name="reason" minlength="5" maxlength="1000" required>{{ $workModels->previewForm['reason'] ?? '' }}</textarea>
                <div class="flex flex-wrap gap-3 mt-5">@if ($workModels->previewEnabled)<button class="btn btn-outline" name="model_action" value="preview" formnovalidate>Gutschriften vorab prüfen (ohne Speichern)</button>@endif<button class="btn btn-primary" name="model_action" value="save">Modell speichern</button></div>
            </fieldset>
        </form>
    </section>
    @endif

    @if ($workModels->creditPreview !== null)
    @php($creditPreview = $workModels->creditPreview)
    <section id="model-credit-preview" class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Vorschau – kein Modell gespeichert</h2>
        <p>{{ $creditPreview['username'] }} · Person #{{ $creditPreview['person_id'] }} · ab {{ $creditPreview['from'] }} (Europe/Berlin){{ $creditPreview['until'] !== null ? ' bis vor '.$creditPreview['until'].' (nächstes vorhandenes Modell)' : '' }}</p>
        <p class="text-sm opacity-70 mt-3">Hypothetisches Tagessoll des eingegebenen Modells im Vergleich zur gespeicherten Netto-Gutschrift. Bereits erfasste Gutschriften werden weder neu berechnet noch geändert. Die Abweichung ist kein Kontosaldo und kein automatischer Korrekturauftrag. Nur bereits gespeicherte Feiertage werden berücksichtigt; deren Vollständigkeit ist nicht bestätigt.</p>
        @if ($creditPreview['truncated'] || $creditPreview['unknown'])<p class="mt-3" role="status">Die Vorschau ist begrenzt oder enthält nicht auswertbare Gutschriften. Unbekannte Werte sind nicht null Minuten; es gibt keine abschließende Summe.</p>@endif
        @if (! $creditPreview['items'])<p class="mt-3" role="status">Keine zuordenbaren gespeicherten Abwesenheiten im betrachteten Modellabschnitt gefunden. Dies ist kein Vollständigkeits- oder Datenqualitätsnachweis.</p>@else
        <p class="text-sm mt-3">Bis zu 100 gespeicherte Gutschriften, nach Berliner Beginn sortiert. Nicht zuordenbare Datumswerte stehen zuerst.</p>
        <div class="table-responsive mt-3"><table class="table"><thead><tr><th>Buchung / Historie (neuer Tab)</th><th>Tag (Berlin)</th><th>Gespeicherte Netto-Gutschrift</th><th>Hypothetisches Tagessoll</th><th>Tagessoll minus Gutschrift</th></tr></thead><tbody>
        @foreach ($creditPreview['items'] as $credit)
            <tr><td><a class="link link-primary" target="_blank" rel="noopener noreferrer" href="{{ 'history.php?'.http_build_query(['entity'=>'zeiterfassung','user_id'=>$creditPreview['person_id'],'record_id'=>$credit['id']]) }}">#{{ $credit['id'] }}</a></td><td>{{ $credit['day'] ?? 'Nicht zuordenbar' }}</td><td>{{ $credit['stored_minutes'] === null ? 'Nicht auswertbar' : $credit['stored_minutes'].' min' }}</td><td>{{ $credit['hypothetical_target'] === null ? 'Unbekannt' : $credit['hypothetical_target'].' min' }}</td><td>{{ $credit['difference'] === null ? 'Unbekannt' : ($credit['difference'] > 0 ? '+' : '').$credit['difference'].' min' }}</td></tr>
        @endforeach
        </tbody></table></div>
        @endif
        <p class="mt-4"><a class="link" href="#user_id">Zurück zum Modellformular (ohne Neuladen)</a></p>
    </section>
    @endif

    @if ($workModels->selectionError)
    <div class="alert alert-error mb-6" role="alert">{{ $workModels->selectionError }} <a href="{{ $workModels->url($workModels->page, 0) }}">Auswahl zurücksetzen</a></div>
    @else
    <section class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">{{ $workModels->selectedModel ? 'Ausgewählte Version und heute maßgebliches Modell' : 'Heute maßgebliches eigenes Modell' }}</h2>
        <p class="text-sm opacity-70 mb-4">Maßgeblich am {{ $workModels->defaultValidFrom }} (Europe/Berlin), unabhängig von der Listenseite. Die Auswahl dient nur der Ansicht und ändert kein Modell.</p>
        @if (! $workModels->effectiveModel)<p class="mb-4" role="status">Für {{ $workModels->selectedModel ? $workModels->selectedModel['username'] : 'Ihr Konto' }} ist heute noch kein Modell gültig.</p>@endif
        @if ($workModels->selectedModel || $workModels->effectiveModel)
        <div class="table-responsive"><table class="table">
            <thead><tr><th>Ansicht</th><th>Version</th><th>Person</th><th>Gültig ab</th>@foreach (\App\View\ExistingUi\WorkModelsPage::DAYS as $day)<th>{{ substr($day, 0, 2) }}</th>@endforeach<th>Begründung</th></tr></thead>
            <tbody>
            @foreach (['Ausgewählt' => $workModels->selectedModel, 'Heute maßgeblich' => $workModels->effectiveModel] as $label => $m)
                @if ($m)<tr><td>{{ $label }}</td><td>#{{ $m['id'] }}</td><td>{{ $m['username'] }}</td><td>{{ $m['valid_from'] }}</td>@foreach ($m['minutes'] as $v)<td>{{ $v }} min</td>@endforeach<td>{{ $m['reason'] }}</td></tr>@endif
            @endforeach
            </tbody>
        </table></div>
        @endif
        @if ($workModels->selectedModel)<p class="mt-4"><a href="{{ $workModels->url($workModels->page, 0) }}">Zur eigenen heutigen Ansicht</a></p>@endif
    </section>
    @endif

    <section class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Hinterlegte Modelle</h2>
        <p class="text-sm opacity-70 mb-4">{{ $workModels->total }} Modellversionen · Seite {{ $workModels->page }} von {{ $workModels->pages }} · {{ $workModels->pageSize }} je Seite innerhalb Ihrer bisherigen Sichtrechte. Neue Versionen können die Seiten verschieben.</p>
        @if ($workModels->models === [])<p role="status">Keine sichtbaren Modellversionen.</p>@else
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Version / Ansicht</th><th>Person</th><th>Gültig ab</th>@foreach (\App\View\ExistingUi\WorkModelsPage::DAYS as $day)<th>{{ substr($day, 0, 2) }}</th>@endforeach<th>Begründung</th></tr></thead>
                <tbody>
                    @foreach ($workModels->models as $m)
                    <tr><td><a href="{{ $workModels->url($workModels->page, $m['id']) }}">#{{ $m['id'] }} ansehen</a>@if ($m['id'] === $workModels->selectedModelId)<small class="block">Ausgewählt</small>@endif<small class="block opacity-70">{{ $m['id'] === $m['effective_id'] ? 'Heute maßgeblich' : ($m['valid_from'] > $workModels->defaultValidFrom ? 'Künftiges Gültigkeitsdatum' : 'Frühere Version') }}</small></td><td>{{ $m['username'] }}</td><td>{{ $m['valid_from'] }}</td>@foreach ($m['minutes'] as $v)<td>{{ $v }} min</td>@endforeach<td>{{ $m['reason'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
        @if ($workModels->pages > 1)
        <nav class="flex flex-wrap gap-3 mt-5" aria-label="Modellversionsseiten">
            @if ($workModels->page > 1)<a class="btn btn-outline" href="{{ $workModels->url($workModels->page - 1) }}">Neuere Modellversionen</a>@endif
            @if ($workModels->page < $workModels->pages)<a class="btn btn-outline" href="{{ $workModels->url($workModels->page + 1) }}">Ältere Modellversionen</a>@endif
        </nav>
        @endif
    </section>
</main>
@endsection
