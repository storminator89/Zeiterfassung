<details id="department-integrity" class="mb-4" @if ($adminPauseSettings->departmentIntegrityRequested) open @endif>
    <summary class="cursor-pointer">Abteilungszuordnungen im gesamten Bestand prüfen</summary>
    <p class="text-sm mt-2">Nur lesende Momentaufnahme aller Benutzer, unabhängig von Suche und Tabellenblatt. Keine automatische Zuordnung, Reparatur, Löschung oder Aktivierung von Fremdschlüsseln.</p>
    <a class="link" href="admin.php?{{ http_build_query(['search' => $adminPauseSettings->users['search'] ?? '', 'page' => $adminPauseSettings->users['page'] ?? 1, 'department_integrity' => '1']) }}#department-integrity">Prüfung ausdrücklich starten / neu laden</a>
    @if ($adminPauseSettings->departmentIntegrityRequested)
        @if ($adminPauseSettings->departmentIntegrity === null)
        <p class="text-warning mt-2" role="alert">Bestandsprüfung derzeit nicht verfügbar. Daraus folgt kein fehlerfreier oder leerer Bestand.</p>
        @else
            @php($integrity = $adminPauseSettings->departmentIntegrity)
            <p class="mt-2" role="status">{{ $integrity['checked'] }} Benutzer geprüft: {{ $integrity['counts']['resolved'] }} aufgelöste Zuordnungen, {{ $integrity['counts']['unassigned'] }} ausdrücklich ohne Zuordnung (NULL), {{ $integrity['problem_count'] }} Zuordnungen zu klären.</p>
            @if ($integrity['unaddressable_count'] > 0)
            <p class="text-warning" role="alert">{{ $integrity['unaddressable_count'] }} Problemzuordnungen gehören zu nichtpositiven Benutzer-IDs. Die vorhandene Prüfliste und der Editor können diese nicht öffnen; getrennte Offlineprüfung erforderlich. Die Gesamtzahl enthält diese Fälle.</p>
            @endif
            @if ($integrity['problem_count'] === 0)
            <p class="text-sm">Keine fehlende oder ungültige Abteilungsreferenz in dieser Momentaufnahme gefunden. Dies bestätigt weder fachlich richtige Zuordnungen noch Migrations- oder Löschfähigkeit.</p>
            @else
            <p class="text-sm">Höchstens 25 Problemzeilen je Prüfseite, nach Benutzer-ID. Ungültige Rohwerte werden hier nicht ausgegeben; bestehende Details über „Bearbeiten“ prüfen und Änderungen ausdrücklich vornehmen. Andere Stammdaten oder Vorgesetztenketten sind nicht Gegenstand dieser Prüfung.</p>
            <div class="overflow-x-auto"><table class="table w-full">
                <thead><tr><th>Benutzer-ID</th><th>{{ $page->copy->text('TABLE_HEADER_USERNAME') }}</th><th>Befund</th><th>{{ $page->copy->text('TABLE_HEADER_ACTIONS') }}</th></tr></thead>
                <tbody>
                    @foreach ($integrity['problems'] as $problem)
                    <tr><td>{{ $problem['id'] }}</td><td>{{ $problem['username'] }}</td>
                        <td>@if ($problem['status'] === 'missing')Abteilungs-ID #{{ $problem['department_id'] }} fehlt.@else Abteilungszuordnung nicht eindeutig prüfbar.@endif</td>
                        <td><button type="button" class="btn btn-ghost btn-sm" data-read-user="{{ $problem['id'] }}" title="{{ $page->copy->text('BUTTON_EDIT') }}">{{ $page->copy->text('BUTTON_EDIT') }}</button></td>
                    </tr>
                    @endforeach
                    @if ($integrity['problems'] === [])<tr><td colspan="4">Keine weiteren Problemzeilen nach der gewählten ID. Für den aktuellen Gesamtbestand die Prüfung neu laden.</td></tr>@endif
                </tbody>
            </table></div>
            @endif
            @if ($integrity['next_after'] !== null)
            <a class="link" href="admin.php?{{ http_build_query(['search' => $adminPauseSettings->users['search'] ?? '', 'page' => $adminPauseSettings->users['page'] ?? 1, 'department_integrity' => '1', 'department_after' => $integrity['next_after']]) }}#department-integrity">Nächste Problemzeilen prüfen</a>
            @endif
        @endif
    @else
    <p class="text-sm mt-2">Noch keine Gesamtprüfung angefordert. Die bestehenden Warnungen einzelner Tabellenzeilen bleiben davon unabhängig.</p>
    @endif
</details>
