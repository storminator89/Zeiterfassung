{{-- Native get_time_records.php fragment; all prepared values are escaped here. --}}
<header class="chrono-ledger-toolbar">
    <h2>{{ $records->copy->text('UI_MY_RECORDS') }}</h2>
    <div class="chrono-ledger-controls">
        <label class="sr-only" for="time-record-review">Buchungen filtern</label><select id="time-record-review" data-record-review-filter data-page="{{ $records->page }}" class="form-select form-select-sm" aria-label="Buchungen filtern"><option value="all" @selected(! $records->reviewOnly)>Alle Buchungen</option><option value="review" @selected($records->reviewOnly)>Mit Prüfhinweis</option></select>
        <details class="chrono-ledger-export"><summary class="btn btn-sm btn-outline-secondary"><x-chrono-icon name="file-export" size="18" /> Export</summary><div class="chrono-ledger-export-menu">
            @if ($records->csvExport)<a href="export_csv.php" target="_blank" rel="noopener noreferrer" title="{{ $records->copy->text('CSV_EXPORT_NEW_CONTEXT_HINT') }}">CSV herunterladen</a>@else<span>CSV nicht verfügbar</span>@endif
            @if ($records->storedValuesExport)<a href="export_stored_values.php" target="_blank" rel="noopener noreferrer">Rohdaten (JSON)</a><x-chrono-help><p>{{ $records->storedValuesText('note') }}</p></x-chrono-help>@endif
        </div></details>
        <x-chrono-help label="Prüfhinweise der Buchungen"><p>Die Prüfansicht zeigt erkannte ungültige Zeiten und Überschneidungen. Einträge ohne Hinweis sind kein vollständiger Bestandscheck. Tageswerte berücksichtigen weiterhin alle eigenen Einträge des Tages.</p></x-chrono-help>
    </div>
