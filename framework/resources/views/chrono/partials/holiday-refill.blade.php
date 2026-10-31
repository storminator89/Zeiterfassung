@php
    $holidayAutomaticPreview = $holidayAutomaticPreview ?? null;
    $holidayPreview = $holidayAutomaticPreview['preview'] ?? null;
    $holidayRefillYear = $holidayAutomaticPreview['year'] ?? $holidayRefillDraft['year'] ?? (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')))->format('Y');
    $holidayPreviewCounts = $holidayPreview['counts'] ?? [];
    $holidayImportReady = $holidayPreview !== null && !$holidayPreview['has_conflicts'] && ($holidayPreviewCounts['missing'] ?? 0) > 0;
@endphp
<details id="holiday-refill" class="card mb-4" @if ($holidayAutomaticPreview !== null || ($holidayRefillReceipt ?? null) !== null || ($holidayRefillDraft ?? null) !== null) open @endif>
    <summary class="card-body cursor-pointer font-semibold"><x-chrono-icon name="calendar-check" size="20" /> Feiertage <span class="badge bg-secondary-lt ms-2">Baden-Württemberg</span></summary>
    <div class="card-body pt-0">
        @if (($holidayRefillReceipt ?? null) !== null)
        <div class="alert alert-success mb-3" role="status"><div><p class="mb-1">{{ $holidayRefillReceipt['year'] }}: {{ $holidayRefillReceipt['inserted'] }} ergänzt, {{ $holidayRefillReceipt['unchanged'] }} bereits vorhanden.</p><a href="dashboard.php?holiday_year={{ $holidayRefillReceipt['year'] }}#holiday-inventory">Feiertage ansehen</a></div></div>
        @if ($holidayRefillReceipt['conditional_omitted'] > 0)<p class="text-secondary mb-3" role="status">{{ $holidayRefillReceipt['conditional_omitted'] }} Einträge mit Zusatzhinweis wurden ausgelassen.</p>@endif
        @endif
        <form method="post" action="admin.php?section=system#holiday-refill" class="chrono-form-panel">
            @csrf
            <input type="hidden" name="load_holidays" value="1">
            <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
                <div><label class="form-label" for="holiday-refill-year">Jahr</label><input id="holiday-refill-year" name="holiday_year" type="number" min="1" max="9999" step="1" value="{{ $holidayRefillYear }}" required class="form-control"></div>
                <button type="submit" class="btn btn-outline-secondary">Feiertage laden</button>
                <x-chrono-help label="Quelle und Auswirkungen"><p>Die Feiertage werden von <a href="https://feiertage-api.de/" target="_blank" rel="noopener noreferrer">feiertage-api.de</a> für Baden-Württemberg geladen und vor dem Übernehmen mit dem Bestand abgeglichen.</p><p>Bestehende Werte werden weder ersetzt noch gelöscht. Ergänzte Feiertage wirken sich auf neu geladene Sollberechnungen und Salden aus, auch für vergangene Zeiträume. Bereits gespeicherte Abwesenheitsgutschriften werden nicht neu berechnet. Einträge mit Zusatzhinweis werden ausgelassen.</p><p>Die Quelle und der Abgleich bestätigen keine rechtliche Richtigkeit oder Vollständigkeit. Der Standort muss zu Baden-Württemberg gehören.</p></x-chrono-help>
            </div>
        </form>
        @if ($holidayPreview !== null)
        <section aria-labelledby="holiday-preview-title">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h3 id="holiday-preview-title" class="h4 mb-0">Feiertage {{ $holidayAutomaticPreview['year'] }}</h3>
                <span class="text-secondary small">{{ $holidayPreviewCounts['missing'] }} neu, {{ $holidayPreviewCounts['unchanged'] }} vorhanden</span>
            </div>
            @if ($holidayPreview['has_conflicts'])
            <div class="alert alert-warning mb-3" role="alert"><div>Die Feiertage können noch nicht übernommen werden.
                @if (($holidayPreviewCounts['not_compared'] ?? 0) > 0)<p class="mb-0">Der Abgleich ist unvollständig. Bitte den Bestand prüfen.</p>
                @else<p class="mb-0">{{ $holidayPreviewCounts['conflict'] }} abweichende und {{ $holidayPreviewCounts['extra_existing'] }} zusätzliche Einträge im Bestand.</p>@endif
                <a href="dashboard.php?holiday_year={{ $holidayAutomaticPreview['year'] }}#holiday-inventory">Bestand ansehen</a>
            </div></div>
            @endif
            <div class="table-responsive mb-3"><table class="table table-vcenter mb-0">
                <thead><tr><th>Datum</th><th>Feiertag</th><th>Stand</th></tr></thead>
                <tbody>
                @foreach ($holidayPreview['rows'] as $holiday)
                    <tr><td>{{ (new \DateTimeImmutable($holiday['day']))->format('d.m.Y') }}</td><td>{{ $holiday['name'] }}</td><td><span class="badge {{ $holiday['status'] === 'missing' ? 'bg-primary-lt' : (in_array($holiday['status'], ['conflict','not_compared'], true) ? 'bg-warning-lt' : 'bg-secondary-lt') }}">{{ ['missing'=>'Neu','unchanged'=>'Vorhanden','conflict'=>'Abweichend','not_compared'=>'Nicht geprüft'][$holiday['status']] ?? 'Prüfen' }}</span></td></tr>
                @endforeach
                </tbody>
            </table></div>
            @if ($holidayPreview['conditional_omitted'] > 0)<p class="text-secondary small mb-3">{{ $holidayPreview['conditional_omitted'] }} Einträge mit Zusatzhinweis ausgelassen.</p>@endif
            @if ($holidayImportReady)
            <form method="post" action="admin.php?section=system#holiday-refill" class="chrono-form-panel">
                @csrf
                <input type="hidden" name="import_holidays" value="1">
                <input type="hidden" name="holiday_import_token" value="{{ $holidayAutomaticPreview['token'] }}">
                <label class="form-check d-flex align-items-start gap-2 mb-3"><input type="checkbox" name="confirm_bw_source" value="BW" required class="form-check-input flex-shrink-0"><span class="form-check-label">Für Baden-Württemberg übernehmen</span></label>
                <button type="submit" class="btn btn-primary">Feiertage übernehmen</button>
            </form>
            @elseif (!$holidayPreview['has_conflicts'])
            <p class="text-secondary mb-0" role="status">Diese Feiertage sind bereits gespeichert.</p>
            @endif
        </section>
        @endif
    </div>
</details>
