@extends('chrono.layouts.app')

@section('content')
<main class="container mx-auto px-4 py-8 chrono-page">
    <div class="chrono-page-intro"><h1 class="text-2xl font-bold"><x-chrono-icon name="briefcase" size="24" /> Arbeitszeitmodelle</h1><img src="assets/illustrations/calendar-plan.png" class="chrono-page-illustration" alt="" width="88" height="76"></div>
    <x-chrono-help><p>Das jüngste Modell mit passendem Gültigkeitsdatum bestimmt die Sollzeit. Feiertage aus dem hinterlegten Feiertagskalender haben null Sollminuten. Bereits erfasste Abwesenheitsgutschriften bleiben unverändert und können über einen Korrekturantrag angepasst werden.</p>
<p class="text-sm opacity-70 mb-4">Nicht auswertbare gespeicherte Wochenminuten sind keine null Minuten. Der Anzeigehinweis ändert oder berechnet kein Modell neu.</p></x-chrono-help>
    @if ($workModels->error)<div class="alert alert-error mb-6" role="alert">{{ $workModels->error }}</div>@endif
    @if ($workModels->saved)<div class="alert alert-success mb-6">Arbeitszeitmodell gespeichert.</div>@endif


    @if ($workModels->creditPreview !== null)
    @php
