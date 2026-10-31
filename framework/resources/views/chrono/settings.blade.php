@extends('chrono.layouts.app')

@section('content')
{{-- Native Settings UI; only ordinary session preferences are connected. --}}

<main class="container mx-auto px-4 py-8 chrono-settings chrono-form-panel">
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h1 class="card-title mb-4"><x-chrono-icon name="user" size="24" /> {{ $page->copy->text('SETTINGS_TITLE') }}</h1>

            @if ($settings->error)
                <div class="alert alert-error shadow-lg mb-4">
                    <div>
                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>{{ $settings->error }}</span>
                    </div>
                </div>
            @endif

            <form method="post" class="space-y-4">
@csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="chrono-field">
                        <label class="label" for="lang">
                            <span class="label-text">{{ $page->copy->text('SETTINGS_LANGUAGE') }}</span>
                        </label>
                        <select name="lang" id="lang" class="select select-bordered w-full form-select">
                            <option value="de" @selected($page->copy->language === 'de')>Deutsch</option>
                            <option value="en" @selected($page->copy->language === 'en')>English</option>
                            <option value="zh" @selected($page->copy->language === 'zh')>中文</option>
                        </select>
                    </div>

                    <div class="chrono-field">
                        <label class="label" for="regelarbeitszeit">
                            <span class="label-text">{{ $page->copy->text('SETTINGS_REGULAR_WORKING_HOURS') }}</span>
                        </label>
                        <input type="number" step="0.1" id="regelarbeitszeit" disabled name="regelarbeitszeit" value="{{ $settings->regularHours }}" min="0" max="24" class="input input-bordered w-full form-control"><a class="link text-sm mt-2" href="work_models.php">Arbeitszeitmodell ansehen</a>
                    </div>

                    @if ($page->role === 'admin')
                        <div class="chrono-field">
                            <label class="label" for="ueberstunden">
                                <span class="label-text">Übertrag (Stunden)</span>
                            </label>
                            <input type="number" step="any" id="ueberstunden" name="ueberstunden" value="{{ $settings->carryInHours }}" class="input input-bordered w-full form-control" aria-describedby="carry-in-contract">
                            <x-chrono-help><p id="carry-in-contract" class="text-sm opacity-70 mt-2">Nur Ihr eigener früherer Überstunden-Übertrag. Positive und negative endliche Zahlen mit Dezimalpunkt und optionalem Exponent sind zulässig; keine Leerzeichen oder Dezimalkommas. Ungültige, zu kleine oder nicht darstellbare Werte werden abgelehnt, nicht gerundet. Das vorhandene REAL-Datenformat bleibt eine binäre Näherung; die bestehende Historie ist kein verlustfreies Dezimaljournal. Bei einem Fehler kann die zuvor gewählte Sprache bereits übernommen sein.</p></x-chrono-help>
                        </div>
                    @endif

                    <div class="chrono-field">
                        <label class="label" for="theme_mode">
                            <span class="label-text">{{ $page->copy->text('NAV_DARK_MODE') }}</span>
                        </label>
                        <select name="theme_mode" id="theme_mode" class="select select-bordered w-full form-select">
                            <option value="light" @selected($settings->themeMode === 'light')>{{ $page->copy->text('SETTINGS_THEME_LIGHT') }}</option>
                            <option value="dark" @selected($settings->themeMode === 'dark')>{{ $page->copy->text('SETTINGS_THEME_DARK') }}</option>
                            <option value="system" @selected($settings->themeMode === 'system')>{{ $page->copy->text('SETTINGS_THEME_SYSTEM') }}</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit" class="btn btn-primary">{{ $page->copy->text('BUTTON_SAVE_CHANGES') }}</button>
                </div>
            </form>

            <details class="chrono-disclosure mt-4"><summary><x-chrono-icon name="user" size="20" /> Mein Profil und Arbeitszeitmodell</summary>
                @include('chrono.partials.settings-profile')
            </details>

            @if ($settings->passwordAvailable)
                <details class="chrono-disclosure mt-4" @if ($settings->error && (request()->has('password_action') || request()->has('session_action'))) open @endif>
                    <summary><x-chrono-icon name="shield-lock" size="20" /> Sicherheit</summary>
                    <h2 id="own-password-title" class="h3 mt-4 mb-3">Passwort ändern</h2>
                    <section aria-labelledby="own-password-title" class="mt-4">
                    <x-chrono-help><p id="own-password-contract" class="text-sm opacity-70 mb-4">{{ $settings->passwordText('contract') }}</p></x-chrono-help>
                    <form method="post" action="settings.php" class="space-y-4" aria-describedby="own-password-contract">
                        @csrf
                        <input type="hidden" name="password_action" value="change">
                        <div class="chrono-field"><label class="label" for="current_password">{{ $settings->passwordText('current') }}</label><input class="input input-bordered w-full form-control" type="password" id="current_password" name="current_password" autocomplete="current-password" maxlength="4096" required></div>
                        <div class="chrono-field"><label class="label" for="own_new_password">{{ $settings->passwordText('new') }}</label><input class="input input-bordered w-full form-control" type="password" id="own_new_password" name="password" autocomplete="new-password" maxlength="72" required></div>
                        <div class="chrono-field"><label class="label" for="own_password_confirmation">{{ $settings->passwordText('confirm') }}</label><input class="input input-bordered w-full form-control" type="password" id="own_password_confirmation" name="password_confirmation" autocomplete="new-password" maxlength="72" required></div>
                        <button type="submit" class="btn btn-primary">{{ $settings->passwordText('submit') }}</button>
                    </form>
                    </section>
                    <h2 id="own-sessions-title" class="h3 mt-4 mb-3">Alle Anmeldungen beenden</h2>
                    <section aria-labelledby="own-sessions-title" class="mt-4">
                    <x-chrono-help><p id="own-sessions-contract" class="text-sm opacity-70 mb-4">{{ $settings->passwordText('sessions_contract') }}</p></x-chrono-help>
                    <form method="post" action="settings.php" class="space-y-4" aria-describedby="own-sessions-contract">
                        @csrf
                        <input type="hidden" name="session_action" value="revoke_web_api">
                        <div class="chrono-field"><label class="label" for="sessions_current_password">{{ $settings->passwordText('current') }}</label><input class="input input-bordered w-full form-control" type="password" id="sessions_current_password" name="current_password" autocomplete="current-password" maxlength="72" required></div>
                        <label class="flex items-start gap-3"><input type="checkbox" name="session_confirmation" value="1" class="checkbox checkbox-primary form-check-input" required><span>{{ $settings->passwordText('sessions_confirm') }}</span></label>
                        <button type="submit" class="btn btn-outline-secondary">{{ $settings->passwordText('sessions_submit') }}</button>
                    </form>
                    </section>
                </details>
            @endif

            @if ($page->role === 'admin')
                <details class="chrono-disclosure mt-4"><summary><x-chrono-icon name="shield-lock" size="20" /> Sicherung und Wiederherstellung</summary>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <section class="mt-4">
                        <div>
                            <h3 class="card-title">{{ $page->copy->text('SETTINGS_BACKUP_RESTORE_TITLE') }}</h3>
                            <p>{{ $page->copy->text('SETTINGS_BACKUP_IMPORT_DISABLED') }}</p>
                            <x-chrono-help><p>{{ $page->copy->text('SETTINGS_BACKUP_RESTORE_INSTRUCTIONS') }}</p>
