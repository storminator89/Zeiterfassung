@extends('chrono.layouts.app')

@section('content')
<main class="container mx-auto px-4 py-8 chrono-page">
    <h1 class="text-2xl font-bold mb-6">Änderungshistorie</h1>
    <p>Erfassung, Korrekturantrag, Entscheidung und Arbeitszeitmodell werden mit Zeitpunkt, Akteur und Vorher-/Nachher-Werten protokolliert. Baseline-Einträge dokumentieren den Stand beim Upgrade; frühere Änderungen sind nicht rekonstruierbar.</p>
    <section class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Historie filtern</h2>
        <form method="get" action="history.php">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div><label class="block text-sm font-semibold mt-4 mb-2" for="history_user">Person-ID (optional)</label><input class="input input-bordered w-full" type="number" min="1" step="1" id="history_user" name="user_id" value="{{ $history->values['user_id'] }}"></div>
                <div><label class="block text-sm font-semibold mt-4 mb-2" for="history_from">Änderung von (optional)</label><input class="input input-bordered w-full" type="date" id="history_from" name="from" value="{{ $history->values['from'] }}"></div>
                <div><label class="block text-sm font-semibold mt-4 mb-2" for="history_to">Änderung bis einschließlich (optional)</label><input class="input input-bordered w-full" type="date" id="history_to" name="to" value="{{ $history->values['to'] }}"></div>
            </div>
            <p class="text-sm opacity-70 mt-3">Der Zeitraum gilt für den Zeitpunkt der protokollierten Änderung in Europe/Berlin, nicht für den Buchungstag. Alle Filter gelten gemeinsam und nur innerhalb Ihrer bisherigen Sichtrechte.</p>
            <details class="mt-4" @if ($history->values['entity'] !== '' || $history->values['record_id'] !== '' || $history->values['request_id'] !== '') open @endif><summary>Datensatz oder Antrag eingrenzen</summary>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div><label class="block text-sm font-semibold mt-4 mb-2" for="history_entity">Bereich (optional)</label><select class="select select-bordered w-full" id="history_entity" name="entity"><option value="">Alle Bereiche</option>@foreach ($history->entities as $entity => $label)<option value="{{ $entity }}" @if ($history->values['entity'] === $entity) selected @endif>{{ $label }}</option>@endforeach</select></div>
                    <div><label class="block text-sm font-semibold mt-4 mb-2" for="history_record">Datensatz-ID im Bereich (optional)</label><input class="input input-bordered w-full" type="number" min="1" step="1" id="history_record" name="record_id" value="{{ $history->values['record_id'] }}"></div>
                    <div><label class="block text-sm font-semibold mt-4 mb-2" for="history_request">Korrekturantrags-ID (optional)</label><input class="input input-bordered w-full" type="number" min="1" step="1" id="history_request" name="request_id" value="{{ $history->values['request_id'] }}"></div>
                </div>
                <p class="text-sm opacity-70 mt-3">Datensatz-IDs können in mehreren Bereichen vorkommen. Die Korrekturantrags-ID zeigt nur Änderungen am Antrag selbst, nicht automatisch die zugehörige Buchung oder Sitzung.</p>
            </details>
            <div class="flex flex-wrap gap-3 mt-5"><button class="btn btn-primary">Filtern</button><a class="btn btn-outline" href="history.php">Filter zurücksetzen</a></div>
        </form>
    </section>
    @if ($history->errors)
    <div class="alert alert-error mb-6" role="alert"><ul>@foreach ($history->errors as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @elseif (! $history->entries)
    <p class="mb-6" role="status">Keine sichtbaren Einträge für diese Filter.</p>
    @endif
    @if ($history->newestUrl !== null)<p class="mb-6"><a href="{{ $history->newestUrl }}">Neueste Treffer mit diesen Filtern</a></p>@endif
    @if ($history->exportUrl !== null)<p class="mb-6"><a class="btn btn-outline" href="{{ $history->exportUrl }}">Aktuelle Treffer dieser Filterseite als ausgewähltes JSON (max. 100)</a></p>@endif
    @foreach ($history->entries as $r)
    <section class="card bg-base-100 shadow-xl p-6 mb-6">
        <p class="text-sm font-semibold opacity-70 mb-2">#{{ $r['id'] }} · {{ $r['occurred_at'] }}</p>
        <h2 class="text-xl font-semibold mb-4">{{ $r['entity'] }} #{{ $r['record_id'] }} · {{ $r['operation'] }}</h2>
        <p>{{ $r['actor'] }} · {{ $r['reason'] }}</p>
        <details><summary>Vorher und nachher ansehen</summary>
            <p class="text-sm opacity-70 my-3">Feldvergleich und JSON zeigen nur ausgewählte freigegebene Felder, nicht den vollständigen Auditinhalt.</p>
            @if ($r['presentation']['limited'])<p class="text-sm opacity-70 my-3">Weitere, nicht erkannte oder nicht darstellbare Inhalte wurden ausgelassen; lange Texte können gekürzt sein.</p>@endif
            @if ($r['presentation']['rows'])
            <div class="overflow-x-auto"><table class="table w-full"><thead><tr><th scope="col">Feld</th><th scope="col">Vorher</th><th scope="col">Nachher</th></tr></thead><tbody>
                @foreach ($r['presentation']['rows'] as $change)
                <tr><th scope="row">{{ $change['field'] }}</th><td style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $change['before'] }}</td><td style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $change['after'] }}</td></tr>
                @endforeach
            </tbody></table></div>
            @else
            <p class="my-3">{{ $r['presentation']['available'] ? 'Keine Änderungen an darstellbaren Feldern.' : 'Feldvergleich nicht verfügbar.' }}</p>
            @endif
            <h3 class="font-semibold mt-4">Vorher · ausgewählte Felder</h3><pre style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $r['presentation']['before'] }}</pre>
            <h3 class="font-semibold mt-4">Nachher · ausgewählte Felder</h3><pre style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $r['presentation']['after'] }}</pre>
        </details>
    </section>
    @endforeach
    @if ($history->olderUrl !== null)<a href="{{ $history->olderUrl }}">Ältere Einträge</a>@endif
</main>
@endsection
