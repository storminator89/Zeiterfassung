@extends('chrono.layouts.app')

@section('content')
<main class="container mx-auto px-4 py-8 chrono-page">
    <h1 class="text-2xl font-bold mb-6">Korrekturanträge</h1>
    <p class="opacity-70">Nachträgliche Änderungen mit Begründung und Freigabe. Originalereignisse bleiben erhalten. Eigene Anträge müssen von einer anderen berechtigten Person freigegeben werden.</p>
    @if ($corrections->error)<div class="alert alert-error mb-6" role="alert">{{ $corrections->error }}</div>@endif
    @if ($corrections->saved)<div class="alert alert-success mb-6">Gespeichert.</div>@endif

    <section class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Arbeitszeit korrigieren oder nachtragen</h2>
        <form method="post">
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
                <label class="block text-sm font-semibold mt-4 mb-2"><input class="checkbox checkbox-primary" type="checkbox" name="cancel" value="1"> Bestehende Buchung stornieren (Zeitfelder dann leer lassen)</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div><label class="block text-sm font-semibold mt-4 mb-2" for="start">Beginn mit Zeitzone</label><input class="input input-bordered w-full" id="start" name="start" placeholder="2026-10-25T09:00+01:00"></div>
                    <div><label class="block text-sm font-semibold mt-4 mb-2" for="end">Ende mit Zeitzone</label><input class="input input-bordered w-full" id="end" name="end" placeholder="2026-10-25T17:00+01:00"></div>
                    <div><label class="block text-sm font-semibold mt-4 mb-2" for="pause">Tatsächliche Pause (Minuten)</label><input class="input input-bordered w-full" id="pause" name="pause" type="number" min="0"></div>
                </div>
                <small class="block opacity-70 mt-3">Deutschland: Winterzeit +01:00, Sommerzeit +02:00. Abwesenheitsgutschriften beginnen um 00:00 Uhr Ortszeit; als Ende 00:00 plus Sollminuten und als Pause 0 eintragen. Arbeitsnachträge dürfen nicht in der Zukunft liegen. Bei Zeitumstellungen den Offset für jeden Zeitpunkt einzeln angeben.</small>
                <label class="block text-sm font-semibold mt-4 mb-2" for="reason">Begründung</label>
                <textarea class="textarea textarea-bordered w-full" id="reason" name="reason" minlength="5" maxlength="1000" required></textarea>
                <div class="flex flex-wrap gap-3 mt-5"><button class="btn btn-primary">Antrag stellen</button></div>
            </fieldset>
        </form>
    </section>

    <h2 class="text-xl font-semibold mb-4">Letzte Anträge</h2>
    @foreach ($corrections->requests as $a)
    <section class="card bg-base-100 shadow-xl p-6 mb-6">
        <p class="text-sm font-semibold opacity-70 mb-2">#{{ $a['id'] }} · {{ $a['username'] }} · {{ ['pending' => 'Offen', 'approved' => 'Freigegeben', 'rejected' => 'Abgelehnt'][$a['status']] }}</p>
        <p>{{ $a['cancel'] ? 'STORNIERUNG · ' : '' }}{{ $a['start'] }} – {{ $a['end'] }} · {{ $a['pause'] }} Minuten Pause</p>
        <p>{{ $a['reason'] }}</p>
        @if ($a['review_reason'])<p class="opacity-70">Entscheidung: {{ $a['review_reason'] }}</p>@endif
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
</main>
@endsection
