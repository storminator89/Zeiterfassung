<details id="holiday-refill" class="card mb-4" @if (($holidayRefillReceipt ?? null) !== null || ($holidayRefillDraft ?? null) !== null) open @endif>
    <summary class="card-body cursor-pointer font-semibold"><x-chrono-icon name="calendar-check" size="20" /> Feiertage ergänzen <span class="badge bg-secondary-lt ms-2">Baden-Württemberg</span></summary>
    <div class="card-body pt-0">
        @if (($holidayRefillReceipt ?? null) !== null)
        <div class="alert alert-success mb-3" role="status"><div><p class="mb-1">{{ $holidayRefillReceipt['year'] }}: {{ $holidayRefillReceipt['inserted'] }} ergänzt, {{ $holidayRefillReceipt['unchanged'] }} bereits vorhanden.</p><a href="dashboard.php?holiday_year={{ $holidayRefillReceipt['year'] }}#holiday-inventory">Feiertage prüfen</a></div></div>
        @if ($holidayRefillReceipt['conditional_omitted'] > 0)<p class="text-warning" role="status">{{ $holidayRefillReceipt['conditional_omitted'] }} Einträge mit Hinweis wurden nicht übernommen.</p>@endif
        @endif
        @if (($holidayRefillDraft ?? null) !== null)<p class="text-secondary mb-3" role="status">Entwurf beibehalten. Bitte Jahr und Daten erneut prüfen und bestätigen.</p>@endif
        <form method="post" class="chrono-form-panel">
            @csrf
            <input type="hidden" name="refill_holidays" value="1">
            <div class="row g-3 align-items-end mb-3">
                <div class="col-sm-4"><label class="form-label" for="holiday-refill-year">Jahr</label><input id="holiday-refill-year" name="holiday_year" type="number" min="1" max="9999" step="1" value="{{ $holidayRefillDraft['year'] ?? '' }}" required class="form-control"></div>
                <div class="col-sm-8"><x-chrono-help label="Quelle und Auswirkungen"><p>Nur für einen ausdrücklich geprüften BW-Standort. Die globale Bestandstabelle trennt keine Regionen. Bestehende Werte werden weder ersetzt noch gelöscht; abweichende Namen oder zusätzliche Bestandsdaten sperren das ganze Jahr zur manuellen Klärung.</p><p>Die vollständige Antwort von <a href="https://feiertage-api.de/" target="_blank" rel="noopener noreferrer">feiertage-api.de</a> mit <code>https://feiertage-api.de/api/?jahr=JAHR&amp;nur_land=BW</code> selbst abrufen, Standort und Inhalt prüfen und hier einfügen. Kein automatischer Abruf. Einträge mit nicht leerem Hinweis werden wie bisher nicht übernommen. Speichern bestätigt weder Quellenherkunft noch Vollständigkeit oder rechtliche Richtigkeit.</p><p>Nur fehlende Daten ergänzen. Neu geladene Sollberechnungen nutzen den ergänzten Kalender auch für vergangene Zeiträume; angezeigte Sollwerte und Salden können sich ändern. Bereits gespeicherte Abwesenheitsgutschriften werden nicht neu berechnet. Das Jahr vorher und nachher in der <a href="dashboard.php?holiday_inventory=1#holiday-inventory" target="_blank" rel="noopener noreferrer">Bestandsanzeige</a> prüfen.</p><p>Die JSON-Antwort darf höchstens 128 KiB groß sein. Ich habe Baden-Württemberg als richtigen Standort, das gewählte Jahr und die Antwort geprüft. Nur fehlende landesweite Daten dieses Jahres ergänzen; Auswirkungen auf angezeigte Sollwerte und Salden sind mir bekannt.</p>@if (($holidayRefillReceipt ?? null) !== null)<p>{{ $holidayRefillReceipt['conditional_omitted'] }} Einträge mit Hinweis nicht übernommen. Kein Vollständigkeitsattest; nicht automatisch erneut senden.</p>@endif</x-chrono-help></div>
            </div>
            <div class="mb-3"><label class="form-label" for="holiday-refill-json">Geprüfte JSON-Antwort</label><textarea id="holiday-refill-json" name="holiday_json" required maxlength="131072" rows="5" spellcheck="false" class="form-control">{{ $holidayRefillDraft['json'] ?? '' }}</textarea></div>
            <label class="form-check d-flex align-items-start gap-2 mb-3"><input type="checkbox" name="confirm_bw_source" value="BW" required class="form-check-input flex-shrink-0"><span class="form-check-label">Standort Baden-Württemberg, Jahr und Quelldaten geprüft. Nur fehlende landesweite Feiertage ergänzen; Auswirkungen auf Sollzeiten und Salden sind mir bekannt.</span></label>
            <div class="d-flex flex-wrap gap-2">
                @if ($holidayRefillPreviewEnabled ?? false)<button type="button" id="holiday-refill-preview-button" class="btn btn-outline-secondary" disabled>Vorab prüfen</button>@endif
                <button type="submit" class="btn btn-primary">Feiertage ergänzen</button>
            </div>
        </form>
        @if ($holidayRefillPreviewEnabled ?? false)
        <p id="holiday-refill-preview-status" class="text-secondary small mt-3" role="status">Vorabprüfung mit JavaScript verfügbar. Sie speichert keine Feiertage.</p>
        <div id="holiday-refill-preview-result" class="mt-3"></div>
        @include('chrono.partials.holiday-refill-preview-script')
        @endif
    </div>
</details>
