@extends('chrono.layouts.app')

@section('content')
<link rel="stylesheet" href="assets/css/terminal_ui.css?v={{ substr(hash_file('sha256', public_path('assets/css/terminal_ui.css')),0,12) }}">
@php
    $terminalSection = request()->query('section', 'devices');
    if (!is_string($terminalSection) || !in_array($terminalSection, ['devices', 'access', 'sites'], true)) {
        $terminalSection = 'devices';
    }
    $terminalCommand = request()->isMethod('post') ? request()->input('action') : null;
    $terminalSection = match ($terminalCommand) {
        'credential', 'revoke_credential' => 'access',
        'site' => 'sites',
        'device', 'revoke_device' => 'devices',
        default => $terminalSection,
    };
    $terminalSites = $terminalDevices->pairingSites;
@endphp
<main class="container mx-auto px-4 py-8 chrono-page chrono-terminals">
    <header class="chrono-page-intro chrono-terminal-intro">
        <h1 class="text-2xl font-bold"><x-chrono-icon name="device-desktop" size="24" /> Terminals</h1>
        <img src="assets/illustrations/system-access.png" alt="" width="88" height="76" class="chrono-page-illustration">
    </header>

    @if ($terminalDevices->error)<div class="alert alert-error mb-6" role="alert">{{ $terminalDevices->error }}</div>@endif
    @if ($terminalDevices->saved)<div class="alert alert-success mb-6" role="status"><x-chrono-icon name="check" size="20" /> Änderung gespeichert.</div>@endif
    @if ($terminalDevices->pairing)
    <section class="chrono-terminal-pairing" aria-label="Gerät koppeln">
        <div><strong>Gerät {{ $terminalDevices->pairing->deviceId }} koppeln</strong><p>Den Einmalcode am Gerätedienst eingeben. Er wird nur jetzt angezeigt.</p></div>
        <div class="chrono-terminal-code"><code>{{ $terminalDevices->pairing->takeCode() }}</code><span>Zehn Minuten gültig</span></div>
    </section>
    @endif

    @if ($terminalDevices->moduleSettingsEnabled)
    <section class="chrono-terminal-service" aria-label="Terminal-Betrieb">
        <div class="chrono-terminal-service-label">
            <span class="chrono-terminal-service-icon"><x-chrono-icon name="device-desktop" size="24" /></span>
            <div><h2>Terminal-Betrieb</h2><span class="chrono-terminal-service-state">{{ $terminalDevices->moduleEnabled ? (count($terminalDevices->devices) === 0 ? 'Modul aktiv, noch kein Gerät eingerichtet' : 'Modul aktiviert') : 'Modul deaktiviert' }}</span></div>
        </div>
        <form method="post" class="chrono-terminal-switch-form">
            @csrf
            <input class="input input-bordered w-full form-control" type="hidden" name="action" value="toggle">
            <label class="form-check form-switch chrono-terminal-switch" for="terminal-enabled">
                <input class="checkbox checkbox-primary form-check-input" type="checkbox" role="switch" id="terminal-enabled" name="enabled" @checked($terminalDevices->moduleEnabled)>
                <span>Terminal aktiv</span>
            </label>
            <button type="submit" class="btn btn-outline-secondary">Speichern</button>
        </form>
        @unless ($terminalDevices->moduleServerEnabled)
        <div class="chrono-terminal-server-note" role="status">Die Serverfreigabe fehlt. <x-chrono-help label="Terminal-Serverfreigabe"><p>Für den Terminal-Betrieb ist zusätzlich APP_TERMINAL_ENABLED=1 erforderlich.</p></x-chrono-help></div>
        @endunless
    </section>
    @endif

    <nav class="chrono-terminal-nav" aria-label="Terminal-Verwaltung">
        <a href="terminals.php?section=devices" @if ($terminalSection === 'devices') aria-current="page" @endif><x-chrono-icon name="device-desktop" size="20" /> Geräte</a>
        <a href="terminals.php?section=access" @if ($terminalSection === 'access') aria-current="page" @endif><x-chrono-icon name="users" size="20" /> Persönliche Zugänge</a>
        <a href="terminals.php?section=sites" @if ($terminalSection === 'sites') aria-current="page" @endif><x-chrono-icon name="building" size="20" /> Standorte</a>
    </nav>

    <section class="card chrono-terminal-panel" aria-labelledby="terminal-devices-title" @if ($terminalSection !== 'devices') hidden @endif>
        <header class="chrono-terminal-section-head">
            <h2 id="terminal-devices-title">Geräte <span class="chrono-terminal-count">{{ count($terminalDevices->devices) }}</span></h2>
            <x-chrono-help label="Freigabe und Gerätekontakt"><p>Freigabe und letzter Kontakt sind unabhängig. Kontakt gilt nach {{ $terminalDevices->contactMaxAge }} Sekunden als veraltet; aktueller Kontakt garantiert keine Erreichbarkeit. Stand beim Laden der Seite.</p>@if ($terminalDevices->deviceRevocationsEnabled)<p>Sperren beendet die Gerätefreigabe und verwirft eine offene Kopplung. Es werden keine bereits gespeicherten Buchungen geändert.</p>@endif</x-chrono-help>
        </header>
        @if ($terminalDevices->devicePairingsEnabled)
        <details class="chrono-terminal-create" id="terminal-device-form" @if ($terminalDevices->error && $terminalCommand === 'device') open @endif>
            <summary class="btn btn-primary"><x-chrono-icon name="plus" size="18" /> Gerät koppeln <x-chrono-icon name="chevron-down" size="16" class="chrono-terminal-create-chevron" /></summary>
            <div class="chrono-terminal-form-panel">
                <form method="post">
                    @csrf
                    <input class="input input-bordered w-full form-control" type="hidden" name="action" value="device">
                    <div class="chrono-terminal-fields">
                        <div><label class="form-label" for="device-name">Gerätename</label><input class="input input-bordered w-full form-control" id="device-name" name="name" maxlength="100" placeholder="Personaleingang" required></div>
                        <div><label class="form-label" for="device-site">Standort</label><select class="select select-bordered w-full form-select" id="device-site" name="site_id">@foreach ($terminalDevices->pairingSites as $site)<option value="{{ $site['id'] }}">{{ $site['name'] }}</option>@endforeach</select></div>
                    </div>
                    <div class="chrono-terminal-form-footer"><button type="submit" class="btn btn-primary">Einmalcode erzeugen</button><x-chrono-help label="Gerät sicher koppeln"><p>Kopplung im lokalen Gerätedienst, niemals durch Hinterlegen eines Admin-Passworts am Terminal.</p></x-chrono-help></div>
                </form>
            </div>
        </details>
        @endif
        @if ($terminalDevices->devices)
        <div class="table-responsive"><table class="table chrono-terminal-table">
            <thead><tr><th>Gerät</th><th>Freigabe</th><th>Kontakt</th><th>Letzter Kontakt</th>@if ($terminalDevices->deviceRevocationsEnabled)<th class="chrono-terminal-action-heading"><span class="visually-hidden">Aktionen</span></th>@endif</tr></thead>
            <tbody>
            @foreach ($terminalDevices->devices as $device)
                <tr>
                    <td data-label="Gerät"><div class="chrono-terminal-name"><x-chrono-icon name="device-desktop" size="20" /><div><strong title="Gerät #{{ $device['id'] }}">{{ $device['name'] }}</strong><span>{{ $device['site'] }}</span></div></div></td>
                    <td data-label="Freigabe"><span class="chrono-terminal-badge">{{ $device['permission_label'] }}</span></td>
                    <td data-label="Kontakt"><span class="chrono-terminal-badge {{ in_array($device['contact_state'], ['stale', 'invalid'], true) ? 'is-warning' : '' }}">{{ $device['contact_label'] }}</span></td>
                    <td data-label="Letzter Kontakt" class="chrono-terminal-last-seen">{{ \App\View\ExistingUi\HumanTime::full($device['last_seen_label']) }}</td>
                    @if ($terminalDevices->deviceRevocationsEnabled)
                    <td class="chrono-terminal-row-action"><form method="post">
                        @csrf
                        <input type="hidden" name="action" value="revoke_device"><input type="hidden" name="id" value="{{ $device['id'] }}">
                        <button type="submit" class="btn btn-outline-secondary btn-sm"><x-chrono-icon name="shield-lock" size="16" /> Sperren</button>
                    </form></td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table></div>
        @else
        <div class="chrono-terminal-empty"><x-chrono-icon name="device-desktop" size="28" /><p>Noch kein Gerät gekoppelt.</p></div>
        @endif
    </section>

    <section class="card chrono-terminal-panel" aria-labelledby="terminal-access-title" @if ($terminalSection !== 'access') hidden @endif>
        <header class="chrono-terminal-section-head">
            <h2 id="terminal-access-title">Persönliche Zugänge <span class="chrono-terminal-count">{{ count($terminalDevices->credentials) }}</span></h2>
            @if (! $terminalDevices->credentialRevocationsEnabled)<x-chrono-help label="Verfügbare Zugangsaktionen"><p>Nur lesende Übersicht der eingerichteten Zugänge. Zugang speichern / ersetzen und Zugang sperren sind hier nicht verbunden. PIN, Kartenwerte und Hashes werden nicht angezeigt.</p></x-chrono-help>@endif
        </header>
        @if ($terminalCredentialCreationEnabled ?? false)
        <details class="chrono-terminal-create" id="terminal-credential-form" @if ($terminalDevices->error && $terminalCommand === 'credential') open @endif>
            <summary class="btn btn-primary"><x-chrono-icon name="plus" size="18" /> Zugang einrichten <x-chrono-icon name="chevron-down" size="16" class="chrono-terminal-create-chevron" /></summary>
            <div class="chrono-terminal-form-panel">
                <form method="post" autocomplete="off">
                    @csrf
                    <input type="hidden" name="action" value="credential">
                    <div class="chrono-terminal-fields">
                        <div><label class="form-label" for="terminal-person">Person</label><select class="select select-bordered form-select" id="terminal-person" name="user_id" required>@foreach ($terminalCredentialUsers as $person)<option value="{{ $person['id'] }}">{{ $person['username'] }}</option>@endforeach</select></div>
                        <div><label class="form-label" for="terminal-credential-site">Standort</label><select class="select select-bordered form-select" id="terminal-credential-site" name="site_id" required>@foreach ($terminalCredentialSites as $site)<option value="{{ $site['id'] }}">{{ $site['name'] }}</option>@endforeach</select></div>
                        <div><label class="form-label" for="terminal-employee-code">Personalcode</label><input class="input input-bordered form-control" id="terminal-employee-code" name="employee_code" pattern="[a-zA-Z0-9_-]{2,32}" maxlength="32" required></div>
                        <div><label class="form-label" for="terminal-pin">PIN (8 Ziffern)</label><input class="input input-bordered form-control" id="terminal-pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{8}" autocomplete="new-password" required></div>
                        <div><label class="form-label" for="terminal-card">NFC-Kennung (optional)</label><input class="input input-bordered form-control" id="terminal-card" name="card" maxlength="128"></div>
                    </div>
                    <p class="chrono-terminal-replace-note">Speichern ersetzt einen bestehenden Terminal-Zugang einschließlich PIN und Karte.</p>
                    <div class="chrono-terminal-form-footer"><button type="submit" class="btn btn-primary" @disabled(! $terminalCredentialSites)>Zugang speichern / ersetzen</button></div>
                </form>
            </div>
        </details>
        @endif
        @if ($terminalDevices->credentials)
        <div class="table-responsive"><table class="table chrono-terminal-table">
            <thead><tr><th>Person</th><th>Standort</th><th>Zugang</th>@if ($terminalDevices->credentialRevocationsEnabled)<th class="chrono-terminal-action-heading"><span class="visually-hidden">Aktionen</span></th>@endif</tr></thead>
            <tbody>
            @foreach ($terminalDevices->credentials as $credential)
                <tr>
                    <td data-label="Person"><div class="chrono-terminal-name"><x-chrono-icon name="user" size="20" /><div><strong>{{ $credential['username'] }}</strong><span>Personalcode {{ $credential['employee_code'] }}</span></div></div></td>
                    <td data-label="Standort">{{ $credential['site'] }}</td>
                    <td data-label="Zugang"><span class="chrono-terminal-badge">{{ $credential['access_label'] }}</span></td>
                    @if ($terminalDevices->credentialRevocationsEnabled)
                    <td class="chrono-terminal-row-action"><form method="post">
                        @csrf
                        <input type="hidden" name="action" value="revoke_credential"><input type="hidden" name="user_id" value="{{ $credential['user_id'] }}">
                        <button type="submit" class="btn btn-outline-secondary btn-sm"><x-chrono-icon name="shield-lock" size="16" /> Zugang sperren</button>
                    </form></td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table></div>
        @else
        <div class="chrono-terminal-empty"><x-chrono-icon name="users" size="28" /><p>Keine sichtbaren Terminal-Zugänge vorhanden.</p></div>
        @endif
    </section>

    <section class="card chrono-terminal-panel" aria-labelledby="terminal-sites-title" @if ($terminalSection !== 'sites') hidden @endif>
        <header class="chrono-terminal-section-head"><h2 id="terminal-sites-title">Standorte</h2></header>
        <details class="chrono-terminal-create" id="terminal-site-form" @if ($terminalDevices->error && $terminalCommand === 'site') open @endif>
            <summary class="btn btn-primary"><x-chrono-icon name="plus" size="18" /> Standort anlegen <x-chrono-icon name="chevron-down" size="16" class="chrono-terminal-create-chevron" /></summary>
            <div class="chrono-terminal-form-panel">
                @if (! $terminalDevices->siteCreationEnabled)<p class="text-sm opacity-70 mb-4" role="status">Die Standortanlage ist in diesem Profil nicht freigegeben. Es wird kein Standort angelegt.</p>@endif
                <form method="post">
                    @csrf
                    <fieldset @disabled(! $terminalDevices->siteCreationEnabled)>
                        <input class="input input-bordered w-full form-control" type="hidden" name="action" value="site">
                        <div class="chrono-terminal-site-name"><label class="form-label" for="site-name">Name</label><input class="input input-bordered w-full form-control" id="site-name" name="name" maxlength="100" required></div>
                        <div class="chrono-terminal-form-footer"><button type="submit" class="btn btn-primary">Standort anlegen</button></div>
                    </fieldset>
                </form>
            </div>
        </details>
        @if ($terminalSites)
        <div class="chrono-terminal-sites">
            <p class="chrono-terminal-site-context">Für neue Geräte verfügbare Standorte</p>
            <ul>@foreach ($terminalSites as $site)<li><x-chrono-icon name="building" size="20" /><span>{{ $site['name'] }}</span></li>@endforeach</ul>
        </div>
        @else
        <div class="chrono-terminal-empty"><x-chrono-icon name="building" size="28" /><p>Keine Standorte für neue Geräte verfügbar.</p></div>
        @endif
    </section>
</main>
@endsection
