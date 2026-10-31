@extends('chrono.layouts.app')

@section('content')
<main class="container mx-auto px-4 py-8 chrono-page chrono-corrections">
    <div class="chrono-today-heading"><h1>Korrekturen</h1><img src="assets/illustrations/time-ledger.png" alt="" width="104" height="88"></div>
    @php
        $selectedBooking = collect($corrections->recordOptions)->firstWhere('id', $corrections->selectedRecordId);
        $editMode = request()->query('edit', 'time');
        if ($corrections->metadataDraft) { $editMode = 'metadata'; }
        elseif ($corrections->requestDraft) { $editMode = !empty($corrections->requestDraft['cancel']) ? 'cancel' : 'time'; }
        if (!is_string($editMode) || !in_array($editMode, ['time','metadata','cancel'], true)) { $editMode = 'time'; }
        if (!$corrections->metadataEnabled && $editMode === 'metadata') { $editMode = 'time'; }
        if (!$selectedBooking && $editMode === 'cancel') { $editMode = 'time'; }
        $editQuery = $corrections->selectedRecordId > 0 ? ['record_id'=>$corrections->selectedRecordId] : [];
        $timeDraft = $corrections->requestDraft;
        $metaDraft = $corrections->metadataDraft;
    @endphp
    @if ($corrections->error)<div class="alert alert-error mb-4" role="alert">{{ $corrections->error }}</div>@endif
    @if ($corrections->saved)<div class="alert alert-success mb-4" role="status">Änderung gespeichert.</div>@endif
    @if (! $corrections->selectionInvalid)
    <section class="card chrono-correction-editor mb-6">
        <div class="chrono-editor-body chrono-correction-flow">
            <header class="chrono-correction-context">
                <h2>{{ $selectedBooking ? 'Buchung bearbeiten' : 'Buchung nachtragen' }}</h2>
                <x-chrono-help label="Freigabe der Änderung"><p>Änderungen werden als Antrag gespeichert. Eine andere berechtigte Person gibt sie frei; die ursprüngliche Buchung bleibt in der Historie.</p></x-chrono-help>
            </header>
            <form method="get" action="adjustments.php" id="correction-booking-picker" class="chrono-booking-picker">
                <input type="hidden" name="edit" value="{{ $editMode }}">
                <label for="correction-booking" class="form-label">{{ $selectedBooking ? 'Ausgewählte Buchung' : 'Oder bestehende Buchung auswählen' }}</label>
                <div class="flex gap-2"><select id="correction-booking" name="record_id" class="form-select" required>
                    <option value="" disabled @selected(!$selectedBooking)>Buchung auswählen</option>
                    @foreach($corrections->recordOptions as $r)<option value="{{ $r['id'] }}" @selected($corrections->selectedRecordId === $r['id'])>{{ \App\View\ExistingUi\HumanTime::full($r['start']) }} – {{ \App\View\ExistingUi\HumanTime::clock($r['end'], $r['start']) }}</option>@endforeach
                </select><button class="btn btn-outline-secondary" type="submit" id="correction-booking-load">Auswählen</button></div>
                @if($selectedBooking)<a href="adjustments.php" class="text-sm">Neue Buchung nachtragen</a>@endif
            </form>
            @if($selectedBooking)
            <nav class="chrono-correction-modes" aria-label="Was möchtest du ändern?">
                <a href="{{ 'adjustments.php?'.http_build_query([...$editQuery,'edit'=>'time']) }}" @if($editMode === 'time') aria-current="page" @endif><x-chrono-icon name="clock" size="18" />Zeiten</a>
                @if($corrections->metadataEnabled)<a href="{{ 'adjustments.php?'.http_build_query([...$editQuery,'edit'=>'metadata']) }}" @if($editMode === 'metadata') aria-current="page" @endif><x-chrono-icon name="pencil" size="18" />Arbeitsort &amp; Notiz</a>@endif
                <a href="{{ 'adjustments.php?'.http_build_query([...$editQuery,'edit'=>'cancel']) }}" @if($editMode === 'cancel') aria-current="page" @endif><x-chrono-icon name="calendar-off" size="18" />Stornieren</a>
            </nav>
            @endif
            @if($editMode === 'metadata')
                @if($selectedBooking)
                <form method="post" action="adjustments.php?edit=metadata" class="chrono-focused-form">
                    @csrf
                    <fieldset @disabled(!$corrections->enabled)>
                        <input type="hidden" name="action" value="request_metadata_v1"><input type="hidden" id="metadata_record_id" name="record_id" value="{{ $corrections->selectedRecordId }}">
                        <label class="form-label" for="metadata_location">Arbeitsort</label>
                        @php
                            $currentLocation = $metaDraft['location'] ?? ($selectedBooking['location'] ?? '');
                        @endphp
                        <select class="form-select" id="metadata_location" name="location">
                            @if(!in_array($currentLocation,['office','home_office','business_trip'],true))<option value="" selected>Bitte auswählen</option>@endif
                            @foreach(['office'=>'Büro','home_office'=>'Homeoffice','business_trip'=>'Dienstreise'] as $code=>$label)<option value="{{ $code }}" @selected($currentLocation === $code)>{{ $label }}</option>@endforeach
                        </select>
                        <label class="form-label" for="metadata_note">Notiz</label><textarea class="form-control" id="metadata_note" name="note" maxlength="1000" rows="2">{{ $metaDraft['note'] ?? ($selectedBooking['note'] ?? '') }}</textarea>
                        <label class="form-label" for="metadata_reason">Grund der Änderung</label><textarea class="form-control" id="metadata_reason" name="reason" minlength="5" maxlength="1000" rows="2" placeholder="Was möchtest du korrigieren?" required>{{ $metaDraft['reason'] ?? '' }}</textarea>
                        <label class="form-check"><input class="form-check-input" type="checkbox" name="metadata_confirm" value="1" required><span>Arbeitsort und Notiz durch diese Angaben ersetzen.</span></label>
                        <footer class="chrono-correction-submit"><span>Änderung benötigt Freigabe</span><button class="btn btn-primary" type="submit">Änderung beantragen</button></footer>
                    </fieldset>
                </form>
                @else<p role="status">Bitte zuerst eine Buchung auswählen.</p>@endif
            @else
                <form method="post" action="{{ 'adjustments.php?edit='.$editMode }}" id="correction-request-form" class="chrono-focused-form">
                    @csrf
                    <fieldset @disabled(!$corrections->enabled)>
                        <input type="hidden" name="action" value="request"><input type="hidden" id="record_id" name="record_id" value="{{ $corrections->selectedRecordId }}">
                        <input type="checkbox" name="cancel" value="1" hidden @checked($editMode === 'cancel')>
                        @if($editMode === 'cancel')
                            <p class="chrono-cancel-summary">Diese Buchung soll nach Freigabe storniert werden.</p>
                            <input type="hidden" name="start" value=""><input type="hidden" name="end" value=""><input type="hidden" name="pause" value="0">
                        @else
                            <section id="correction-time-helper" class="chrono-correction-time" hidden>
                                <div class="chrono-form-pair">
                                @foreach(['start'=>'Beginn','end'=>'Ende'] as $field=>$label)
                                    <div data-correction-time="{{ $field }}"><label class="form-label" for="{{ $field }}-local">{{ $label }}</label><input class="form-control" id="{{ $field }}-local" type="datetime-local" step="60" aria-describedby="{{ $field }}-time-status">
                                    <div data-time-occurrence hidden><label class="form-label" for="{{ $field }}-occurrence">Welcher Zeitpunkt?</label><select class="form-select" id="{{ $field }}-occurrence"></select></div>
                                    <p id="{{ $field }}-time-status" class="text-sm text-warning" role="status" aria-live="polite"></p><button type="button" class="btn btn-outline-secondary" data-time-apply disabled>Übernehmen</button></div>
                                @endforeach
                                </div>
                            </section>
                            <details id="correction-raw-times" class="mb-3" open><summary>Zeitangaben prüfen</summary><div class="chrono-form-pair">
                                <div><label class="form-label" for="start">Beginn mit Zeitzone</label><input class="form-control" id="start" name="start" value="{{ $timeDraft ? ($timeDraft['start'] ?? '') : ($selectedBooking['start'] ?? '') }}"></div>
                                <div><label class="form-label" for="end">Ende mit Zeitzone</label><input class="form-control" id="end" name="end" value="{{ $timeDraft ? ($timeDraft['end'] ?? '') : ($selectedBooking['end'] ?? '') }}"></div>
                            </div></details>
                            <div class="chrono-pause-input"><label class="form-label" for="pause">Pause</label><div class="input-group"><input class="form-control" id="pause" name="pause" value="{{ $timeDraft ? ($timeDraft['pause'] ?? '') : ($selectedBooking ? ($selectedBooking['pause'] ?? '') : '0') }}" type="number" min="0" required><span class="input-group-text">Minuten</span></div></div>
                        @endif
                        <label class="form-label" for="reason">{{ $editMode === 'cancel' ? 'Grund der Stornierung' : 'Grund der Änderung' }}</label><textarea class="form-control" id="reason" name="reason" minlength="5" maxlength="1000" rows="2" placeholder="Zum Beispiel: Ausstempeln vergessen" required>{{ $timeDraft['reason'] ?? '' }}</textarea>
                        <footer class="chrono-correction-submit"><span>Änderung benötigt Freigabe</span><button class="btn {{ $editMode === 'cancel' ? 'btn-outline-danger' : 'btn-primary' }}" type="submit">{{ $editMode === 'cancel' ? 'Stornierung beantragen' : 'Änderung beantragen' }}</button></footer>
                    </fieldset>
                </form>
            @endif
        </div>
    </section>
    @else
    <p class="mb-6"><a class="btn btn-outline-secondary" href="adjustments.php">Buchung auswählen</a></p>
    @endif

    <details class="chrono-disclosure mb-6" @if ($corrections->requestStatus !== 'pending' || $corrections->ownRequestsOnly || $corrections->requestLookupValue !== '' || $corrections->ownRequestsInvalid || $corrections->ownRequestsUnavailable || $corrections->requestLookupInvalid || $corrections->requestLookupUnavailable) open @endif>
        <summary><x-chrono-icon name="filter" size="20" /> Anträge filtern</summary>
        <div class="mt-4">
        @if ($corrections->ownRequestsInvalid)<div class="alert alert-error mb-4" role="alert">Ungültige Auswahl für eigene Anträge. Es werden keine Anträge angezeigt. Bitte die Auswahl neu setzen.</div>
        @elseif ($corrections->ownRequestsUnavailable)<div class="alert alert-error mb-4" role="alert">Eigene Antragsauswahl ist in diesem Profil nicht verbunden. Es werden keine Anträge angezeigt.</div>@endif
        @if ($corrections->requestLookupInvalid)<div class="alert alert-error mb-4" role="alert">Ungültige Antrags-ID. Bitte eine positive ganze ID ohne Leerzeichen oder führende Nullen eingeben. Es werden keine Anträge angezeigt.</div>
        @elseif ($corrections->requestLookupUnavailable)<div class="alert alert-error mb-4" role="alert">Antrags-ID-Suche ist in diesem Profil nicht verbunden. Es werden keine Anträge angezeigt.</div>@endif
        <p class="mb-4">Offen: <strong>{{ $corrections->requestCounts['pending'] }}</strong></p>
        <form method="get" action="adjustments.php">
            @if (array_key_exists('record_id', $corrections->queueSelection))<input type="hidden" name="record_id" value="{{ $corrections->queueSelection['record_id'] }}">@endif
            <label class="block text-sm font-semibold mt-4 mb-2" for="request_status">Status</label>
            <select class="select select-bordered w-full form-select" id="request_status" name="request_status">
                @foreach ($corrections::STATUS_LABELS as $status => $label)
                <option value="{{ $status }}" @selected($corrections->requestStatus === $status)>{{ $label }} ({{ $corrections->requestCounts[$status] }})</option>
                @endforeach
            </select>
            <label class="block text-sm font-semibold mt-4 mb-2" for="own_requests">Antragsauswahl</label>
            <select class="select select-bordered w-full form-select" id="own_requests" name="own_requests"><option value="" @selected(! $corrections->ownRequestsOnly)>Alle Anträge</option><option value="1" @selected($corrections->ownRequestsOnly)>Nur eigene Anträge</option></select>

            <label class="block text-sm font-semibold mt-4 mb-2" for="request_id">Antrags-ID (optional)</label>
            <input class="input input-bordered w-full form-control" type="text" inputmode="numeric" id="request_id" name="request_id" value="{{ $corrections->requestLookupValue }}">
            <x-chrono-help><p class="text-sm opacity-70 mt-3">Status und Antrags-ID gelten gemeinsam, nur innerhalb Ihrer bisherigen Sichtrechte. Ein leeres Ergebnis bestätigt weder Existenz noch Zugriff; auch ein anderer Status kann die Auswahl leeren.</p></x-chrono-help>
            <div class="flex flex-wrap gap-3 mt-5"><button type="submit" class="btn btn-primary">Filtern</button>@if ($corrections->requestLookupInvalid || $corrections->requestLookupUnavailable || $corrections->requestLookupValue !== '')<a class="btn btn-outline-secondary" href="{{ $corrections->requestLookupResetUrl() }}">Antrags-ID entfernen</a>@endif</div>
        </form>
        </div>
    </details>
    <h2 class="text-xl font-semibold mb-4">Anträge</h2>
    @if ($corrections->requestTotal > 0)<p class="text-sm opacity-70 mb-4">{{ $corrections->requestTotal }} {{ $corrections->requestTotal === 1 ? 'Antrag' : 'Anträge' }} @if ($corrections->requestPages > 1), Seite {{ $corrections->requestPage }} von {{ $corrections->requestPages }}@endif</p>@endif
    @if (! $corrections->requests)<p class="mb-6" role="status">Keine Anträge in dieser Auswahl.</p>@endif
    @foreach ($corrections->requests as $a)
    @php
        $metadataPreview = $a['metadata_preview'] ?? null;
        $requestKind = $metadataPreview !== null ? 'Arbeitsort & Notiz' : ($a['cancel'] ? 'Stornierung' : 'Zeitänderung');
    @endphp
    <article id="balance-preview-{{ $a['id'] }}" class="card chrono-request-card mb-4" aria-label="{{ $requestKind.' #'.$a['id'] }}">
        <header class="chrono-request-header"><div><h3>{{ $requestKind }}</h3>
            @if($a['user_id'] !== $page->userId)<p>{{ $a['username'] }}</p>@endif
            @if(isset($a['lifecycle']))<p data-correction-lifecycle>Eingereicht {{ \App\View\ExistingUi\HumanTime::full($a['lifecycle']['created'] ?? null) }}</p>@endif
        </div><span class="badge {{ $a['status'] === 'pending' ? 'bg-warning-lt' : ($a['status'] === 'approved' ? 'bg-success-lt' : 'bg-secondary-lt') }}">{{ ['pending'=>'Offen','approved'=>'Freigegeben','rejected'=>'Abgelehnt','withdrawn'=>'Zurückgenommen'][$a['status']] }}</span></header>
        <div class="chrono-request-body">
        @if($metadataPreview !== null)
            @if($metadataPreview['available'])
            <div class="chrono-request-comparison"><div><span>Bisher</span><strong>{{ $metadataPreview['before_location'] }}</strong><p>{{ $metadataPreview['before_note'] }}</p></div><div><span>Beantragt</span><strong>{{ $metadataPreview['after_location'] }}</strong><p>{{ $metadataPreview['after_note'] }}</p></div></div>
            @else<p role="status">Diese Änderung lässt sich nicht darstellen. Bitte den Antrag prüfen.</p>@endif
        @elseif(!$a['cancel'] && $a['preview']['proposed_usable'])
            <p class="chrono-request-period"><x-chrono-icon name="calendar" size="18" />{{ \App\View\ExistingUi\HumanTime::full($a['start']) }} – {{ \App\View\ExistingUi\HumanTime::clock($a['end'], $a['start']) }}<span>{{ $a['pause'] }} min Pause</span></p>
        @elseif(!$a['cancel'])<p role="status">Beantragte Zeitangaben bitte prüfen.</p>@endif
        @if($metadataPreview === null)
            <div class="chrono-request-comparison"><div><span>Bisherige Dauer</span><strong>{{ $a['preview']['before'] }}</strong></div><div><span>Beantragte Dauer</span><strong>{{ $a['preview']['after'] }}</strong></div></div>
            <div class="chrono-request-difference">{{ $a['preview']['difference'] === null ? 'Änderung nicht berechenbar' : 'Änderung: '.$a['preview']['difference'] }} <x-chrono-help label="Vergleich der Buchungsdauer"><p>Nur ausgewählte Zeitfelder des gespeicherten Originals und Antrags. Keine Freigabe- oder Saldozusage; die Entscheidung prüft den aktuellen Datensatz erneut.</p></x-chrono-help></div>
        @endif
        <div class="chrono-request-reason"><span>Grund</span><p>{{ $a['reason'] }}</p></div>
        @if($a['review_reason'])<div class="chrono-request-reason"><span>{{ $a['status'] === 'withdrawn' ? 'Rücknahme' : 'Entscheidung' }}</span><p>{{ $a['review_reason'] }}</p></div>@endif
        @if(isset($a['lifecycle']) && $a['lifecycle']['resolved_kind'] !== null)<p class="text-sm text-secondary">{{ $a['lifecycle']['resolved_kind'] === 'withdrawal' ? 'Zurückgenommen' : 'Entschieden' }} {{ \App\View\ExistingUi\HumanTime::full($a['lifecycle']['resolved'] ?? null) }}</p>@endif
        @if($metadataPreview === null && $a['status'] === 'pending' && $a['user_id'] === $page->userId && $corrections->balancePreviewRequestId === $a['id'] && $corrections->balancePreview)
            <section class="chrono-request-balance" aria-label="Saldo-Vorschau">
                @if($corrections->balancePreview['available'])<div class="chrono-request-comparison"><div><span>Saldo jetzt</span><strong>{{ $corrections->balancePreview['before'] }}</strong></div><div><span>Mit diesem Antrag</span><strong>{{ $corrections->balancePreview['after'] }}</strong></div></div><p>Änderung: {{ $corrections->balancePreview['difference'] }}</p>
                @else<p role="status">Keine verlässliche Vorschau verfügbar. Aktuellen Eintrag und Datenhinweise prüfen.</p>@endif
                <x-chrono-help label="Berechnung der Vorschau"><p>Nur dieser Antrag auf dem aktuell gelesenen Stand: Startguthaben plus abgeschlossene Buchungstage minus deren Soll, wie Home. Kein vollständiges Vertragszeitkonto, keine Freigabezusage. Andere offene Anträge sind nicht eingerechnet; vor der Entscheidung wird erneut geprüft.</p></x-chrono-help>
            </section>
        @endif
        </div>
        <footer class="chrono-request-actions">
        @if($metadataPreview === null && $a['status'] === 'pending' && $a['user_id'] === $page->userId && !($corrections->balancePreviewRequestId === $a['id'] && $corrections->balancePreview))<a class="btn btn-outline-secondary btn-sm" href="{{ $corrections->balancePreviewUrl($a['id']) }}">Saldo-Vorschau</a>@endif
        @if ($corrections->withdrawalsEnabled && $a['status'] === 'pending' && $a['user_id'] === $page->userId)
        <details class="chrono-request-withdraw" @if (($corrections->withdrawalDraft['id'] ?? null) === $a['id']) open @endif><summary class="btn btn-ghost-secondary btn-sm">Zurücknehmen</summary>
        <form method="post">
            @csrf
            <input type="hidden" name="action" value="withdraw">
            <input type="hidden" name="id" value="{{ $a['id'] }}">
            <label class="block text-sm font-semibold mt-4 mb-2" for="withdraw_reason_{{ $a['id'] }}">Begründung der Rücknahme</label>
            <input class="input input-bordered w-full form-control" id="withdraw_reason_{{ $a['id'] }}" name="withdraw_reason" value="{{ ($corrections->withdrawalDraft['id'] ?? null) === $a['id'] ? ($corrections->withdrawalDraft['withdraw_reason'] ?? '') : '' }}" minlength="5" maxlength="1000" required>
            @if (($corrections->withdrawalDraft['id'] ?? null) === $a['id'])<p class="text-sm opacity-70 mt-3" role="status">Begründung zur Prüfung beibehalten. Aktuellen Antrag prüfen und Rücknahme erneut bewusst wählen; nichts wird automatisch gesendet.</p>@endif
            <x-chrono-help><p class="text-sm opacity-70 mt-3">Nur diesen offenen Antrag endgültig zurücknehmen. Buchung und bisherige Historie bleiben erhalten; die Rücknahme wird protokolliert. Bei Bedarf einen neuen Antrag stellen.</p></x-chrono-help>
            <div class="flex flex-wrap gap-3 mt-5"><button type="submit" class="btn btn-outline-secondary">Antrag endgültig zurücknehmen</button></div>
        </form>
        </details>
        @endif
        @if ($a['status'] === 'pending' && $a['user_id'] !== $page->userId && in_array($page->role, ['admin', 'supervisor'], true))
        <form method="post">
            @csrf
            <fieldset @disabled(! $corrections->enabled)>
                <input class="input input-bordered w-full form-control" type="hidden" name="action" value="review">
                <input class="input input-bordered w-full form-control" type="hidden" name="id" value="{{ $a['id'] }}">
                <label class="block text-sm font-semibold mt-4 mb-2" for="review_reason_{{ $a['id'] }}">Begründung der Entscheidung</label>
                <input class="input input-bordered w-full form-control" id="review_reason_{{ $a['id'] }}" name="review_reason" value="{{ ($corrections->reviewDraft['id'] ?? null) === $a['id'] ? ($corrections->reviewDraft['review_reason'] ?? '') : '' }}" minlength="3" maxlength="1000" required>
                @if (($corrections->reviewDraft['id'] ?? null) === $a['id'])<p class="text-sm opacity-70 mt-3" role="status">Begründung zur Prüfung beibehalten. Aktuellen Antrag prüfen und Entscheidung erneut bewusst wählen; nichts wird automatisch gesendet.</p>@endif
                <div class="flex flex-wrap gap-3 mt-5"><button type="submit" class="btn btn-primary" name="decision" value="approved">Freigeben</button><button name="decision" value="rejected" class="btn btn-outline-secondary">Ablehnen</button></div>
            </fieldset>
        </form>
        @endif
        </footer>
    </article>
    @endforeach
    @if ($corrections->requestPages > 1)
    <nav class="flex flex-wrap gap-3 mb-6" aria-label="Antragsseiten">
        @if ($corrections->requestPage > 1)<a class="btn btn-outline-secondary" href="{{ $corrections->pageUrl($corrections->requestPage - 1) }}">Neuere Anträge</a>@endif
        @if ($corrections->requestPage < $corrections->requestPages)<a class="btn btn-outline-secondary" href="{{ $corrections->pageUrl($corrections->requestPage + 1) }}">Ältere Anträge</a>@endif
    </nav>
    @endif
</main>
<script>
(function () {
    const picker = document.getElementById('correction-booking');
    const load = document.getElementById('correction-booking-load');
    if (picker && load) { load.hidden = true; picker.addEventListener('change', () => picker.form.requestSubmit()); }
}());
</script>
@include('chrono.partials.correction-time-helper')
@endsection
