<section id="api-management" class="card bg-base-100 shadow-xl mb-8" aria-labelledby="api-management-title">
    <div class="card-body">
        <h2 id="api-management-title" class="card-title text-2xl mb-4">{{ $apiManagement->text('title') }}</h2>
        <x-chrono-help><p>{{ $apiManagement->text('contract') }}</p></x-chrono-help>
        @if ($apiManagement->notice)<p class="alert alert-success" role="status">{{ $apiManagement->text('notice_'.$apiManagement->notice) }}</p>@endif
        <dl class="mb-4">
            <dt>{{ $apiManagement->text('state') }}</dt><dd>{{ $apiManagement->text($apiManagement->policy->enabled ? 'enabled' : 'disabled') }}</dd>
            <dt>{{ $apiManagement->text('key') }}</dt><dd>{{ $apiManagement->text(match($apiManagement->keyStatus) { 'missing'=>'key_missing', 'ready'=>'key_ready', 'operator'=>'key_operator', default=>'key_blocked' }) }}</dd>
            <dt>{{ $apiManagement->text('revision') }}</dt><dd>{{ $apiManagement->policy->revision }}</dd>
        </dl>
        <x-chrono-help><p class="alert alert-info mb-4">{{ $apiManagement->text('boundary') }}</p>
<p class="text-sm opacity-70 mb-4">{{ $apiManagement->text('key_secret') }}</p></x-chrono-help>
        @if (in_array($apiManagement->keyStatus, ['missing','ready','operator'], true))
        <form method="post" action="admin.php?section=system" class="space-y-4 mb-6">
            @csrf
            <input type="hidden" name="api_management" value="1"><input type="hidden" name="api_action" value="enable"><input type="hidden" name="api_revision" value="{{ $apiManagement->policy->revision }}">
            <div class="form-control"><label for="api-enable-password">{{ $apiManagement->text('password') }}</label><input id="api-enable-password" type="password" name="current_password" autocomplete="current-password" maxlength="4096" class="input input-bordered" required></div>
            <label class="label"><input type="checkbox" name="api_confirm" value="1" class="checkbox" required> {{ $apiManagement->text('confirm_enable') }}</label>
            @if ($apiManagement->keyStatus === 'missing')<label class="label"><input type="checkbox" name="api_create_key" value="1" class="checkbox" required> {{ $apiManagement->text('confirm_key') }}</label>@endif
            <button class="btn btn-primary" type="submit">{{ $apiManagement->text($apiManagement->keyStatus === 'missing' ? 'enable_new' : 'enable') }}</button>
        </form>
        @endif
        <form method="post" action="admin.php?section=system" class="space-y-4">
            @csrf
            <input type="hidden" name="api_management" value="1"><input type="hidden" name="api_action" value="disable"><input type="hidden" name="api_revision" value="{{ $apiManagement->policy->revision }}">
            <div class="form-control"><label for="api-disable-password">{{ $apiManagement->text('password') }}</label><input id="api-disable-password" type="password" name="current_password" autocomplete="current-password" maxlength="4096" class="input input-bordered" required></div>
            <label class="label"><input type="checkbox" name="api_confirm" value="1" class="checkbox" required> {{ $apiManagement->text('confirm_disable') }}</label>
            <button class="btn btn-primary" type="submit">{{ $apiManagement->text('disable') }}</button>
        </form>
    </div>
</section>