<p>{{ $page->copy->text('SETTINGS_BACKUP_RESTORE_GUIDE') }} <code>docs/TERMINAL.md</code></p></x-chrono-help>
                        </div>
                    </section>
                    <section class="mt-4">
                        <div>
                            <h3 class="card-title">{{ $page->copy->text('SETTINGS_BACKUP_SNAPSHOT_TITLE') }}</h3>
                            <x-chrono-help><p class="mb-4">{{ $page->copy->text('SETTINGS_BACKUP_SNAPSHOT_DESCRIPTION') }}</p></x-chrono-help>
                            @if (\Illuminate\Support\Facades\Route::has('chrono.database-snapshot'))
                                <a href="download.php" class="btn btn-outline-secondary" aria-describedby="snapshot-sensitive">{{ $page->copy->text('SETTINGS_BACKUP_SNAPSHOT_DOWNLOAD') }}</a>
                                <x-chrono-help><p id="snapshot-sensitive" class="text-sm opacity-70 mt-2">Der vollständige Datenbank-Snapshot enthält sensible Konto- und Personaldaten. Externe Schlüssel, Anwendungscode und Betriebskonfiguration sind nicht enthalten.</p></x-chrono-help>
                            @else
                                <button type="button" class="btn btn-outline-secondary" disabled aria-describedby="snapshot-hold">{{ $page->copy->text('SETTINGS_BACKUP_SNAPSHOT_DOWNLOAD') }}</button>
                                <p id="snapshot-hold" class="text-sm opacity-70 mt-2">Hier nicht verfügbar.</p>
                            @endif
                        </div>
                    </section>
                </div>
                </details>
            @endif
        </div>
    </div>
</main>

@if ($settings->saved)
    <div id="successModal" class="alert alert-success" role="status">
        <span>{{ $page->copy->text('SETTINGS_SAVE_SUCCESS_TITLE') }}</span>
        <button type="button" onclick="closeModal()" class="btn btn-ghost">{{ $page->copy->text('BUTTON_CLOSE') }}</button>
    </div>

    <script>
        function closeModal() {
            document.getElementById('successModal').hidden = true;
        }
    </script>
@endif

<script>
    // Funktion zum Aktualisieren des Themes
    function updateTheme(theme) {
        // Reuse the standard chrome's single raw-choice/System resolver.
        setTheme(theme);
    }

    // Theme beim Laden der Seite setzen
    document.addEventListener('DOMContentLoaded', function() {
        const savedTheme = chronoReadPreference('theme') || 'light';
        updateTheme(savedTheme);
    });

    // Theme-Wechsel überwachen
    const themeSelect = document.getElementById('theme_mode');
    if (themeSelect) {
        themeSelect.addEventListener('change', function() {
            updateTheme(this.value);
        });
    }
</script>
@endsection