$creditPreview = $workModels->creditPreview;
@endphp
    <section id="model-credit-preview" class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Vorschau (nicht gespeichert)</h2>
        <p>{{ $creditPreview['username'] }}, Person #{{ $creditPreview['person_id'] }}, ab {{ $creditPreview['from'] }} (Europe/Berlin){{ $creditPreview['until'] !== null ? ' bis vor '.$creditPreview['until'].' (nächstes vorhandenes Modell)' : '' }}</p>
        <x-chrono-help><p class="text-sm opacity-70 mt-3">Das Tagessoll des eingegebenen Modells im Vergleich zur gespeicherten Netto-Gutschrift. Bereits erfasste Gutschriften werden weder neu berechnet noch geändert. Die Abweichung ist kein Kontosaldo und kein automatischer Korrekturauftrag. Nur bereits gespeicherte Feiertage werden berücksichtigt; deren Vollständigkeit ist nicht bestätigt.</p></x-chrono-help>
        @if ($creditPreview['truncated'] || $creditPreview['unknown'])<p class="mt-3" role="status">Die Vorschau ist begrenzt oder enthält nicht auswertbare Gutschriften. Unbekannte Werte sind nicht null Minuten; es gibt keine abschließende Summe.</p>@endif
        @if (! $creditPreview['items'])<p class="mt-3" role="status">Keine zuordenbaren gespeicherten Abwesenheiten im betrachteten Modellabschnitt gefunden. Dies ist kein Vollständigkeits- oder Datenqualitätsnachweis.</p>@else
        <x-chrono-help><p class="text-sm mt-3">Bis zu 100 gespeicherte Gutschriften je Seite in der bisherigen Beginn-/ID-Reihenfolge. Unlesbare Anfänge stehen zuerst; Ortszeit-/Formathinweise bleiben unabhängig davon ungeklärt.</p></x-chrono-help>
        <div class="table-responsive mt-3"><table class="table"><thead><tr><th>Buchung</th><th>Tag (Berlin)</th><th>Gutschrift</th><th>Soll im neuen Modell</th><th>Differenz</th></tr></thead><tbody>
        @foreach ($creditPreview['items'] as $credit)
            <tr><td><a class="link link-primary" target="_blank" rel="noopener noreferrer" href="{{ 'history.php?'.http_build_query(['entity'=>'zeiterfassung','user_id'=>$creditPreview['person_id'],'record_id'=>$credit['id']]) }}">#{{ $credit['id'] }}</a>@if ($credit['timestamp_review'] ?? false)<span class="block text-sm">Zeitformat/Ortszeit ungeklärt; keine erfundene Gutschrift.</span>@endif</td><td>{{ $credit['day'] ?? 'Nicht zuordenbar' }}</td><td>{{ $credit['stored_minutes'] === null ? 'Nicht auswertbar' : $credit['stored_minutes'].' min' }}</td><td>{{ $credit['hypothetical_target'] === null ? 'Unbekannt' : $credit['hypothetical_target'].' min' }}</td><td>{{ $credit['difference'] === null ? 'Unbekannt' : ($credit['difference'] > 0 ? '+' : '').$credit['difference'].' min' }}</td></tr>
        @endforeach
        </tbody></table></div>
        @endif
        @if (($creditPreview['pagination_version'] ?? null) === 1)
        <p class="text-sm mt-3">{{ $creditPreview['total'] }} passende gespeicherte Buchungen, Vorschauseite {{ $creditPreview['page'] }} von {{ $creditPreview['pages'] }}, höchstens {{ $creditPreview['page_size'] }} je Seite.</p>
        <x-chrono-help><p class="text-sm opacity-70 mt-2">Jeder Seitenaufruf prüft die aktuell ausgefüllte Modellmaske erneut, ohne Speichern. Neue Daten oder geänderte Eingaben können Seiten verschieben. Für eine geänderte Maske können Sie die Vorschau oben bewusst auf Seite 1 neu starten. Kein eingefrorener Abschlussstand.</p></x-chrono-help>
        @if ($creditPreview['pages'] > 1)
        <nav class="flex flex-wrap gap-3 mt-3" aria-label="Gutschriften-Vorschauseiten">
            @if ($creditPreview['page'] > 1)<button type="submit" form="work-model-form" class="btn btn-outline-secondary" name="preview_page" value="1" formnovalidate @disabled(! $workModels->enabled)>Erste Seite</button><button type="submit" form="work-model-form" class="btn btn-outline-secondary" name="preview_page" value="{{ $creditPreview['page'] - 1 }}" formnovalidate @disabled(! $workModels->enabled)>Vorherige Seite</button>@endif
            @if ($creditPreview['page'] < $creditPreview['pages'])<button type="submit" form="work-model-form" class="btn btn-outline-secondary" name="preview_page" value="{{ $creditPreview['page'] + 1 }}" formnovalidate @disabled(! $workModels->enabled)>Nächste Seite</button><button type="submit" form="work-model-form" class="btn btn-outline-secondary" name="preview_page" value="{{ $creditPreview['pages'] }}" formnovalidate @disabled(! $workModels->enabled)>Letzte Seite</button>@endif
        </nav>
        @endif
        @endif
        <p class="mt-4"><a class="link" href="#user_id">Zurück zum Modellformular (ohne Neuladen)</a></p>
    </section>
    @endif

    @if ($workModels->selectionError)
    <div class="alert alert-error mb-6" role="alert">{{ $workModels->selectionError }} <a href="{{ $workModels->url($workModels->page, 0) }}">Auswahl zurücksetzen</a></div>
    @else
    <section class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">{{ $workModels->selectedModel ? 'Ausgewähltes und aktuelles Modell' : 'Mein aktuelles Modell' }}</h2>
        <p class="text-sm opacity-70 mb-4">Stand {{ substr(\App\View\ExistingUi\HumanTime::full($workModels->defaultValidFrom.'T00:00:00'), 0, 10) }}</p>
        @if (! $workModels->effectiveModel)<p class="mb-4" role="status">Für {{ $workModels->selectedModel ? $workModels->selectedModel['username'] : 'Ihr Konto' }} ist heute noch kein Modell gültig.</p>@endif
        @if ($workModels->effectiveModel && ! $workModels->selectedModel)
        @php
            $weekMinutes = $workModels->effectiveModel['minutes'];
            $weekKnown = count($weekMinutes) === 7 && !in_array(null, $weekMinutes, true);
            $weekTotal = $weekKnown ? array_sum($weekMinutes) : null;
        @endphp
        <div class="chrono-week-plan">
            <div class="chrono-week-total"><span class="text-secondary">Pro Woche</span><strong>{{ $weekTotal === null ? 'Unbekannt' : sprintf('%02d:%02d', intdiv($weekTotal,60), $weekTotal % 60) }}</strong><span class="text-secondary">Stunden</span></div>
            <div class="chrono-week-days">
            @foreach (\App\View\ExistingUi\WorkModelsPage::DAYS as $i => $day)
            @php
