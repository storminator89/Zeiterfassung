{{-- Mounted only after fresh V4 Admin/policy/profile checks, in the nonce/CSP local document. --}}
<div id="ldap-sync" class="card bg-base-100 shadow-xl mb-8">
    <div class="card-body">
        <h2 class="card-title text-2xl mb-4">{{ __('ldap-sync.title', [], $page->copy->language) }}</h2>
        @if ($ldap->ldapSyncOutcome !== null)
            <div class="alert {{ $ldap->ldapSyncOutcome->hasConflicts() ? 'alert-warning' : 'alert-success' }} mb-4" role="status">
                <div>
                    <p>{{ __($ldap->ldapSyncOutcome->hasConflicts() ? 'ldap-sync.conflicts' : 'ldap-sync.success', ['count' => $ldap->ldapSyncOutcome->conflictCount], $page->copy->language) }}</p>
                    @if ($ldap->ldapSyncOutcome->hasConflicts())
                        <ul>
                            @foreach ($ldap->ldapSyncOutcome->conflicts as $detail)
                                <li>{{ __('ldap-sync.account', ['id' => $detail['id']], $page->copy->language) }}: {{ __('ldap-sync.reason_'.$detail['reason'], [], $page->copy->language) }}</li>
                            @endforeach
                        </ul>
                        @if ($ldap->ldapSyncOutcome->conflictCount > count($ldap->ldapSyncOutcome->conflicts))
                            <p>{{ __('ldap-sync.truncated', ['shown' => count($ldap->ldapSyncOutcome->conflicts), 'total' => $ldap->ldapSyncOutcome->conflictCount], $page->copy->language) }}</p>
                        @endif
                    @endif
                </div>
            </div>
        @endif
        @if ($ldap->ldapSyncNotice !== null)
            <p class="alert alert-info mb-4" role="note">{{ __('ldap-sync.'.(in_array($ldap->ldapSyncNotice, ['disabled', 'not_ready', 'unconfirmed'], true) ? $ldap->ldapSyncNotice : 'not_ready'), [], $page->copy->language) }}</p>
        @endif
        @if ($ldap->ldapSyncForm !== null && $page->cspNonce !== null)
            <p class="mb-4">{{ __('ldap-sync.notice', [], $page->copy->language) }}</p>
            <form method="post" action="admin.php?section=access" autocomplete="off" class="space-y-4">
                @csrf
                <input type="hidden" name="sync_ldap" value="1">
                <div class="form-control">
                    <label class="label" for="ldap_host"><span class="label-text">{{ __('ldap-sync.host', [], $page->copy->language) }}</span></label>
                    <input type="text" id="ldap_host" name="ldap_host" class="input input-bordered" value="{{ $ldap->ldapSyncForm->host }}" readonly required>
                </div>
                <div class="form-control">
                    <label class="label" for="ldap_port"><span class="label-text">{{ __('ldap-sync.port', [], $page->copy->language) }}</span></label>
                    <input type="number" id="ldap_port" name="ldap_port" class="input input-bordered" value="{{ $ldap->ldapSyncForm->port }}" readonly required>
                </div>
                <div class="form-control">
                    <label class="label" for="ldap_user"><span class="label-text">{{ __('ldap-sync.bind_user', [], $page->copy->language) }}</span></label>
                    <input type="text" id="ldap_user" name="ldap_user" class="input input-bordered" autocomplete="off" required>
                </div>
                <div class="form-control">
                    <label class="label" for="ldap_pass"><span class="label-text">{{ __('ldap-sync.bind_password', [], $page->copy->language) }}</span></label>
                    <input type="password" id="ldap_pass" name="ldap_pass" class="input input-bordered" autocomplete="new-password" required>
                </div>
                <div class="form-control">
                    <label class="label" for="ldap_base_dn"><span class="label-text">{{ __('ldap-sync.base_dn', [], $page->copy->language) }}</span></label>
                    <input type="text" id="ldap_base_dn" name="ldap_base_dn" class="input input-bordered" value="{{ $ldap->ldapSyncForm->baseDn }}" readonly required>
                </div>
                <p class="text-sm opacity-70">{{ __('ldap-sync.password_hint', [], $page->copy->language) }}</p>
                <button type="submit" class="btn btn-primary"><i class="fas fa-sync" aria-hidden="true"></i>{{ __('ldap-sync.submit', [], $page->copy->language) }}</button>
            </form>
        @endif
    </div>
</div>
