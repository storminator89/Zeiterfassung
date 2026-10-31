@php($apiGerman = $apiManagement->language === 'de')
<section id="api-management" class="card mb-4" aria-labelledby="api-management-title">
    <div class="card-header">
        <h2 id="api-management-title" class="card-title"><x-chrono-icon name="shield-lock" size="20" /> {{ $apiGerman ? 'API' : $apiManagement->text('title') }}</h2>
        <span class="badge {{ $apiManagement->policy->enabled ? 'bg-success-lt' : 'bg-secondary-lt' }}">{{ $apiManagement->text($apiManagement->policy->enabled ? 'enabled' : 'disabled') }}</span>
        <div class="ms-auto"><x-chrono-help label="API und Zugriff"><p>{{ $apiManagement->text('contract') }}</p><p>{{ $apiManagement->text('boundary') }}</p><p>{{ $apiManagement->text('key_secret') }}</p><p>{{ $apiManagement->text('key') }}: {{ $apiManagement->text(match($apiManagement->keyStatus) { 'missing'=>'key_missing', 'ready'=>'key_ready', 'operator'=>'key_operator', default=>'key_blocked' }) }}. {{ $apiManagement->text('revision') }}: {{ $apiManagement->policy->revision }}.</p></x-chrono-help></div>
    </div>
    <div class="card-body">
        @if ($apiManagement->notice)<p class="alert alert-success mb-3" role="status">{{ $apiManagement->text('notice_'.$apiManagement->notice) }}</p>@endif
        <p class="text-secondary mb-3">{{ $apiGerman ? 'Schlüssel: '.match($apiManagement->keyStatus) { 'missing'=>'nicht eingerichtet', 'ready'=>'vorbereitet', 'operator'=>'vorhanden', default=>'bitte prüfen' } : $apiManagement->text(match($apiManagement->keyStatus) { 'missing'=>'key_missing', 'ready'=>'key_ready', 'operator'=>'key_operator', default=>'key_blocked' }) }}</p>
        @if (!in_array($apiManagement->keyStatus, ['missing','ready','operator'], true))<p class="text-warning" role="status">{{ $apiGerman ? 'Schlüssel nicht verfügbar. Bitte die lokale Konfiguration prüfen.' : $apiManagement->text('key_blocked') }}</p>@endif
        @if (in_array($apiManagement->keyStatus, ['missing','ready','operator'], true))
        <details class="chrono-disclosure" @if (($adminPauseSettings->error ?? null) && request()->input('api_action') === 'enable') open @endif>
            <summary>{{ $apiGerman ? ($apiManagement->policy->enabled ? 'Aktivierung prüfen' : ($apiManagement->keyStatus === 'missing' ? 'API einrichten' : 'API aktivieren')) : $apiManagement->text('enable') }}</summary>
            <form method="post" action="admin.php?section=system" class="chrono-form-panel mt-3">
                @csrf
                <input type="hidden" name="api_management" value="1"><input type="hidden" name="api_action" value="enable"><input type="hidden" name="api_revision" value="{{ $apiManagement->policy->revision }}">
                <div class="mb-3"><label class="form-label" for="api-enable-password">{{ $apiManagement->text('password') }}</label><input id="api-enable-password" type="password" name="current_password" autocomplete="current-password" maxlength="4096" class="form-control" required></div>
                <label class="form-check d-flex align-items-start gap-2 mb-3"><input type="checkbox" name="api_confirm" value="1" class="form-check-input flex-shrink-0" required><span class="form-check-label">{{ $apiManagement->text('confirm_enable') }}</span></label>
                @if ($apiManagement->keyStatus === 'missing')<label class="form-check d-flex align-items-start gap-2 mb-3"><input type="checkbox" name="api_create_key" value="1" class="form-check-input flex-shrink-0" required><span class="form-check-label">{{ $apiManagement->text('confirm_key') }}</span></label>@endif
                <button class="btn btn-primary" type="submit">{{ $apiManagement->text($apiManagement->keyStatus === 'missing' ? 'enable_new' : 'enable') }}</button>
            </form>
        </details>
        @endif
        <details class="chrono-disclosure mt-3" @if (($adminPauseSettings->error ?? null) && request()->input('api_action') === 'disable') open @endif>
            <summary>{{ $apiGerman ? 'API deaktivieren' : $apiManagement->text('disable') }}</summary>
            <form method="post" action="admin.php?section=system" class="chrono-form-panel mt-3">
                @csrf
                <input type="hidden" name="api_management" value="1"><input type="hidden" name="api_action" value="disable"><input type="hidden" name="api_revision" value="{{ $apiManagement->policy->revision }}">
                <div class="mb-3"><label class="form-label" for="api-disable-password">{{ $apiManagement->text('password') }}</label><input id="api-disable-password" type="password" name="current_password" autocomplete="current-password" maxlength="4096" class="form-control" required></div>
                <label class="form-check d-flex align-items-start gap-2 mb-3"><input type="checkbox" name="api_confirm" value="1" class="form-check-input flex-shrink-0" required><span class="form-check-label">{{ $apiManagement->text('confirm_disable') }}</span></label>
                <button class="btn btn-outline-secondary" type="submit">{{ $apiManagement->text('disable') }}</button>
            </form>
        </details>
    </div>
</section>
