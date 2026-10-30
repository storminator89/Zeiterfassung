<details id="holiday-refill" class="card bg-base-100 shadow-xl mb-8" @if (($holidayRefillReceipt ?? null) !== null || ($holidayRefillDraft ?? null) !== null) open @endif>
    <summary class="card-body cursor-pointer font-semibold">Feiertagsbestand ergänzen · Baden-Württemberg</summary>
    <div class="card-body pt-0">
        @if (($holidayRefillReceipt ?? null) !== null)
        <p class="alert alert-success" role="status">Jahr {{ $holidayRefillReceipt['year'] }} · BW gespeichert: {{ $holidayRefillReceipt['inserted'] }} neue Zeilen, {{ $holidayRefillReceipt['unchanged'] }} vorhandene Zeilen unverändert; {{ $holidayRefillReceipt['conditional_omitted'] }} Einträge mit Hinweis nicht übernommen. <a class="link" href="dashboard.php?holiday_year={{ $holidayRefillReceipt['year'] }}#holiday-inventory">Jahrgang prüfen</a>. Kein Vollständigkeitsattest; nicht automatisch erneut senden.</p>
        @endif
        <p>Nur für einen ausdrücklich geprüften BW-Standort. Die globale Bestandstabelle trennt keine Regionen. Bestehende Werte werden weder ersetzt noch gelöscht; abweichende Namen oder zusätzliche Bestandsdaten sperren das ganze Jahr zur manuellen Klärung.</p>
        <p>Die vollständige Antwort von <a class="link" href="https://feiertage-api.de/" target="_blank" rel="noopener noreferrer">feiertage-api.de</a> mit <code>https://feiertage-api.de/api/?jahr=JAHR&amp;nur_land=BW</code> selbst abrufen, Standort und Inhalt prüfen und hier einfügen. Kein automatischer Abruf. Einträge mit nicht leerem Hinweis werden wie bisher nicht übernommen. Speichern bestätigt weder Quellenherkunft noch Vollständigkeit oder rechtliche Richtigkeit.</p>
        <p class="text-sm opacity-70">Nur fehlende Daten ergänzen. Neu geladene Sollberechnungen nutzen den ergänzten Kalender auch für vergangene Zeiträume; angezeigte Sollwerte und Salden können sich ändern. Bereits gespeicherte Abwesenheitsgutschriften werden nicht neu berechnet. Das Jahr vorher und nachher in der <a class="link" href="dashboard.php?holiday_inventory=1#holiday-inventory" target="_blank" rel="noopener noreferrer">Bestandsanzeige</a> prüfen.</p>
        @if (($holidayRefillDraft ?? null) !== null)<p class="text-sm opacity-70" role="status">Darstellbare Eingaben nur in dieser Antwort beibehalten. Jahr und JSON erneut prüfen und Baden-Württemberg bewusst erneut bestätigen; nichts wurde automatisch erneut gesendet.</p>@endif
        <form method="post" class="space-y-4 mt-4">
            @csrf
            <input type="hidden" name="refill_holidays" value="1">
            <label class="form-control" for="holiday-refill-year"><span class="label-text">Jahr</span><input id="holiday-refill-year" name="holiday_year" type="number" min="1" max="9999" step="1" value="{{ $holidayRefillDraft['year'] ?? '' }}" required class="input input-bordered w-32"></label>
            <label class="form-control" for="holiday-refill-json"><span class="label-text">Vollständige JSON-Antwort (höchstens 128 KiB)</span><textarea id="holiday-refill-json" name="holiday_json" required maxlength="131072" rows="8" spellcheck="false" class="textarea textarea-bordered">{{ $holidayRefillDraft['json'] ?? '' }}</textarea></label>
            <label class="flex items-start gap-2"><input type="checkbox" name="confirm_bw_source" value="BW" required class="checkbox"><span>Ich habe Baden-Württemberg als richtigen Standort, das gewählte Jahr und die Antwort geprüft. Nur fehlende landesweite Daten dieses Jahres ergänzen; Auswirkungen auf angezeigte Sollwerte und Salden sind mir bekannt.</span></label>
            @if ($holidayRefillPreviewEnabled ?? false)
            <button type="button" id="holiday-refill-preview-button" class="btn btn-secondary" disabled>Nur vorab prüfen · nichts speichern</button>
            @endif
            <button type="submit" class="btn btn-primary">Fehlende BW-Feiertage ergänzen</button>
        </form>
        @if ($holidayRefillPreviewEnabled ?? false)
        <p id="holiday-refill-preview-status" class="text-sm opacity-70 mt-4" role="status">Die Vorabprüfung wird mit JavaScript verfügbar; Speichern bleibt ein eigener bewusster Vorgang.</p>
        <div id="holiday-refill-preview-result" class="mt-4"></div>
        @include('chrono.partials.holiday-refill-preview-script')
        @endif
    </div>
</details>
