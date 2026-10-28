@extends('chrono.layouts.app')

@section('content')
<main class="container mx-auto px-4 py-8 chrono-page">
    <h1 class="text-2xl font-bold mb-6">Praxis-Terminals</h1>
    @if ($terminalDevices->moduleSettingsEnabled)
    <div class="alert alert-info mb-6" role="note">Optionaler Terminal-Betrieb und Geräteübersicht mit Standort und Kontaktstatus. Koppeln und das Speichern persönlicher Terminal-Zugänge sind in dieser Laravel-Ansicht nicht verbunden. Standortanlage, Geräte- und Zugangssperre sind nur bei gesonderter Freigabe verfügbar.</div>
    @elseif ($terminalDevices->deviceRevocationsEnabled)
    <div class="alert alert-info mb-6" role="note">Geräteübersicht mit Standort und Kontaktstatus. Koppeln, Modulfreigabe und das Speichern persönlicher Terminal-Zugänge sind in dieser Laravel-Ansicht nicht verbunden. Standortanlage, Geräte- und Zugangssperre sind nur bei gesonderter Freigabe verfügbar.</div>
    @else
    <div class="alert alert-info mb-6" role="note">Nur lesende Geräteübersicht mit Standort und Kontaktstatus. Koppeln, Sperren, Modulfreigabe und das Speichern persönlicher Terminal-Zugänge sind in dieser Laravel-Ansicht nicht verbunden. Standortanlage und Zugangssperre sind nur bei gesonderter Freigabe verfügbar.</div>
    @endif
    @if ($terminalDevices->error)<div class="alert alert-error mb-6" role="alert">{{ $terminalDevices->error }}</div>@endif
    @if ($terminalDevices->saved)<div class="alert alert-success mb-6">Änderung gespeichert.</div>@endif
    @if ($terminalDevices->moduleSettingsEnabled)
    <div class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Optionaler Terminal-Betrieb</h2>
        <p>Bei Aktivierung verwenden Web und Terminal dieselben Buchungen. Pausen werden tatsächlich erfasst; es erfolgt kein automatischer Mindestpausenabzug. Nachträgliche Änderungen laufen über Korrekturanträge.</p>
        <p>Serverfreigabe: <strong>{{ $terminalDevices->moduleServerEnabled ? 'vorhanden' : 'aus – APP_TERMINAL_ENABLED=1 erforderlich' }}</strong></p>
        <form method="post">
            @csrf
            <input class="input input-bordered w-full" type="hidden" name="action" value="toggle">
            <label class="block text-sm font-semibold mt-4 mb-2"><input class="checkbox checkbox-primary" type="checkbox" name="enabled" @checked($terminalDevices->moduleEnabled)> Modul aktivieren</label>
            <button class="btn btn-primary">Speichern</button>
        </form>
    </div>
    @endif
    <section class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Geräte</h2>
        <p class="opacity-70 mb-4">Freigabe und letzter Kontakt sind unabhängig. Kontakt gilt nach {{ $terminalDevices->contactMaxAge }} Sekunden als veraltet; aktueller Kontakt garantiert keine Erreichbarkeit. Stand beim Laden der Seite.</p>
        @if ($terminalDevices->deviceRevocationsEnabled)<p class="opacity-70 mb-4">Sperren beendet die Gerätefreigabe und verwirft eine offene Kopplung. Es werden keine bereits gespeicherten Buchungen geändert.</p>@endif
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Gerät</th><th>Standort</th><th>Freigabe</th><th>Kontaktstatus</th><th>Letzter Kontakt</th>@if ($terminalDevices->deviceRevocationsEnabled)<th></th>@endif</tr></thead>
                <tbody>
                @foreach ($terminalDevices->devices as $device)
                    <tr>
                        <td>{{ $device['name'] }} #{{ $device['id'] }}</td>
                        <td>{{ $device['site'] }}</td>
                        <td>{{ $device['permission_label'] }}</td>
                        <td><span class="{{ in_array($device['contact_state'], ['stale', 'invalid'], true) ? 'text-warning font-semibold' : '' }}">{{ $device['contact_label'] }}</span></td>
                        <td>{{ $device['last_seen_label'] }}</td>
                        @if ($terminalDevices->deviceRevocationsEnabled)
                        <td><form method="post">
                            @csrf
                            <input type="hidden" name="action" value="revoke_device">
                            <input type="hidden" name="id" value="{{ $device['id'] }}">
                            <button class="btn btn-error">Sperren</button>
                        </form></td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
    <section class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Standort ergänzen</h2>
        @if (! $terminalDevices->siteCreationEnabled)<p class="opacity-70 mb-4">Die Standortanlage ist in diesem Profil nicht freigegeben. Es wird kein Standort angelegt.</p>@endif
        <form method="post">
            @csrf
            <fieldset @disabled(! $terminalDevices->siteCreationEnabled)>
                <input class="input input-bordered w-full" type="hidden" name="action" value="site">
                <label class="block text-sm font-semibold mt-4 mb-2" for="site-name">Name</label>
                <input class="input input-bordered w-full" id="site-name" name="name" maxlength="100" required>
                <div class="flex flex-wrap gap-3 mt-5"><button class="btn btn-primary">Standort anlegen</button></div>
            </fieldset>
        </form>
    </section>
    <section class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Terminal-Zugänge</h2>
        @if ($terminalDevices->credentialRevocationsEnabled)
        <p class="opacity-70 mb-4">Vorhandene Zugänge können hier gesperrt werden. Zugang speichern / ersetzen ist nicht verbunden. PIN, Kartenwerte und Hashes werden nicht angezeigt.</p>
        @else
        <p class="opacity-70 mb-4">Nur lesende Übersicht der eingerichteten Zugänge. Zugang speichern / ersetzen und Zugang sperren sind hier nicht verbunden. PIN, Kartenwerte und Hashes werden nicht angezeigt.</p>
        @endif
        @if (! $terminalDevices->credentials)<p class="mb-4" role="status">Keine sichtbaren Terminal-Zugänge vorhanden.</p>@endif
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Person</th><th>Personalcode</th><th>Standort</th><th>Zugang</th>@if ($terminalDevices->credentialRevocationsEnabled)<th></th>@endif</tr></thead>
                <tbody>
                @foreach ($terminalDevices->credentials as $credential)
                    <tr><td>{{ $credential['username'] }}</td><td>{{ $credential['employee_code'] }}</td><td>{{ $credential['site'] }}</td><td>{{ $credential['access_label'] }}</td>
                        @if ($terminalDevices->credentialRevocationsEnabled)
                        <td><form method="post">
                            @csrf
                            <input type="hidden" name="action" value="revoke_credential">
                            <input type="hidden" name="user_id" value="{{ $credential['user_id'] }}">
                            <button class="btn btn-error">Zugang sperren</button>
                        </form></td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
