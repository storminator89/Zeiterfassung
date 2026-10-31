@php
    $mcp = $mcpManagement ?? null;
    $mcpUrl = rtrim(config('app.url'), '/').'/mcp';
    $mcpApiReady = ($apiManagement ?? null) !== null && $apiManagement->policy->enabled && in_array($apiManagement->keyStatus, ['ready','operator'], true);
@endphp
<section class="card chrono-mcp" id="mcp-management" aria-labelledby="mcp-title">
    <div class="chrono-mcp-heading">
        <div class="chrono-mcp-icon"><x-chrono-icon name="device-desktop" size="28" /></div>
        <div><h2 id="mcp-title">MCP-Server</h2><p>Ein Endpunkt. Jeder verbindet sein eigenes Konto.</p></div>
        <span class="badge {{ ($mcp['enabled'] ?? false) ? 'bg-green-lt' : 'bg-secondary-lt' }}">{{ ($mcp['enabled'] ?? false) ? 'Aktiviert' : 'Deaktiviert' }}</span>
    </div>
    @if ($mcpNotice ?? null)<p class="alert alert-success" role="status">{{ $mcpNotice }}</p>@endif
    <div class="chrono-mcp-endpoint"><label for="mcp-endpoint">MCP-URL</label><input class="form-control" id="mcp-endpoint" readonly value="{{ $mcpUrl }}" aria-label="MCP-URL"></div>
    <div class="chrono-mcp-facts"><div><span>Transport</span><strong>Streamable HTTP</strong></div><div><span>Anmeldung</span><strong>Persönlicher API-Key</strong></div><div><span>Zugriff</span><strong>Eigene Daten je Nutzer</strong></div></div>
    @if ($mcp === null)<p>Aktivierung durch die lokale Administration.</p>
    @elseif ($mcp['error'])<p class="alert alert-warning" role="alert">MCP-Einstellungen nicht verfügbar. Private Konfiguration prüfen.</p>
    @else
        @if (!$mcpApiReady)<p class="chrono-mcp-prerequisite"><x-chrono-icon name="info-circle" size="18" /><span>Für die Anmeldung muss die <a href="#api-management">API eingerichtet und aktiviert</a> sein.</span></p>@endif
        <details class="chrono-mcp-setup"><summary><x-chrono-icon name="settings" size="18" />{{ $mcp['enabled'] ? 'MCP verwalten' : 'MCP aktivieren' }}</summary>
            @if (!$mcp['available'])<p>Änderungen sind in einer privaten lokalen Installation verfügbar.</p>
            @else
            <form method="post" action="admin.php?section=system#mcp-management" class="chrono-mcp-form">
                @csrf<input type="hidden" name="mcp_management" value="1"><input type="hidden" name="mcp_revision" value="{{ $mcp['revision'] }}">
                <label class="chrono-mcp-check"><input type="checkbox" class="form-check-input" name="apps_enabled" value="1" @checked($mcp['apps_enabled'])><span>Interaktive Chrono-Ansicht im MCP-Client anbieten</span></label>
                <div class="chrono-mcp-confirm"><label for="mcp-password">Aktuelles Admin-Passwort</label><input id="mcp-password" type="password" name="current_password" class="form-control" autocomplete="current-password" required maxlength="4096"></div>
                <div class="chrono-mcp-actions"><button type="submit" name="mcp_action" value="{{ $mcp['enabled'] ? 'save' : 'enable' }}" class="btn btn-primary"><x-chrono-icon name="{{ $mcp['enabled'] ? 'check' : 'login' }}" size="18" />{{ $mcp['enabled'] ? 'Änderungen speichern' : 'Für alle Nutzer aktivieren' }}</button>@if ($mcp['enabled'])<button type="submit" name="mcp_action" value="disable" formnovalidate class="btn btn-outline-secondary">Deaktivieren</button>@endif</div>
                <small>Die Freigabe wird bei jedem Aufruf geprüft. Es gibt kein gemeinsam hinterlegtes Benutzerkonto.</small>
            </form>@endif
        </details>
    @endif
    <details class="chrono-mcp-setup"><summary><x-chrono-icon name="login" size="18" /> Im Assistenten verbinden</summary>
        <div class="chrono-mcp-client"><p>Im MCP-Client „Streamable HTTP“ auswählen, die MCP-URL eintragen und den eigenen API-Key als Bearer-Token hinterlegen.</p>
            <dl><div><dt>Authentifizierung</dt><dd><code>Authorization: Bearer &lt;DEIN_API_KEY&gt;</code></dd></div></dl>
            <small>Jeder Aufruf prüft den API-Key und den aktuellen Kontostand. Eine Browser-Anmeldung ersetzt den Key nicht. Kein OAuth-Login; der MCP-Client muss Bearer-Token unterstützen.</small>
        </div>
    </details>
    <details class="chrono-mcp-setup"><summary><x-chrono-icon name="list-details" size="18" /> Werkzeuge & Berechtigungen</summary>
        <ul class="chrono-mcp-tools"><li><x-chrono-icon name="user" size="20" /><span><strong>Eigenes Konto</strong>Identität und Anwesenheit</span></li><li><x-chrono-icon name="calendar" size="20" /><span><strong>Zeiten lesen</strong>Buchungen, Korrekturstatus und Arbeitszeitmodelle</span></li><li><x-chrono-icon name="clock" size="20" /><span><strong>Stempeln</strong>Kommen, Pause und Gehen mit Bestätigung</span></li><li><x-chrono-icon name="shield-lock" size="20" /><span><strong>Persönlicher Zugriff</strong>Keine fremden Konten oder Verwaltungsfunktionen</span></li></ul>
        <p class="chrono-mcp-toolnames"><code>chrono_identity</code><code>chrono_attendance</code><code>chrono_time_entries</code><code>chrono_corrections</code><code>chrono_work_model</code><code>chrono_work_models</code><code>chrono_start</code><code>chrono_pause_start</code><code>chrono_pause_end</code><code>chrono_end</code></p>
    </details>
</section>
