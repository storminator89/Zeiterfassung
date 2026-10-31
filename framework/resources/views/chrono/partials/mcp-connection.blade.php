@php($mcpConnection = app(\App\Mcp\ManagedProfile::class)->status())
<section class="card chrono-mcp" aria-labelledby="own-mcp-title">
    <div class="chrono-mcp-heading"><div class="chrono-mcp-icon"><x-chrono-icon name="device-desktop" size="24" /></div><div><h2 id="own-mcp-title">KI-Assistent verbinden</h2><p>Deine Zeiten direkt über MCP nutzen.</p></div><span class="badge bg-secondary-lt">{{ $mcpConnection['enabled'] ? 'Verfügbar' : 'Noch nicht aktiviert' }}</span></div>
    <label for="own-mcp-endpoint">MCP-URL</label><input id="own-mcp-endpoint" class="form-control" readonly value="{{ rtrim(config('app.url'), '/') }}/mcp">
    <p class="mt-3 mb-0">Streamable HTTP mit deinem persönlichen API-Key</p>
    <details class="chrono-mcp-setup mt-3"><summary>Verbindungsdetails</summary><div class="chrono-mcp-client"><p>Im MCP-Client die URL hinzufügen und deinen bestehenden persönlichen API-Key als Bearer-Token eintragen.</p><code>Authorization: Bearer &lt;DEIN_API_KEY&gt;</code><small>Du siehst ausschließlich deine eigenen Daten. Kommen, Pause und Gehen benötigen eine Bestätigung. Der Client muss Bearer-Token unterstützen.</small>@if (!$mcpConnection['enabled'])<small>Die Verwaltung muss MCP und die API zuerst freigeben.</small>@endif</div></details>
</section>
