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
$creditHours = static fn($minutes) => ($minutes < 0 ? '-' : '').sprintf('%02d:%02d', intdiv(abs($minutes),60), abs($minutes)%60).' h';
@endphp
    <section id="model-credit-preview" class="card chrono-model-preview mb-6" aria-labelledby="model-preview-title">
        <header class="chrono-model-preview-header">
            <div><span class="badge bg-secondary-lt">Vorschau</span><h2 id="model-preview-title">Abwesenheiten im neuen Modell</h2><p>{{ $creditPreview['username'] }}<span>Ab {{ substr(\App\View\ExistingUi\HumanTime::full($creditPreview['from'].'T00:00:00'),0,10) }}</span></p></div>
            <x-chrono-help label="Was zeigt dieser Vergleich?"><p>Verglichen werden bereits gutgeschriebene Abwesenheitsstunden mit dem Tagessoll des eingegebenen Modells. Die Abweichung ist das neue Tagessoll minus die gespeicherte Gutschrift. Bestehende Buchungen werden durch das Modell nicht automatisch geändert.</p><p>Es zählen nur gespeicherte Feiertage. Bei einem späteren Modell endet der Vergleich vor dessen Beginn.</p>@if($creditPreview['until'] !== null)<p>Nächstes Modell ab {{ substr(\App\View\ExistingUi\HumanTime::full($creditPreview['until'].'T00:00:00'),0,10) }}.</p>@endif<p>Die Vorschau wird mit jedem Aufruf neu geprüft. Es werden höchstens 100 Einträge je Seite angezeigt.</p></x-chrono-help>
        </header>
        <p class="chrono-model-preview-note"><x-chrono-icon name="info-circle" size="18" /> Prüfe Abweichungen vor dem Speichern des Modells.</p>
        @if ($creditPreview['truncated'] || $creditPreview['unknown'])<p class="alert alert-warning mx-3" role="status">Einige Einträge lassen sich nicht sicher vergleichen. Die Vorschau ist nicht vollständig.</p>@endif
        @if (!$creditPreview['items'])
            <p class="chrono-model-preview-empty" role="status">Für diesen Zeitraum wurden keine passenden Abwesenheiten gefunden.</p>
        @else
        <div class="table-responsive"><table class="table chrono-model-preview-table mb-0">
            <thead><tr><th>Tag</th><th>Gutgeschrieben</th><th>Neues Tagessoll</th><th>Abweichung</th></tr></thead>
            <tbody>@foreach ($creditPreview['items'] as $credit)
                <tr><th scope="row"><a href="{{ 'history.php?'.http_build_query(['entity'=>'zeiterfassung','user_id'=>$creditPreview['person_id'],'record_id'=>$credit['id']]) }}" aria-label="{{ isset($credit['day']) ? 'Buchung vom '.substr(\App\View\ExistingUi\HumanTime::full($credit['day'].'T00:00:00'),0,10).' ansehen' : 'Buchungsdetails ansehen' }}">{{ isset($credit['day']) ? substr(\App\View\ExistingUi\HumanTime::full($credit['day'].'T00:00:00'),0,10) : 'Datum unbekannt' }}</a>@if($credit['timestamp_review'] ?? false)<span class="d-block small text-warning">Datum prüfen</span>@endif</th>
                <td data-label="Gutgeschrieben">{{ $credit['stored_minutes'] === null ? 'Nicht auswertbar' : $creditHours($credit['stored_minutes']) }}</td>
                <td data-label="Neues Tagessoll">{{ $credit['hypothetical_target'] === null ? 'Unbekannt' : $creditHours($credit['hypothetical_target']) }}</td>
                <td data-label="Abweichung"><strong>{{ $credit['difference'] === null ? 'Nicht vergleichbar' : ($credit['difference'] === 0 ? 'Unverändert' : ($credit['difference'] > 0 ? '+' : '').$creditHours($credit['difference'])) }}</strong></td></tr>
            @endforeach</tbody>
        </table></div>
        @endif
        <footer class="chrono-model-preview-footer">
            <span class="text-secondary small">{{ count($creditPreview['items']) }} {{ count($creditPreview['items']) === 1 ? 'Eintrag angezeigt' : 'Einträge angezeigt' }}@if(($creditPreview['pagination_version'] ?? null) === 1 && $creditPreview['pages'] > 1) — Seite {{ $creditPreview['page'] }} von {{ $creditPreview['pages'] }}@endif</span>
            <a class="btn btn-outline-secondary" href="#user_id"><x-chrono-icon name="pencil" size="18" /> Modell bearbeiten</a>
        </footer>
        @if (($creditPreview['pagination_version'] ?? null) === 1)
        @if ($creditPreview['pages'] > 1)
        <nav class="flex flex-wrap gap-3 mt-3" aria-label="Gutschriften-Vorschauseiten">
            @if ($creditPreview['page'] > 1)<button type="submit" form="work-model-form" class="btn btn-outline-secondary" name="preview_page" value="1" formnovalidate @disabled(! $workModels->enabled)>Erste Seite</button><button type="submit" form="work-model-form" class="btn btn-outline-secondary" name="preview_page" value="{{ $creditPreview['page'] - 1 }}" formnovalidate @disabled(! $workModels->enabled)>Vorherige Seite</button>@endif
            @if ($creditPreview['page'] < $creditPreview['pages'])<button type="submit" form="work-model-form" class="btn btn-outline-secondary" name="preview_page" value="{{ $creditPreview['page'] + 1 }}" formnovalidate @disabled(! $workModels->enabled)>Nächste Seite</button><button type="submit" form="work-model-form" class="btn btn-outline-secondary" name="preview_page" value="{{ $creditPreview['pages'] }}" formnovalidate @disabled(! $workModels->enabled)>Letzte Seite</button>@endif
        </nav>
        @endif
        @endif
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


    <details class="chrono-disclosure mb-6" @if($workModels->selectedModel || $workModels->page > 1) open @endif>
        <summary><x-chrono-icon name="history" size="20" /> Frühere Arbeitszeitmodelle</summary><div class="mt-4">
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
    </div></details>
