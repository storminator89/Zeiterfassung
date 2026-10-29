<div class="mt-2">
    <span class="badge">{{ ['active' => 'Aktiv', 'inactive' => 'Deaktiviert', 'departed' => 'Ausgetreten'][$lifecycleUser['status']] }}</span>
    @foreach ($lifecycleUser['confirmations'] as $nextStatus => $confirmation)
    <button type="button" class="btn btn-ghost btn-sm" data-account-lifecycle data-user-id="{{ $lifecycleId }}" data-username="{{ $lifecycleUser['username'] }}" data-status="{{ $lifecycleUser['status'] }}" data-next-status="{{ $nextStatus }}" data-confirmation="{{ $confirmation }}">{{ ['active' => 'Aktivieren', 'inactive' => 'Deaktivieren', 'departed' => 'Austritt erfassen'][$nextStatus] }}</button>
    @endforeach
</div>