$minutes = $weekMinutes[$i] ?? null;
@endphp
            <div class="chrono-week-day {{ $minutes === 0 ? 'chrono-day-off' : '' }}"><span title="{{ $day }}">{{ substr($day,0,2) }}</span><meter min="0" max="1440" value="{{ $minutes ?? 0 }}" aria-label="{{ $day }}: {{ $minutes === null ? 'Nicht auswertbar' : $minutes.' Minuten' }}"></meter><strong>{{ $minutes === null ? 'Unbekannt' : ($minutes === 0 ? 'Frei' : sprintf('%02d:%02d', intdiv($minutes,60), $minutes % 60)) }}</strong></div>
            @endforeach
            </div>
        </div>
        <details class="mt-4"><summary>Modelldetails</summary><dl class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-3">
            <div><dt class="text-sm opacity-70">Version</dt><dd>#{{ $workModels->effectiveModel['id'] }}</dd></div>
            <div><dt class="text-sm opacity-70">Person</dt><dd>{{ $workModels->effectiveModel['username'] }}</dd></div>
            <div><dt class="text-sm opacity-70">Gültig ab</dt><dd>{{ $workModels->effectiveModel['valid_from'] === '0001-01-01' ? 'Startmodell' : substr(\App\View\ExistingUi\HumanTime::full($workModels->effectiveModel['valid_from'].'T00:00:00'),0,10) }}</dd></div>
            <div><dt class="text-sm opacity-70">Begründung</dt><dd>{{ $workModels->effectiveModel['reason'] }}</dd></div>
        </dl></details>
        @elseif ($workModels->selectedModel)
        <div class="table-responsive"><table class="table">
            <thead><tr><th>Ansicht</th><th>Version</th><th>Person</th><th>Gültig ab</th>@foreach (\App\View\ExistingUi\WorkModelsPage::DAYS as $day)<th>{{ substr($day, 0, 2) }}</th>@endforeach<th>Begründung</th></tr></thead>
            <tbody>
            @foreach (['Ausgewählt' => $workModels->selectedModel, 'Aktuell' => $workModels->effectiveModel] as $label => $m)
                @if ($m)<tr><td>{{ $label }}</td><td>#{{ $m['id'] }}</td><td>{{ $m['username'] }}</td><td>{{ $m['valid_from'] === '0001-01-01' ? 'Startmodell' : substr(\App\View\ExistingUi\HumanTime::full($m['valid_from'].'T00:00:00'),0,10) }}</td>@foreach ($m['minutes'] as $v)<td>{{ $v === null ? 'Nicht auswertbar' : sprintf('%02d:%02d', intdiv($v,60), $v % 60) }}</td>@endforeach<td>{{ $m['reason'] }}</td></tr>@endif
            @endforeach
            </tbody>
        </table></div>
        @endif
        @if ($workModels->selectedModel)<p class="mt-4"><a href="{{ $workModels->url($workModels->page, 0) }}">Zur eigenen heutigen Ansicht</a></p>@endif
    </section>
    @endif

    @if ($page->role === 'admin')
    <details id="new-work-model" class="chrono-disclosure mb-6" @if ($workModels->error || $workModels->creditPreview !== null) open @endif>
        <summary><x-chrono-icon name="plus" size="20" /> Arbeitszeitmodell anlegen</summary>
        <div class="mt-4">
        <x-chrono-help><p class="opacity-70">Ein neues Modell ersetzt keine Historie. Bei gleichem Gültigkeitsdatum gilt die zuletzt gespeicherte Version. Rückwirkende Modelle ändern Sollzeit-Auswertungen, aber keine gebuchten Gutschriften.</p></x-chrono-help>
        <form method="post" id="work-model-form" class="chrono-form-panel">
            @csrf
            <fieldset @disabled(! $workModels->enabled)>
                <label class="block text-sm font-semibold mt-4 mb-2" for="user_id">Person</label>
                <select class="select select-bordered w-full form-select" id="user_id" name="user_id" @required($workModels->previewEnabled)>
                    @if ($workModels->previewEnabled)<option value="">Person auswählen</option>@endif
                    @foreach ($workModels->targetOptions as $u)
                    <option value="{{ (int) $u['id'] }}" @selected(($workModels->previewForm['user_id'] ?? '') === (string) $u['id'])>{{ $u['username'] }}</option>
                    @endforeach
                </select>
                <label class="block text-sm font-semibold mt-4 mb-2" for="valid_from">Gültig ab</label>
                <input class="input input-bordered w-full form-control" type="date" id="valid_from" name="valid_from" value="{{ $workModels->previewForm['valid_from'] ?? $workModels->defaultValidFrom }}" required>
                <div class="chrono-model-input-week">
                    @foreach (\App\View\ExistingUi\WorkModelsPage::DAYS as $i => $day)
                    <div>
                        <label for="model-day{{ $i + 1 }}" class="block text-sm font-semibold mt-4 mb-2"><span title="{{ $day }}">{{ substr($day,0,2) }}</span><span class="chrono-entry-caption">Minuten</span></label>
                        <input id="model-day{{ $i + 1 }}" class="input input-bordered w-full form-control" name="day{{ $i + 1 }}" type="number" min="0" max="1440" value="{{ $workModels->previewForm['day'.($i + 1)] ?? '0' }}" required>
                    </div>
                    @endforeach
                </div>
                <label class="block text-sm font-semibold mt-4 mb-2" for="reason">Begründung</label>
                <textarea class="textarea textarea-bordered w-full form-control" id="reason" name="reason" minlength="5" maxlength="1000" required>{{ $workModels->previewForm['reason'] ?? '' }}</textarea>
                <div class="flex flex-wrap gap-3 mt-5">@if ($workModels->previewEnabled)<button type="submit" class="btn btn-outline-secondary" name="model_action" value="preview" formnovalidate>Vorschau</button>@endif<button type="submit" class="btn btn-primary" name="model_action" value="save">Modell speichern</button></div>
            </fieldset>
        </form>
        </div>
    </details>
    @endif


    <section class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Modellverlauf</h2>
        <x-chrono-help><p class="text-sm opacity-70 mb-4">{{ $workModels->total }} Modellversionen, Seite {{ $workModels->page }} von {{ $workModels->pages }}, {{ $workModels->pageSize }} je Seite innerhalb Ihrer bisherigen Sichtrechte. Neue Versionen können die Seiten verschieben.</p></x-chrono-help>
        @if ($workModels->models === [])<p role="status">Keine sichtbaren Modellversionen.</p>@else
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Version</th><th>Person</th><th>Gültig ab</th>@foreach (\App\View\ExistingUi\WorkModelsPage::DAYS as $day)<th>{{ substr($day, 0, 2) }}</th>@endforeach<th>Begründung</th></tr></thead>
                <tbody>
                    @foreach ($workModels->models as $m)
                    <tr><td><a href="{{ $workModels->url($workModels->page, $m['id']) }}">#{{ $m['id'] }} ansehen</a>@if ($m['id'] === $workModels->selectedModelId)<small class="block">Ausgewählt</small>@endif<small class="block opacity-70">{{ $m['id'] === $m['effective_id'] ? 'Aktuell' : ($m['valid_from'] > $workModels->defaultValidFrom ? 'Geplant' : 'Frühere Version') }}</small></td><td>{{ $m['username'] }}</td><td>{{ $m['valid_from'] === '0001-01-01' ? 'Startmodell' : substr(\App\View\ExistingUi\HumanTime::full($m['valid_from'].'T00:00:00'),0,10) }}</td>@foreach ($m['minutes'] as $v)<td>{{ $v === null ? 'Nicht auswertbar' : sprintf('%02d:%02d', intdiv($v,60), $v % 60) }}</td>@endforeach<td>{{ $m['reason'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
        @if ($workModels->pages > 1)
        <nav class="flex flex-wrap gap-3 mt-5" aria-label="Modellversionsseiten">
            @if ($workModels->page > 1)<a class="btn btn-outline-secondary" href="{{ $workModels->url($workModels->page - 1) }}">Neuere Modellversionen</a>@endif
            @if ($workModels->page < $workModels->pages)<a class="btn btn-outline-secondary" href="{{ $workModels->url($workModels->page + 1) }}">Ältere Modellversionen</a>@endif
        </nav>
        @endif
    </section>
</main>
@endsection