</header>
@if ($records->reviewOnly && $records->totalRecords === 0)
<p class="text-sm mb-4" role="status">Keine passenden Prüfhinweise gefunden. Dies bestätigt weder einen fehlerfreien Bestand noch rekonstruierbare Altzeiten.</p>
@endif
<div id="timeRecordsTable" class="card chrono-ledger">
    <div class="chrono-ledger-body">
        <div class="chrono-ledger-days">
            <table class="table w-full">
                <thead class="chrono-ledger-head">
                    <tr>
                        <th class="text-left px-6 py-4 font-semibold text-sm w-48">Datum</th>
                        <th class="text-center px-6 py-4 font-semibold text-sm w-32">{{ $records->copy->text('TABLE_HEADER_DURATION') }}</th>
                        <th class="text-center px-6 py-4 font-semibold text-sm w-32">Pause</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-base-200">
                @foreach ($records->groups as $group)
                    <tr class="chrono-day-row" data-date="{{ $group['group_key'] }}">
                        <td class="px-6 py-4 w-48">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2">
                                    <div class="font-medium">{{ $group['formatted_date'] }}</div>
                                    <div class="badge badge-sm badge-ghost" title="{{ $group['is_partial'] ? 'Sichtbare Einträge / alle Einträge des Tages' : 'Anzahl der Einträge' }}">{{ $group['is_partial'] ? $group['entry_count'] . ' / ' . $group['day_entry_count'] : $group['entry_count'] }}</div>
                                    @if ($group['is_unresolved'])<span class="badge badge-warning badge-sm">Klärung offen</span>@endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 w-32">
                            <div class="flex items-center justify-center gap-2">
                                <x-chrono-icon name="clock" size="18" />
                                <span class="font-medium">{{ $group['total_duration'] }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 w-32">
                            <div class="flex items-center justify-center gap-2">
                                <i class="fas fa-pause text-primary/75" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 5v14M16 5v14"/></svg></i>
                                <span class="font-medium">{{ $group['total_pause'] === null ? 'Ungültig' : $group['total_pause'] . ' min' }}</span>
                            </div>
                        </td>
                    </tr>
                    <tr class="details-row bg-base-100/50" data-date="{{ $group['group_key'] }}" >
                        <td colspan="3" class="p-0">
                            <div class="chrono-day-entries">
                                @if ($group['is_partial'])<p class="text-sm opacity-70 mb-3">Teilansicht: {{ $group['entry_count'] }} / {{ $group['day_entry_count'] }} Einträge auf dieser Seite. Dauer und Pause berücksichtigen alle Einträge des Tages.</p>@endif
                                @if ($group['invalid_count'] > 0)<p class="text-sm text-warning mb-3" role="alert">Datenqualität: {{ $group['invalid_count'] }} ungültige Einträge. Keine verlässliche Tagessumme; bitte „Korrektur / Storno“ verwenden.</p>@endif
                                @if ($group['is_unresolved'])<p class="text-sm text-warning mb-3" role="alert">Klärung offen: Arbeitszeit und ganztägige Abwesenheit überschneiden sich. Die Tagessumme ist nicht abschließend. Einzelwerte bleiben unverändert; bitte über „Korrektur / Storno“ mit Freigabe klären.</p>@endif
                                <div class="chrono-entry-list">
                                    @foreach ($group['records'] as $row)
                                        <div class="chrono-entry">
                                            <div class="chrono-entry-period"><x-chrono-icon name="clock" size="18" /><span title="{{ \App\View\ExistingUi\HumanTime::full($row['start_label']) }}">{{ \App\View\ExistingUi\HumanTime::clock($row['start_label']) }}</span><span class="text-secondary">–</span><span title="{{ \App\View\ExistingUi\HumanTime::full($row['end_label']) }}">{{ \App\View\ExistingUi\HumanTime::clock($row['end_label'], $row['start_label']) }}</span></div>
                                            <div class="chrono-entry-duration"><span class="chrono-entry-caption">Dauer</span><strong>{{ $row['duration_label'] }}</strong></div>
                                            <div class="chrono-entry-break"><span class="chrono-entry-caption">Pause</span>{{ $row['pause'] }} min</div>
                                            <div class="chrono-entry-place">{{ $records->location($row['location']) }}</div>
                                            <div class="chrono-entry-actions"><a class="btn btn-sm btn-ghost-secondary" href="adjustments.php?record_id={{ $row['id'] }}" aria-label="Buchung korrigieren"><x-chrono-icon name="pencil" size="18" /><span>Korrektur</span></a>
                                            @if ($records->historyLinks && is_int($row['id']) && $row['id'] > 0)<a class="btn btn-sm btn-icon btn-ghost-secondary" href="{{ 'history.php?entity=zeiterfassung&record_id='.$row['id'].'&relations=1' }}" target="_blank" rel="noopener noreferrer" aria-label="Buchungshistorie" title="{{ $records->copy->text('TIME_RECORD_HISTORY_HINT') }}"><x-chrono-icon name="history" size="18" /></a>@endif
                                            </div>
                                            @if ($row['comment'] !== '')<p class="chrono-entry-note">{{ $row['comment'] }}</p>@endif
                                            <div class="chrono-entry-findings">@if ($row['is_invalid'])<span class="block text-sm text-warning">{{ $row['validation_error'] }}</span>@endif @if ($row['is_unresolved'])<span class="block text-sm text-warning">Klärung offen: Überschneidung mit Arbeitszeit / Abwesenheit. Einzelwert unverändert.</span>@endif
                                            @if (!empty($records->workOverlapFindings[(string) $row['id']]))<span class="block text-sm text-warning">Prüfhinweis: Abgeschlossene Arbeitszeiten überschneiden sich. Bruttointervalle, keine Pausenlage; keine automatische Kürzung. Dauer und Summen unverändert. Über Korrektur / Storno manuell klären.</span>@endif
                                            @foreach ($records->localTimeFindings[(string) $row['id']] ?? [] as $column => $category)
                                            @if ($category === 'invalid_timestamp')
                                            <span class="block text-sm text-warning">Altzeit-Prüfhinweis ({{ $column === 'startzeit' ? 'Start' : 'Ende' }}): von der strengen Altzeitdiagnose als ungültig erkannt. Bestehende Laufzeitberechnung unverändert, nicht neu bestätigt. Über Korrektur / Storno prüfen.</span>
                                            @elseif ($category === 'unsupported_timestamp_shape')
                                            <span class="block text-sm text-warning">Format-Prüfhinweis ({{ $column === 'startzeit' ? 'Start' : 'Ende' }}): lesbar, aber außerhalb des Altzeit-Diagnoseformats. Das Zeitformat wird damit nicht als ungültig bewertet; Dauer/Summen unverändert. Über Korrektur / Storno prüfen.</span>
                                            @else
                                            <span class="block text-sm text-warning">DST-Prüfhinweis ({{ $column === 'startzeit' ? 'Start' : 'Ende' }}): {{ $category === 'ambiguous_berlin_local_time' ? 'doppelte Berliner Stunde ohne Offset. Kein eindeutiger Zeitpunkt' : 'Berliner Zeitlücke ohne Offset. Kein existierender lokaler Zeitpunkt' }}; Dauer/Summen nicht bestätigt.</span>
                                            @endif
                                            @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                    </tbody>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($records->totalPages > 1)
        <div class="flex justify-center mt-4">
            <div class="btn-group">
                @if ($records->page > 1)
                    <button onclick="updateTimeRecordsTable(1)" class="btn">«</button>
                    <button onclick="updateTimeRecordsTable({{ $records->page - 1 }})" class="btn">‹</button>
                @endif
                @for ($i = max(1, $records->page - 2); $i <= min($records->totalPages, $records->page + 2); $i++)
                    <button onclick="updateTimeRecordsTable({{ $i }})" class="btn {{ $i === $records->page ? 'btn-active' : '' }}">{{ $i }}</button>
                @endfor
                @if ($records->page < $records->totalPages)
                    <button onclick="updateTimeRecordsTable({{ $records->page + 1 }})" class="btn">›</button>
                    <button onclick="updateTimeRecordsTable({{ $records->totalPages }})" class="btn">»</button>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
