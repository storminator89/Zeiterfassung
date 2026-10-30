@extends('chrono.layouts.app')

@section('content')
<main class="container mx-auto px-4 py-8 chrono-page">
    <h1 class="text-2xl font-bold mb-6">Praxis-Terminals</h1>
    @if ($terminalDevices->error)<div class="alert alert-error mb-6" role="alert">{{ $terminalDevices->error }}</div>@endif
    @if ($terminalDevices->saved)<div class="alert alert-success mb-6">Änderung gespeichert.</div>@endif
    @if ($terminalDevices->pairing)
    <div class="alert alert-success mb-6"><strong>Gerät {{ $terminalDevices->pairing->deviceId }} koppeln · zehn Minuten gültig</strong><p>Den folgenden Einmalcode am Gerätedienst eingeben. Er wird nur jetzt angezeigt.</p><code>{{ $terminalDevices->pairing->takeCode() }}</code></div>
    @endif
    @if ($terminalDevices->moduleSettingsEnabled)
    <div class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Terminal-Betrieb</h2>
        <p>Arbeitszeiten über gekoppelte Praxisgeräte erfassen.</p>
        <p>Serverfreigabe: <strong>{{ $terminalDevices->moduleServerEnabled ? 'vorhanden' : 'aus – APP_TERMINAL_ENABLED=1 erforderlich' }}</strong></p>
        <form method="post">
            @csrf
            <input class="input input-bordered w-full" type="hidden" name="action" value="toggle">
            <label class="flex items-center gap-3 text-sm font-semibold mt-4 mb-2"><input class="checkbox checkbox-primary" type="checkbox" name="enabled" @checked($terminalDevices->moduleEnabled)> <span>Modul aktivieren</span></label>
            <button class="btn btn-primary">Speichern</button>
        </form>
    </div>
    @endif
    @if ($terminalDevices->devicePairingsEnabled)
    <section class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Neues Gerät</h2>
        <form method="post">
            @csrf
            <input class="input input-bordered w-full" type="hidden" name="action" value="device">
            <label class="block text-sm font-semibold mt-4 mb-2" for="device-name">Gerätename</label>
            <input class="input input-bordered w-full" id="device-name" name="name" maxlength="100" placeholder="Personaleingang" required>
            <label class="block text-sm font-semibold mt-4 mb-2" for="device-site">Standort</label>
            <select class="select select-bordered w-full" id="device-site" name="site_id">@foreach ($terminalDevices->pairingSites as $site)<option value="{{ $site['id'] }}">{{ $site['name'] }}</option>@endforeach</select>
            <div class="flex flex-wrap gap-3 mt-5"><button class="btn btn-primary">Einmalcode erzeugen</button></div>
        </form>
        <p class="opacity-70">Kopplung im lokalen Gerätedienst, niemals durch Hinterlegen eines Admin-Passworts am Terminal.</p>
    </section>
    @endif
    <section class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Geräte</h2>
        <x-chrono-help><p class="opacity-70 mb-4">Freigabe und letzter Kontakt sind unabhängig. Kontakt gilt nach {{ $terminalDevices->contactMaxAge }} Sekunden als veraltet; aktueller Kontakt garantiert keine Erreichbarkeit. Stand beim Laden der Seite.</p></x-chrono-help>
        @if ($terminalDevices->deviceRevocationsEnabled)<x-chrono-help><p class="opacity-70 mb-4">Sperren beendet die Gerätefreigabe und verwirft eine offene Kopplung. Es werden keine bereits gespeicherten Buchungen geändert.</p></x-chrono-help>@endif
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Gerät</th><th>Standort</th><th>Freigabe</th><th>Kontaktstatus</th><th>Letzter Kontakt</th>@if ($terminalDevices->deviceRevocationsEnabled)<th></th>@endif</tr></thead>
                <tbody>
                @forelse ($terminalDevices->devices as $device)
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
                @empty
                    <tr><td colspan="6" class="opacity-70">Noch keine Geräte gekoppelt. Lege oben ein Gerät an, um einen Einmalcode zu erzeugen.</td></tr>
                @endforelse
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
    @if ($terminalCredentialCreationEnabled ?? false)
    <section class="card bg-base-100 p-6 mb-6">
      <h2 class="text-xl font-semibold mb-4">Persönlicher Terminal-Zugang</h2>
      <form method="post" autocomplete="off">@csrf
        <input type="hidden" name="action" value="credential">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <label class="form-control">Person<select class="select select-bordered" name="user_id" required>@foreach($terminalCredentialUsers as $person)<option value="{{ $person['id'] }}">{{ $person['username'] }}</option>@endforeach</select></label>
          <label class="form-control">Standort<select class="select select-bordered" name="site_id" required>@foreach($terminalCredentialSites as $site)<option value="{{ $site['id'] }}">{{ $site['name'] }}</option>@endforeach</select></label>
          <label class="form-control">Personalcode<input class="input input-bordered" name="employee_code" pattern="[a-zA-Z0-9_-]{2,32}" maxlength="32" required></label>
          <label class="form-control">Neue PIN · 8 Ziffern<input class="input input-bordered" name="pin" type="password" inputmode="numeric" pattern="[0-9]{8}" autocomplete="new-password" required></label>
          <label class="form-control">NFC-Kennung (optional)<input class="input input-bordered" name="card" maxlength="128"></label>
        </div>
        <p class="text-sm opacity-70 mt-3">Speichern ersetzt einen bestehenden Terminal-Zugang einschließlich PIN und Karte.</p>
        <button class="btn btn-primary mt-4" @disabled(!$terminalCredentialSites)>Zugang speichern / ersetzen</button>
      </form>
    </section>
    @endif
    <section class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Terminal-Zugänge</h2>
        @if ($terminalDevices->credentialRevocationsEnabled)
        <p class="opacity-70 mb-4">Vorhandene Terminal-Zugänge verwalten. PIN und Kartenkennung werden nicht angezeigt.</p>
        @else
        <x-chrono-help><p class="opacity-70 mb-4">Nur lesende Übersicht der eingerichteten Zugänge. Zugang speichern / ersetzen und Zugang sperren sind hier nicht verbunden. PIN, Kartenwerte und Hashes werden nicht angezeigt.</p></x-chrono-help>
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
