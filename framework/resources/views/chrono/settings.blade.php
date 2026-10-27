@extends('chrono.layouts.app')

@section('content')
{{-- Native Settings UI; only ordinary session preferences are connected. --}}
<style>
    /* Existing utility equivalents, scoped to the unchanged Settings cards. */
    .chrono-settings .text-3xl { font-size: 1.875rem; line-height: 2.25rem; }
    .chrono-settings .space-y-6 > * + * { margin-top: 1.5rem; }
    .chrono-settings .my-8 { margin-top: 2rem; margin-bottom: 2rem; }
    .chrono-settings .shadow-md { box-shadow: 0 4px 6px -1px rgb(0 0 0 / .1), 0 2px 4px -2px rgb(0 0 0 / .1); }
    @media (min-width: 768px) { .chrono-settings .md\:w-auto { width: auto; } }
</style>
<div class="container mx-auto px-4 py-8 chrono-settings">
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title text-3xl mb-6 flex items-center">
                <img src="assets/kolibri_icon.png" alt="Quodara Chrono Logo" class="w-12 h-12 mr-4">
                {{ $page->copy->text('SETTINGS_TITLE') }}
            </h2>

            @if ($settings->error)
                <div class="alert alert-error shadow-lg mb-4">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>{{ $settings->error }}</span>
                    </div>
                </div>
            @endif

            <form method="post" class="space-y-6">
@csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-control">
                        <label class="label" for="lang">
                            <span class="label-text"><i class="fas fa-language mr-2"></i>{{ $page->copy->text('SETTINGS_LANGUAGE') }}</span>
                        </label>
                        <select name="lang" id="lang" class="select select-bordered w-full">
                            <option value="de" @selected($page->copy->language === 'de')>Deutsch</option>
                            <option value="en" @selected($page->copy->language === 'en')>English</option>
                            <option value="zh" @selected($page->copy->language === 'zh')>中文</option>
                        </select>
                    </div>

                    <div class="form-control">
                        <label class="label" for="regelarbeitszeit">
                            <span class="label-text"><i class="fas fa-clock mr-2"></i>{{ $page->copy->text('SETTINGS_REGULAR_WORKING_HOURS') }}</span>
                        </label>
                        <p>Das aktuelle Soll mit Gültigkeitsdatum steht unter <a href="work_models.php">Arbeitszeitmodell</a>.</p><input type="number" step="0.1" id="regelarbeitszeit" disabled name="regelarbeitszeit" value="{{ $settings->regularHours }}" min="0" max="24" class="input input-bordered w-full">
                    </div>

                    @if ($page->role === 'admin')
                        <div class="form-control">
                            <label class="label" for="ueberstunden">
                                <span class="label-text"><i class="fas fa-hourglass mr-2"></i>{{ $page->copy->text('SETTINGS_OVERTIME') }}</span>
                            </label>
                            <input type="number" step="any" id="ueberstunden" name="ueberstunden" value="{{ $settings->carryInHours }}" class="input input-bordered w-full" aria-describedby="carry-in-contract">
                            <p id="carry-in-contract" class="text-sm opacity-70 mt-2">Nur Ihr eigener früherer Überstunden-Übertrag. Positive und negative endliche Zahlen mit Dezimalpunkt und optionalem Exponent sind zulässig; keine Leerzeichen oder Dezimalkommas. Ungültige, zu kleine oder nicht darstellbare Werte werden abgelehnt, nicht gerundet. Das vorhandene REAL-Datenformat bleibt eine binäre Näherung; die bestehende Historie ist kein verlustfreies Dezimaljournal. Bei einem Fehler kann die zuvor gewählte Sprache bereits übernommen sein.</p>
                        </div>
                    @endif

                    <div class="form-control">
                        <label class="label" for="theme_mode">
                            <span class="label-text"><i class="fas fa-adjust mr-2"></i>{{ $page->copy->text('NAV_DARK_MODE') }}</span>
                        </label>
                        <select name="theme_mode" id="theme_mode" class="select select-bordered w-full">
                            <option value="light" @selected($settings->themeMode === 'light')>Hell</option>
                            <option value="dark" @selected($settings->themeMode === 'dark')>Dunkel</option>
                            <option value="system" @selected($settings->themeMode === 'system')>System</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit" class="btn btn-primary w-full md:w-auto"><i class="fas fa-save mr-2"></i>{{ $page->copy->text('BUTTON_SAVE_CHANGES') }}</button>
                </div>
            </form>

            @if ($page->role === 'admin')
                <div class="divider my-8">{{ $page->copy->text('SETTINGS_BACKUP_OPERATIONS_TITLE') }}</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="card bg-base-200 shadow-md">
                        <div class="card-body">
                            <h3 class="card-title"><i class="fas fa-history mr-2"></i>{{ $page->copy->text('SETTINGS_BACKUP_RESTORE_TITLE') }}</h3>
                            <p>{{ $page->copy->text('SETTINGS_BACKUP_IMPORT_DISABLED') }}</p>
                            <p>{{ $page->copy->text('SETTINGS_BACKUP_RESTORE_INSTRUCTIONS') }}</p>
                            <p>{{ $page->copy->text('SETTINGS_BACKUP_RESTORE_GUIDE') }} <code>docs/TERMINAL.md</code></p>
                        </div>
                    </div>
                    <div class="card bg-base-200 shadow-md">
                        <div class="card-body">
                            <h3 class="card-title"><i class="fas fa-download mr-2"></i>{{ $page->copy->text('SETTINGS_BACKUP_SNAPSHOT_TITLE') }}</h3>
                            <p class="mb-4">{{ $page->copy->text('SETTINGS_BACKUP_SNAPSHOT_DESCRIPTION') }}</p>
                            <button type="button" class="btn btn-primary w-full" disabled aria-describedby="snapshot-hold"><i class="fas fa-download mr-2"></i>{{ $page->copy->text('SETTINGS_BACKUP_SNAPSHOT_DOWNLOAD') }}</button>
                            <p id="snapshot-hold" class="text-sm opacity-70 mt-2">Der Datenbank-Snapshot ist in dieser Laravel-Ansicht noch nicht angebunden. Es wird kein Download oder Restore ausgeführt.</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@if ($settings->saved)
    <div id="successModal" class="modal modal-open">
        <div class="modal-box">
            <h3 class="font-bold text-lg">Erfolg!</h3>
            <p class="py-4">Einstellungen erfolgreich aktualisiert!</p>
            <div class="modal-action">
                <button onclick="closeModal()" class="btn btn-primary">Schließen</button>
            </div>
        </div>
    </div>

    <script>
        function closeModal() {
            document.getElementById('successModal').classList.remove('modal-open');
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
        const savedTheme = localStorage.getItem('theme') || 'light';
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
