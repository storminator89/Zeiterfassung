@extends('chrono.layouts.app')

@section('content')
<main class="container mx-auto px-4 py-8 chrono-page">
    <h1 class="text-2xl font-bold mb-6"><x-chrono-icon name="pencil" size="24" /> Korrekturanträge</h1>
    <x-chrono-help><p class="opacity-70">Nachträgliche Änderungen mit Begründung und Freigabe. Originalereignisse bleiben erhalten. Eigene Anträge müssen von einer anderen berechtigten Person freigegeben werden.</p></x-chrono-help>
    @if ($corrections->error)<div class="alert alert-error mb-6" role="alert">{{ $corrections->error }}</div>@endif
    @if ($corrections->saved)<div class="alert alert-success mb-6">Gespeichert.</div>@endif

    @if (! $corrections->selectionInvalid)
    <details class="chrono-disclosure mb-6" @if ($corrections->selectedRecordId > 0 || $corrections->requestDraft || $corrections->metadataDraft || $corrections->error) open @endif>
        <summary><x-chrono-icon name="pencil" size="20" /> Korrektur beantragen</summary>
        <section class="mt-4 chrono-form-panel">
        @if ($corrections->requestDraft)<p class="text-sm opacity-70 mb-4" role="status">Entwurf beibehalten. Bitte prüfen und erneut senden.</p>@endif
        <details id="correction-time-helper" class="mb-4" hidden open>
            <summary class="cursor-pointer font-semibold">Datum und Uhrzeit</summary>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-3">
                @foreach (['start' => 'Beginn', 'end' => 'Ende'] as $field => $label)
                <div data-correction-time="{{ $field }}">
                    <label class="block text-sm font-semibold mb-2" for="{{ $field }}-local">{{ $label }}</label>
                    <input class="input input-bordered w-full form-control" id="{{ $field }}-local" type="datetime-local" step="1" aria-describedby="{{ $field }}-time-status">
                    <div data-time-occurrence hidden>
                        <label class="block text-sm font-semibold mt-3 mb-2" for="{{ $field }}-occurrence">Welcher Zeitpunkt?</label>
                        <select class="select select-bordered w-full form-select" id="{{ $field }}-occurrence"></select>
                    </div>
                    <p id="{{ $field }}-time-status" class="text-sm opacity-70 mt-2" role="status" aria-live="polite"></p>
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-2" data-time-apply disabled>Übernehmen</button>
                </div>
                @endforeach
            </div>
        </details>
        <form method="post" id="correction-request-form" class="chrono-form-panel">
            @csrf
            <fieldset @disabled(! $corrections->enabled)>
                <input class="input input-bordered w-full form-control" type="hidden" name="action" value="request">
                <label class="block text-sm font-semibold mt-4 mb-2" for="record_id">Bestehender Eintrag</label>
                <select class="select select-bordered w-full form-select" id="record_id" name="record_id">
                    <option value="0">Fehlende Buchung nachtragen</option>
                    @foreach ($corrections->recordOptions as $r)
                    <option value="{{ $r['id'] }}" @selected($corrections->selectedRecordId === $r['id'])>#{{ $r['id'] }}, {{ \App\View\ExistingUi\HumanTime::full($r['start']) }} bis {{ \App\View\ExistingUi\HumanTime::full($r['end']) }}</option>
                    @endforeach
                </select>
                <label class="block text-sm font-semibold mt-4 mb-2"><input class="checkbox checkbox-primary form-check-input" type="checkbox" name="cancel" value="1" @checked($corrections->requestDraft['cancel'] ?? false)> Bestehende Buchung stornieren (Zeitfelder dann leer lassen)</label>
                <details class="mb-4"><summary>Zeitwerte manuell eingeben</summary><div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div><label class="block text-sm font-semibold mt-4 mb-2" for="start">Beginn mit Zeitzone</label><input class="input input-bordered w-full form-control" id="start" name="start" value="{{ $corrections->requestDraft['start'] ?? '' }}" placeholder="2026-10-25T09:00+01:00"></div>
                    <div><label class="block text-sm font-semibold mt-4 mb-2" for="end">Ende mit Zeitzone</label><input class="input input-bordered w-full form-control" id="end" name="end" value="{{ $corrections->requestDraft['end'] ?? '' }}" placeholder="2026-10-25T17:00+01:00"></div>
                </div></details><div>
                    <div><label class="block text-sm font-semibold mt-4 mb-2" for="pause">Tatsächliche Pause (Minuten)</label><input class="input input-bordered w-full form-control" id="pause" name="pause" value="{{ $corrections->requestDraft['pause'] ?? '' }}" type="number" min="0"></div>
                </div>
                <x-chrono-help><small class="block opacity-70 mt-3">Deutschland: Winterzeit +01:00, Sommerzeit +02:00. Abwesenheitsgutschriften beginnen um 00:00 Uhr Ortszeit; als Ende 00:00 plus Sollminuten und als Pause 0 eintragen. Arbeitsnachträge dürfen nicht in der Zukunft liegen. Bei Zeitumstellungen den Offset für jeden Zeitpunkt einzeln angeben.</small></x-chrono-help>
                <label class="block text-sm font-semibold mt-4 mb-2" for="reason">Begründung</label>
                <textarea class="textarea textarea-bordered w-full form-control" id="reason" name="reason" minlength="5" maxlength="1000" required>{{ $corrections->requestDraft['reason'] ?? '' }}</textarea>
                <div class="flex flex-wrap gap-3 mt-5"><button type="submit" class="btn btn-primary">Antrag stellen</button></div>
            </fieldset>
        </form>
        @if ($corrections->metadataEnabled)
        <details class="mt-5" @if ($corrections->metadataDraft) open @endif>
            <summary class="cursor-pointer font-semibold">Arbeitsort oder Notiz ändern</summary>
            <x-chrono-help><p class="text-sm opacity-70 mt-3">Nur bestehende abgeschlossene eigene Arbeitsbuchungen, keine Abwesenheiten. Beide Angaben ersetzen die bisherigen Werte nach fremder berechtigter Freigabe; eine leere Notiz löscht die alte Notiz. Zeiten, Pausen und Originalereignisse bleiben unverändert.</p></x-chrono-help>
            @if ($corrections->metadataDraft)<p class="text-sm opacity-70 mt-3" role="status">Felder nur zur Prüfung beibehalten. Ersetzen erneut bewusst bestätigen; nichts wird automatisch gesendet.</p>@endif
            <form method="post">
                @csrf
                <fieldset @disabled(! $corrections->enabled)>
                    <input type="hidden" name="action" value="request_metadata_v1">
                    <label class="block text-sm font-semibold mt-4 mb-2" for="metadata_record_id">Buchung</label>
                    <select class="select select-bordered w-full form-select" id="metadata_record_id" name="record_id"><option value="0">Bitte bestehende Buchung wählen</option>@foreach ($corrections->recordOptions as $r)<option value="{{ $r['id'] }}" @selected($corrections->selectedRecordId === $r['id'])>#{{ $r['id'] }}, {{ \App\View\ExistingUi\HumanTime::full($r['start']) }} bis {{ \App\View\ExistingUi\HumanTime::full($r['end']) }}</option>@endforeach</select>
                    <label class="block text-sm font-semibold mt-4 mb-2" for="metadata_location">Neuer Arbeitsort</label>
                    <select class="select select-bordered w-full form-select" id="metadata_location" name="location">@foreach (['office'=>'Büro','home_office'=>'Homeoffice','business_trip'=>'Dienstreise'] as $code=>$label)<option value="{{ $code }}" @selected(($corrections->metadataDraft['location'] ?? 'office') === $code)>{{ $label }}</option>@endforeach</select>
                    <label class="block text-sm font-semibold mt-4 mb-2" for="metadata_note">Neue Notiz (leer löscht)</label>
                    <textarea class="textarea textarea-bordered w-full form-control" id="metadata_note" name="note" maxlength="1000">{{ $corrections->metadataDraft['note'] ?? '' }}</textarea>
                    <label class="block text-sm font-semibold mt-4 mb-2" for="metadata_reason">Begründung</label><textarea class="textarea textarea-bordered w-full form-control" id="metadata_reason" name="reason" minlength="5" maxlength="1000" required>{{ $corrections->metadataDraft['reason'] ?? '' }}</textarea>
                    <label class="block text-sm mt-4"><input class="checkbox checkbox-primary form-check-input" type="checkbox" name="metadata_confirm" value="1" required> Arbeitsort und Notiz durch diese Angaben ersetzen.</label>
                    <div class="flex flex-wrap gap-3 mt-5"><button type="submit" class="btn btn-primary">Änderung beantragen</button></div>
                </fieldset>
            </form>
        </details>
        @endif
        </section>
    </details>
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
    <h2 class="text-xl font-semibold mb-4">Anträge ({{ $corrections::STATUS_LABELS[$corrections->requestStatus] }})</h2>
    @if ($corrections->requestTotal > 0)<p class="text-sm opacity-70 mb-4">{{ $corrections->requestTotal }} Anträge @if ($corrections->requestPages > 1), Seite {{ $corrections->requestPage }} von {{ $corrections->requestPages }}@endif</p>@endif
    @if (! $corrections->requests)<p class="mb-6" role="status">Keine Anträge in dieser Auswahl.</p>@endif
    @foreach ($corrections->requests as $a)
    @php($metadataPreview = $a['metadata_preview'] ?? null)
    <details class="chrono-disclosure mb-4" @if ($corrections->balancePreviewRequestId === $a['id'] || ($corrections->withdrawalDraft['id'] ?? null) === $a['id'] || ($corrections->reviewDraft['id'] ?? null) === $a['id'] || $corrections->requestLookupValue === (string) $a['id']) open @endif>
        <summary>#{{ $a['id'] }} {{ $a['username'] }}: {{ ['pending' => 'Offen', 'approved' => 'Freigegeben', 'rejected' => 'Abgelehnt', 'withdrawn' => 'Zurückgenommen'][$a['status']] }}</summary>
        <section class="mt-4">
        @if (isset($a['lifecycle']))
        <p class="text-sm opacity-70 mb-3" data-correction-lifecycle>{{ $page->copy->text('CORRECTION_REQUESTED_AT') }}: {{ $a['lifecycle']['created'] ?? $page->copy->text('CORRECTION_TIME_UNAVAILABLE') }}@if ($a['lifecycle']['resolved_kind'] !== null), {{ $page->copy->text($a['lifecycle']['resolved_kind'] === 'withdrawal' ? 'CORRECTION_WITHDRAWN_AT' : 'CORRECTION_DECIDED_AT') }}: {{ $a['lifecycle']['resolved'] ?? $page->copy->text('CORRECTION_TIME_UNAVAILABLE') }}@endif</p>
        @endif
        @if ($metadataPreview !== null)
        <div class="text-sm my-3"><p class="font-semibold">Arbeitsort und Notiz</p>
            @if ($metadataPreview['available'])
            <p>Arbeitsort vorher: {{ $metadataPreview['before_location'] }}, Beantragt: {{ $metadataPreview['after_location'] }}</p>
            <p>Notiz vorher: {{ $metadataPreview['before_note'] }}</p><p>Notiz beantragt: {{ $metadataPreview['after_note'] }}</p>
            @else<p>Metadaten-Antrag nicht auswertbar; keine Freigabezusage.</p>@endif
        </div>
        @elseif ($a['cancel'])<p>Stornierung</p>
        @elseif ($a['preview']['proposed_usable'])<p>{{ \App\View\ExistingUi\HumanTime::full($a['start']) }} bis {{ \App\View\ExistingUi\HumanTime::full($a['end']) }}, {{ $a['pause'] }} Minuten Pause</p>
        @else<p>Beantragte Zeitangaben nicht auswertbar.</p>@endif
        @if ($metadataPreview === null)
        <div class="text-sm my-3">
            <p class="font-semibold">Buchungsdauer</p>
            <p>Vorher: {{ $a['preview']['before'] }}, Beantragt: {{ $a['preview']['after'] }}</p>
            <p>{{ $a['preview']['difference'] === null ? 'Keine verlässliche Dauer-Differenz verfügbar.' : 'Dauer-Differenz: ' . $a['preview']['difference'] }}</p>
            <x-chrono-help><p class="opacity-70">Nur ausgewählte Zeitfelder des gespeicherten Originals und Antrags. Keine Freigabe- oder Saldozusage; die Entscheidung prüft den aktuellen Datensatz erneut.</p></x-chrono-help>
        </div>
        @endif
        @if ($metadataPreview === null && $a['status'] === 'pending' && $a['user_id'] === $page->userId)
        <div class="text-sm my-3" id="balance-preview-{{ $a['id'] }}">
            @if ($corrections->balancePreviewRequestId === $a['id'] && $corrections->balancePreview)
            <p class="font-semibold">Saldo mit diesem Antrag</p>
                @if ($corrections->balancePreview['available'])
                <p>Jetzt: {{ $corrections->balancePreview['before'] }}, Mit diesem Antrag: {{ $corrections->balancePreview['after'] }}, Änderung: {{ $corrections->balancePreview['difference'] }}</p>
                @else
                <p role="status">Keine verlässliche Vorschau verfügbar. Aktuellen Eintrag und Datenhinweise prüfen.</p>
                @endif
            <x-chrono-help><p class="opacity-70">Nur dieser Antrag auf dem aktuell gelesenen Stand: Startguthaben plus abgeschlossene Buchungstage minus deren Soll, wie Home. Kein vollständiges Vertragszeitkonto, keine Freigabezusage. Andere offene Anträge sind nicht eingerechnet; vor der Entscheidung wird erneut geprüft.</p></x-chrono-help>
            @else
            <a class="btn btn-outline-secondary btn-sm mt-2" href="{{ $corrections->balancePreviewUrl($a['id']) }}">Saldo-Vorschau</a>
            @endif
        </div>
        @endif
        <p>{{ $a['reason'] }}</p>
        @if ($a['review_reason'])<p class="opacity-70">{{ $a['status'] === 'withdrawn' ? 'Rücknahme' : 'Entscheidung' }}: {{ $a['review_reason'] }}</p>@endif
        @if ($corrections->withdrawalsEnabled && $a['status'] === 'pending' && $a['user_id'] === $page->userId)
        <details class="chrono-disclosure mt-4" @if (($corrections->withdrawalDraft['id'] ?? null) === $a['id']) open @endif><summary>Antrag zurücknehmen</summary>
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
        </section>
    </details>
    @endforeach
    @if ($corrections->requestPages > 1)
    <nav class="flex flex-wrap gap-3 mb-6" aria-label="Antragsseiten">
        @if ($corrections->requestPage > 1)<a class="btn btn-outline-secondary" href="{{ $corrections->pageUrl($corrections->requestPage - 1) }}">Neuere Anträge</a>@endif
        @if ($corrections->requestPage < $corrections->requestPages)<a class="btn btn-outline-secondary" href="{{ $corrections->pageUrl($corrections->requestPage + 1) }}">Ältere Anträge</a>@endif
    </nav>
    @endif
</main>
@include('chrono.partials.correction-time-helper')
@endsection
