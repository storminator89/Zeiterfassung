@extends('chrono.layouts.app')

@section('content')
<main class="container mx-auto px-4 py-8 chrono-page">
    <h1 class="text-2xl font-bold mb-6">Korrekturanträge</h1>
    <p class="opacity-70">Nachträgliche Änderungen mit Begründung und Freigabe. Originalereignisse bleiben erhalten. Eigene Anträge müssen von einer anderen berechtigten Person freigegeben werden.</p>
    @if ($corrections->error)<div class="alert alert-error mb-6" role="alert">{{ $corrections->error }}</div>@endif
    @if ($corrections->saved)<div class="alert alert-success mb-6">Gespeichert.</div>@endif

    @if (! $corrections->selectionInvalid)
    <section class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Arbeitszeit korrigieren oder nachtragen</h2>
        @if ($corrections->requestDraft)<p class="text-sm opacity-70 mb-4" role="status">Antragsfelder zur Prüfung beibehalten. Es wird nichts automatisch erneut gesendet. Ungültige oder überlange Felder gegebenenfalls neu eingeben.</p>@endif
        <details id="correction-time-helper" class="mb-4" hidden>
            <summary class="cursor-pointer font-semibold">Datum und Uhrzeit wählen (Berlin)</summary>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-3">
                @foreach (['start' => 'Beginn', 'end' => 'Ende'] as $field => $label)
                <div data-correction-time="{{ $field }}">
                    <label class="block text-sm font-semibold mb-2" for="{{ $field }}-local">{{ $label }} in Berlin</label>
                    <input class="input input-bordered w-full" id="{{ $field }}-local" type="datetime-local" step="1" aria-describedby="{{ $field }}-time-status">
                    <div data-time-occurrence hidden>
                        <label class="block text-sm font-semibold mt-3 mb-2" for="{{ $field }}-occurrence">Welcher Zeitpunkt?</label>
                        <select class="select select-bordered w-full" id="{{ $field }}-occurrence"></select>
                    </div>
                    <p id="{{ $field }}-time-status" class="text-sm opacity-70 mt-2" role="status" aria-live="polite"></p>
                    <button class="btn btn-outline btn-sm mt-2" type="button" data-time-apply disabled>In ISO-Feld übernehmen</button>
                </div>
                @endforeach
            </div>
        </details>
        <form method="post" id="correction-request-form">
            @csrf
            <fieldset @disabled(! $corrections->enabled)>
                <input class="input input-bordered w-full" type="hidden" name="action" value="request">
                <label class="block text-sm font-semibold mt-4 mb-2" for="record_id">Bestehender Eintrag</label>
                <select class="select select-bordered w-full" id="record_id" name="record_id">
                    <option value="0">Fehlende Buchung nachtragen</option>
                    @foreach ($corrections->recordOptions as $r)
                    <option value="{{ $r['id'] }}" @selected($corrections->selectedRecordId === $r['id'])>#{{ $r['id'] }} · {{ $r['start'] }} – {{ $r['end'] }}</option>
                    @endforeach
                </select>
                <label class="block text-sm font-semibold mt-4 mb-2"><input class="checkbox checkbox-primary" type="checkbox" name="cancel" value="1" @checked($corrections->requestDraft['cancel'] ?? false)> Bestehende Buchung stornieren (Zeitfelder dann leer lassen)</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div><label class="block text-sm font-semibold mt-4 mb-2" for="start">Beginn mit Zeitzone</label><input class="input input-bordered w-full" id="start" name="start" value="{{ $corrections->requestDraft['start'] ?? '' }}" placeholder="2026-10-25T09:00+01:00"></div>
                    <div><label class="block text-sm font-semibold mt-4 mb-2" for="end">Ende mit Zeitzone</label><input class="input input-bordered w-full" id="end" name="end" value="{{ $corrections->requestDraft['end'] ?? '' }}" placeholder="2026-10-25T17:00+01:00"></div>
                    <div><label class="block text-sm font-semibold mt-4 mb-2" for="pause">Tatsächliche Pause (Minuten)</label><input class="input input-bordered w-full" id="pause" name="pause" value="{{ $corrections->requestDraft['pause'] ?? '' }}" type="number" min="0"></div>
                </div>
                <small class="block opacity-70 mt-3">Deutschland: Winterzeit +01:00, Sommerzeit +02:00. Abwesenheitsgutschriften beginnen um 00:00 Uhr Ortszeit; als Ende 00:00 plus Sollminuten und als Pause 0 eintragen. Arbeitsnachträge dürfen nicht in der Zukunft liegen. Bei Zeitumstellungen den Offset für jeden Zeitpunkt einzeln angeben.</small>
                <label class="block text-sm font-semibold mt-4 mb-2" for="reason">Begründung</label>
                <textarea class="textarea textarea-bordered w-full" id="reason" name="reason" minlength="5" maxlength="1000" required>{{ $corrections->requestDraft['reason'] ?? '' }}</textarea>
                <div class="flex flex-wrap gap-3 mt-5"><button class="btn btn-primary">Antrag stellen</button></div>
            </fieldset>
        </form>
    </section>
    @else
    <p class="mb-6"><a class="btn btn-outline" href="adjustments.php">Eigene Buchung neu auswählen</a></p>
    @endif

    <section class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Anträge filtern</h2>
        <p class="mb-4">Offene Anträge: <strong>{{ $corrections->requestCounts['pending'] }}</strong> · innerhalb Ihrer bisherigen Sichtrechte.</p>
        <form method="get" action="adjustments.php">
            @if ($corrections->queueSelection)<input type="hidden" name="record_id" value="{{ $corrections->queueSelection['record_id'] }}">@endif
            <label class="block text-sm font-semibold mt-4 mb-2" for="request_status">Status</label>
            <select class="select select-bordered w-full" id="request_status" name="request_status">
                @foreach ($corrections::STATUS_LABELS as $status => $label)
                <option value="{{ $status }}" @selected($corrections->requestStatus === $status)>{{ $label }} ({{ $corrections->requestCounts[$status] }})</option>
                @endforeach
            </select>
            <div class="flex flex-wrap gap-3 mt-5"><button class="btn btn-primary">Filtern</button></div>
        </form>
    </section>
    <h2 class="text-xl font-semibold mb-4">Anträge: {{ $corrections::STATUS_LABELS[$corrections->requestStatus] }}</h2>
    <p class="text-sm opacity-70 mb-4">{{ $corrections->requestTotal }} Anträge · Seite {{ $corrections->requestPage }} von {{ $corrections->requestPages }}</p>
    @if (! $corrections->requests)<p class="mb-6" role="status">Keine sichtbaren Anträge für diesen Status.</p>@endif
    @foreach ($corrections->requests as $a)
    <section class="card bg-base-100 shadow-xl p-6 mb-6">
        <p class="text-sm font-semibold opacity-70 mb-2">#{{ $a['id'] }} · {{ $a['username'] }} · {{ ['pending' => 'Offen', 'approved' => 'Freigegeben', 'rejected' => 'Abgelehnt', 'withdrawn' => 'Zurückgenommen'][$a['status']] }}</p>
        @if ($a['cancel'])<p>STORNIERUNG · kein neuer Zeitwert</p>
        @elseif ($a['preview']['proposed_usable'])<p>{{ $a['start'] }} – {{ $a['end'] }} · {{ $a['pause'] }} Minuten Pause</p>
        @else<p>Beantragte Zeitangaben nicht auswertbar.</p>@endif
        <div class="text-sm my-3">
            <p class="font-semibold">Gespeicherte Netto-Buchungsdauer · kein Kontosaldo</p>
            <p>Vorher: {{ $a['preview']['before'] }} · Beantragt: {{ $a['preview']['after'] }}</p>
            <p>{{ $a['preview']['difference'] === null ? 'Keine verlässliche Dauer-Differenz verfügbar.' : 'Dauer-Differenz: ' . $a['preview']['difference'] }}</p>
            <p class="opacity-70">Nur ausgewählte Zeitfelder des gespeicherten Originals und Antrags. Keine Freigabe- oder Saldozusage; die Entscheidung prüft den aktuellen Datensatz erneut.</p>
        </div>
        @if ($a['status'] === 'pending' && $a['user_id'] === $page->userId)
        <div class="text-sm my-3" id="balance-preview-{{ $a['id'] }}">
            @if ($corrections->balancePreviewRequestId === $a['id'] && $corrections->balancePreview)
            <p class="font-semibold">Eigener Home-Stand · Vorschau</p>
                @if ($corrections->balancePreview['available'])
                <p>Jetzt: {{ $corrections->balancePreview['before'] }} · Mit diesem Antrag: {{ $corrections->balancePreview['after'] }} · Änderung: {{ $corrections->balancePreview['difference'] }}</p>
                @else
                <p role="status">Keine verlässliche Vorschau verfügbar. Aktuellen Eintrag und Datenhinweise prüfen.</p>
                @endif
            <p class="opacity-70">Nur dieser Antrag auf dem aktuell gelesenen Stand: Startguthaben plus abgeschlossene Buchungstage minus deren Soll, wie Home. Kein vollständiges Vertragszeitkonto, keine Freigabezusage. Andere offene Anträge sind nicht eingerechnet; vor der Entscheidung wird erneut geprüft.</p>
            @else
            <a class="btn btn-outline btn-sm mt-2" href="{{ $corrections->balancePreviewUrl($a['id']) }}">Home-Stand mit Antrag prüfen</a>
            @endif
        </div>
        @endif
        <p>{{ $a['reason'] }}</p>
        @if ($a['review_reason'])<p class="opacity-70">{{ $a['status'] === 'withdrawn' ? 'Rücknahme' : 'Entscheidung' }}: {{ $a['review_reason'] }}</p>@endif
        @if ($corrections->withdrawalsEnabled && $a['status'] === 'pending' && $a['user_id'] === $page->userId)
        <form method="post">
            @csrf
            <input type="hidden" name="action" value="withdraw">
            <input type="hidden" name="id" value="{{ $a['id'] }}">
            <label class="block text-sm font-semibold mt-4 mb-2" for="withdraw_reason_{{ $a['id'] }}">Begründung der Rücknahme</label>
            <input class="input input-bordered w-full" id="withdraw_reason_{{ $a['id'] }}" name="withdraw_reason" minlength="5" maxlength="1000" required>
            <p class="text-sm opacity-70 mt-3">Nur diesen offenen Antrag endgültig zurücknehmen. Buchung und bisherige Historie bleiben erhalten; die Rücknahme wird protokolliert. Bei Bedarf einen neuen Antrag stellen.</p>
            <div class="flex flex-wrap gap-3 mt-5"><button class="btn btn-outline" type="submit">Antrag endgültig zurücknehmen</button></div>
        </form>
        @endif
        @if ($a['status'] === 'pending' && $a['user_id'] !== $page->userId && in_array($page->role, ['admin', 'supervisor'], true))
        <form method="post">
            @csrf
            <fieldset @disabled(! $corrections->enabled)>
                <input class="input input-bordered w-full" type="hidden" name="action" value="review">
                <input class="input input-bordered w-full" type="hidden" name="id" value="{{ $a['id'] }}">
                <label class="block text-sm font-semibold mt-4 mb-2" for="review_reason_{{ $a['id'] }}">Begründung der Entscheidung</label>
                <input class="input input-bordered w-full" id="review_reason_{{ $a['id'] }}" name="review_reason" minlength="3" maxlength="1000" required>
                <div class="flex flex-wrap gap-3 mt-5"><button class="btn btn-primary" name="decision" value="approved">Freigeben</button><button name="decision" value="rejected" class="btn btn-outline">Ablehnen</button></div>
            </fieldset>
        </form>
        @endif
    </section>
    @endforeach
    @if ($corrections->requestPages > 1)
    <nav class="flex flex-wrap gap-3 mb-6" aria-label="Antragsseiten">
        @if ($corrections->requestPage > 1)<a class="btn btn-outline" href="{{ $corrections->pageUrl($corrections->requestPage - 1) }}">Neuere Anträge</a>@endif
        @if ($corrections->requestPage < $corrections->requestPages)<a class="btn btn-outline" href="{{ $corrections->pageUrl($corrections->requestPage + 1) }}">Ältere Anträge</a>@endif
    </nav>
    @endif
</main>
@include('chrono.partials.correction-time-helper')
@endsection
