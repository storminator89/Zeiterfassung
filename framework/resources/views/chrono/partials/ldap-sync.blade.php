{{-- Mounted only after existing Admin/policy/profile checks; no new access path. --}}
@php($ldapGerman = $page->copy->language === 'de')
<div id="ldap-sync" class="card mb-4">
    <div class="card-header">
        <h2 class="card-title"><x-chrono-icon name="building" size="20" /> {{ $ldapGerman ? 'LDAP' : __('ldap-sync.title', [], $page->copy->language) }}</h2>
        @if ($ldapGerman)<span class="badge bg-secondary-lt">{{ $ldap->ldapSyncNotice === 'disabled' ? 'Deaktiviert' : ($ldap->ldapSyncNotice === 'unconfirmed' ? 'Bitte prüfen' : ($ldap->ldapSyncForm !== null && $page->cspNonce !== null ? 'Eingerichtet' : 'Nicht eingerichtet')) }}</span>@endif
        <div class="ms-auto"><x-chrono-help label="Verzeichnis und Zugang"><p>{{ __('ldap-sync.notice', [], $page->copy->language) }}</p><p>{{ __('ldap-sync.password_hint', [], $page->copy->language) }}</p></x-chrono-help></div>
    </div>
    <div class="card-body">
        @if ($ldap->ldapSyncOutcome !== null)
            <div class="alert {{ $ldap->ldapSyncOutcome->hasConflicts() ? 'alert-warning' : 'alert-success' }} mb-3" role="status">
                <div>
                    <p>{{ __($ldap->ldapSyncOutcome->hasConflicts() ? 'ldap-sync.conflicts' : 'ldap-sync.success', ['count' => $ldap->ldapSyncOutcome->conflictCount], $page->copy->language) }}</p>
                    @if ($ldap->ldapSyncOutcome->hasConflicts())
                        <ul class="mb-0">
                            @foreach ($ldap->ldapSyncOutcome->conflicts as $detail)<li>{{ __('ldap-sync.account', ['id' => $detail['id']], $page->copy->language) }}: {{ __('ldap-sync.reason_'.$detail['reason'], [], $page->copy->language) }}</li>@endforeach
                        </ul>
                        @if ($ldap->ldapSyncOutcome->conflictCount > count($ldap->ldapSyncOutcome->conflicts))<p>{{ __('ldap-sync.truncated', ['shown' => count($ldap->ldapSyncOutcome->conflicts), 'total' => $ldap->ldapSyncOutcome->conflictCount], $page->copy->language) }}</p>@endif
                    @endif
                </div>
            </div>
        @endif
        @if ($ldap->ldapSyncNotice !== null)
            <p class="text-secondary mb-3" role="note">{{ $ldapGerman ? match($ldap->ldapSyncNotice) { 'disabled'=>'Synchronisierung ist deaktiviert.', 'unconfirmed'=>'Ergebnis unklar. Bitte den gespeicherten Stand prüfen, bevor Sie erneut synchronisieren.', default=>'Verbindung noch nicht eingerichtet.' } : __('ldap-sync.'.(in_array($ldap->ldapSyncNotice, ['disabled', 'not_ready', 'unconfirmed'], true) ? $ldap->ldapSyncNotice : 'not_ready'), [], $page->copy->language) }}</p>
            <x-chrono-help><p>{{ __('ldap-sync.'.(in_array($ldap->ldapSyncNotice, ['disabled', 'not_ready', 'unconfirmed'], true) ? $ldap->ldapSyncNotice : 'not_ready'), [], $page->copy->language) }}</p></x-chrono-help>
        @endif
        @if ($ldap->ldapSyncForm !== null && $page->cspNonce !== null)
            <p class="text-secondary mb-3">{{ $ldap->ldapSyncForm->host }}:{{ $ldap->ldapSyncForm->port }}</p>
            <details class="chrono-disclosure" @if (($ldap->error ?? null) && request()->has('sync_ldap')) open @endif>
                <summary>{{ $ldapGerman ? 'Verzeichnis synchronisieren' : __('ldap-sync.submit', [], $page->copy->language) }}</summary>
                <form method="post" action="admin.php?section=access" autocomplete="off" class="chrono-form-panel mt-3">
                    @csrf
                    <input type="hidden" name="sync_ldap" value="1">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-8"><label class="form-label" for="ldap_host">{{ $ldapGerman ? 'Server' : __('ldap-sync.host', [], $page->copy->language) }}</label><input type="text" id="ldap_host" name="ldap_host" class="form-control" value="{{ $ldap->ldapSyncForm->host }}" readonly required></div>
                        <div class="col-sm-4"><label class="form-label" for="ldap_port">{{ $ldapGerman ? 'Port' : __('ldap-sync.port', [], $page->copy->language) }}</label><input type="number" id="ldap_port" name="ldap_port" class="form-control" value="{{ $ldap->ldapSyncForm->port }}" readonly required></div>
                    </div>
                    <div class="mb-3"><label class="form-label" for="ldap_base_dn">{{ $ldapGerman ? 'Suchbereich (Base-DN)' : __('ldap-sync.base_dn', [], $page->copy->language) }}</label><input type="text" id="ldap_base_dn" name="ldap_base_dn" class="form-control" value="{{ $ldap->ldapSyncForm->baseDn }}" readonly required></div>
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6"><label class="form-label" for="ldap_user">{{ $ldapGerman ? 'Benutzer (Bind-DN)' : __('ldap-sync.bind_user', [], $page->copy->language) }}</label><input type="text" id="ldap_user" name="ldap_user" class="form-control" autocomplete="off" required></div>
                        <div class="col-sm-6"><label class="form-label" for="ldap_pass">{{ $ldapGerman ? 'Passwort' : __('ldap-sync.bind_password', [], $page->copy->language) }}</label><input type="password" id="ldap_pass" name="ldap_pass" class="form-control" autocomplete="new-password" required></div>
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('ldap-sync.submit', [], $page->copy->language) }}</button>
                </form>
            </details>
        @endif
    </div>
</div>