</main>
<script>
(() => {
    const form = document.getElementById('work-model-form');
    if (!form) return;
    // Presentation only: the existing writer continues to receive whole minutes.
    form.querySelectorAll('input[id^="model-day"]').forEach(raw => {
        const label = form.querySelector('label[for="' + raw.id + '"]');
        if (!label) return;
        const input = document.createElement('input');
        input.type = 'text'; input.inputMode = 'numeric'; input.className = raw.className;
        input.id = raw.id + '-hours'; input.placeholder = '08:00'; input.required = true;
        input.maxLength = 5;
        const parsed = /^(?:0|[1-9][0-9]{0,3})$/.test(raw.value) ? Number(raw.value) : null;
        input.value = parsed !== null && parsed <= 1440
            ? String(Math.floor(parsed / 60)).padStart(2, '0') + ':' + String(parsed % 60).padStart(2, '0') : '';
        const sync = () => {
            const match = /^([0-9]{1,2}):([0-5][0-9])$/.exec(input.value);
            const minutes = match ? Number(match[1]) * 60 + Number(match[2]) : null;
            const valid = minutes !== null && minutes <= 1440;
            raw.value = valid ? String(minutes) : '';
            input.setCustomValidity(valid ? '' : 'Bitte Stunden und Minuten eingeben, zum Beispiel 08:00 (höchstens 24:00).');
        };
        raw.type = 'hidden'; raw.after(input); label.htmlFor = input.id;
        label.querySelector('.chrono-entry-caption').textContent = 'Std.:Min.';
        input.addEventListener('input', sync); sync();
    });
})();
</script>
@endsection
