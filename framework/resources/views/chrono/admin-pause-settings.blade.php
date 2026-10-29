@extends('chrono.layouts.app')

@section('content')
@php($managedUsersEnabled = $managedUsersEnabled ?? false)
<style>
    #user-management > .card-body > .card-title { position:relative; }
    #account-lifecycle-help { display:inline-flex;align-self:stretch;align-items:center; }
    #account-lifecycle-tooltip { position:absolute;left:0;top:100%;z-index:20;width:min(24rem,100%);padding:.75rem;border:1px solid hsl(var(--b3,214 16% 91%));border-radius:.5rem;background:hsl(var(--b1,0 0% 100%));color:hsl(var(--bc,215 28% 17%));font-size:.875rem;font-weight:400;line-height:1.4;box-shadow:0 .25rem .75rem rgb(0 0 0 / .15); }
    #account-lifecycle-tooltip[hidden] { display:none; }
</style>
<main class="container mx-auto px-4 py-8 chrono-page">
    @if ($managedUsersEnabled)
    <div class="alert alert-info mb-4" role="note">Die vorhandene Benutzerverwaltung ist in diesem privaten Laravel-{{ $adminPauseSettings->accountLifecycleV5 ? 'V5-Lokalprofil' : 'V4-Profil' }} verbunden. Historiengeschützte Konten und die letzte lokale Administration bleiben erhalten. API-Token sind nicht angebunden; LDAP benötigt weiterhin die getrennte HTTP- und Aufnahmefreigabe.</div>
    @else
    <div class="alert alert-info mb-4" role="note">In dieser Laravel-Ansicht sind die vorhandene Pausenregel-, Abteilungsverwaltung und das Erstellen lokaler Benutzer verbunden. Bestehende Benutzerdetails bleiben nur lesend; Änderungen, Löschen, Passwortänderungen, API-Token sind nicht angebunden. LDAP-Synchronisierung erfordert zusätzlich die ausdrücklich zugelassene V4-HTTP-Komposition und gespeicherte Aufnahmefreigabe.</div>
    @endif
    @if ($adminPauseSettings->error)
    <div class="alert alert-error shadow-lg mb-4" role="alert"><div><i class="fas fa-exclamation-circle"></i><span>{{ $adminPauseSettings->error }}</span></div></div>
    @endif
    @if ($adminPauseSettings->success)
    <div class="alert alert-success shadow-lg mb-4" role="status"><div><i class="fas fa-check-circle"></i><span>{{ $adminPauseSettings->success }}</span></div></div>
    @endif
    @if ($adminPauseSettings->intakePolicy !== null)
    <div id="user-intake-policy" class="card bg-base-100 shadow-xl mb-8">
        <div class="card-body">
            <h2 class="card-title text-2xl mb-4">Benutzeraufnahme · Laravel {{ $adminPauseSettings->accountLifecycleV5 ? 'V5 lokal' : 'V4' }}</h2>
            <p class="mb-4">Aufnahmewege unabhängig freigeben. Bestehende Konten und Anmeldungen bleiben unverändert; die Erst-Admin-Einrichtung ist davon getrennt. LDAP benötigt zusätzlich einen zugelassenen Connector; Speichern startet keinen Verzeichnislauf.</p>
            @if ($adminPauseSettings->accountLifecycleV5)
            <p class="alert alert-info mb-4" role="note">Dieses vorbereitete V5-Profil ist lokal. LDAP-Anmeldung, LDAP-Synchronisierung und API sind nicht zugelassen; die Aufnahmeauswahl allein aktiviert diese Kanäle nicht.</p>
            @endif
            <form method="post" class="space-y-4">
                @csrf
                <input type="hidden" name="update_intake_policy" value="1">
                <input type="hidden" name="revision" value="{{ $adminPauseSettings->intakePolicy['revision'] }}">
                <fieldset @disabled(! $adminPauseSettings->enabled) class="space-y-4">
                    @foreach (['manual_enabled' => 'Manuelle Anlage', 'ldap_sync_enabled' => 'LDAP-Synchronisierung', 'registration_enabled' => 'Öffentliche Selbstregistrierung'] as $field => $label)
                    <div class="form-control">
                        <label class="label" for="intake-{{ $field }}"><span class="label-text">{{ $label }}</span></label>
                        <select class="select select-bordered" id="intake-{{ $field }}" name="{{ $field }}">
                            <option value="0" @selected(! $adminPauseSettings->intakePolicy[$field])>Aus</option>
                            <option value="1" @selected($adminPauseSettings->intakePolicy[$field])>An</option>
                        </select>
                    </div>
                    @endforeach
                    <button class="btn btn-primary">Aufnahmewege speichern</button>
                </fieldset>
            </form>
            <p class="text-sm opacity-70 mt-3">Stand: Revision {{ $adminPauseSettings->intakePolicy['revision'] }}. Veraltete Formulare überschreiben keine neuere Auswahl. Alle Wege dürfen aus sein; lokale Administration kann die Auswahl trotzdem wieder ändern.</p>
        </div>
    </div>
    @endif
    @if ($adminPauseSettings->intakePolicy !== null)
        @include('chrono.partials.ldap-sync', ['ldap' => $adminPauseSettings])
    @endif
    <div id="user-management" class="card bg-base-100 shadow-xl mb-8">
        <div class="card-body">
            <h2 class="card-title text-2xl mb-4">{{ $page->copy->text('USER_MANAGEMENT_TITLE') }}
                <span id="account-lifecycle-help">
                    <button type="button" id="account-lifecycle-trigger" class="btn btn-ghost btn-sm" aria-label="Hinweis zu Kontosperren" aria-describedby="account-lifecycle-tooltip" aria-controls="account-lifecycle-tooltip" aria-expanded="false">ⓘ</button>
                    <span id="account-lifecycle-tooltip" role="tooltip" hidden>@if ($adminPauseSettings->lifecycleUsers !== [])Kontostatus und Terminal-/LDAP-Sitzungssperren sind getrennte Aktionen. Gesperrte Konten bleiben mit ihrer Historie erhalten.@else Eine zentrale Kontosperre fehlt noch. Lokale Passwortänderungen sperren weder erneute LDAP-Anmeldungen noch Terminal-Zugänge.@endif</span>
                </span>
            </h2>
            @if ($adminPauseSettings->intakePolicy !== null && ! $adminPauseSettings->intakePolicy['manual_enabled'])<p class="alert alert-info mb-4" role="note">Manuelle Benutzeraufnahme ist durch die gespeicherte Auswahl deaktiviert.</p>@endif
            @if ($adminPauseSettings->ldapSessionNotice !== null)
            <p class="alert alert-info mb-4" role="status">{{ $adminPauseSettings->ldapSessionNotice }}</p>
            @endif
            @if ($page->interactions->ldapSessionRevocations)
            <p class="text-sm opacity-70 mb-4" id="ldap-session-revocation-help">„LDAP-Sitzungen beenden“ beendet bestehende Web-Sitzungen und bisherige hashgebundene Tokens beim nächsten autoritativen Check. Es deaktiviert kein Konto: erneute gültige LDAP-Anmeldung bleibt möglich, Terminal-Zugänge bleiben unverändert. Bei unklarer Antwort nicht erneut senden.</p>
            @endif
            @if ($adminPauseSettings->lifecycleNotice !== null)
            <p class="alert alert-info mb-4" role="status">{{ $adminPauseSettings->lifecycleNotice }}</p>
            @endif
            @unless ($managedUsersEnabled)
            <div id="user-mutations-hold" class="alert alert-info mb-4" role="note">Bestehende Benutzer bleiben nur lesend: Ändern, Löschen und Passwortänderungen sind nicht verbunden.</div>
            @endunless
            <button type="button" class="btn btn-primary mb-4" id="toggleUserForm" @disabled(! $adminPauseSettings->enabled || ($adminPauseSettings->intakePolicy !== null && ! $adminPauseSettings->intakePolicy['manual_enabled']))><i class="fas fa-plus mr-2"></i>{{ $page->copy->text('BUTTON_CREATE_USER') }}</button>
            @php($createUserDraft = $adminPauseSettings->createUserDraft)
            <div id="userForm" class="{{ $createUserDraft === null ? 'hidden ' : '' }}mb-6">
                <form method="post" class="space-y-4">
                    @if ($createUserDraft !== null)<p class="text-sm opacity-70" role="status">Eingaben zur Überarbeitung erhalten. Bitte alle Angaben prüfen, Rolle und Zuordnungen ausdrücklich neu wählen und das Passwort erneut eingeben. Keine automatische Speicherung oder Wiederholung.</p>@endif
                    @csrf
                    <input type="hidden" name="add_user" value="1">
                    <fieldset @disabled(! $adminPauseSettings->enabled || ($adminPauseSettings->intakePolicy !== null && ! $adminPauseSettings->intakePolicy['manual_enabled'])) class="space-y-4">
                    <div class="form-control">
                        <label class="label" for="username"><span class="label-text">{{ $page->copy->text('FORM_USERNAME') }}</span></label>
                        <input type="text" id="username" name="username"@if ($createUserDraft !== null) value="{{ $createUserDraft['username'] ?? '' }}"@endif class="input input-bordered" required>
                    </div>
                    <div class="form-control">
                        <label class="label" for="password"><span class="label-text">{{ $page->copy->text('FORM_PASSWORD') }}</span></label>
                        <input type="password" id="password" name="password" class="input input-bordered" required>
                    </div>
                    <div class="form-control">
                        <label class="label" for="email"><span class="label-text">{{ $page->copy->text('FORM_EMAIL') }}</span></label>
                        <input type="email" id="email" name="email"@if ($createUserDraft !== null) value="{{ $createUserDraft['email'] ?? '' }}"@endif class="input input-bordered" required>
                    </div>
                    <div class="form-control">
                        <label class="label" for="role"><span class="label-text">{{ $page->copy->text('FORM_ROLE') }}</span></label>
                        <select id="role" name="role" class="select select-bordered" required>
                            @if ($createUserDraft !== null)<option value="" disabled selected>Rolle erneut wählen</option>@endif
                            <option value="user">{{ $page->copy->text('FORM_ROLE_USER') }}</option>
                            <option value="admin">{{ $page->copy->text('FORM_ROLE_ADMIN') }}</option>
                            <option value="supervisor">{{ $page->copy->text('FORM_ROLE_SUPERVISOR') }}</option>
                        </select>
                    </div>
                    <div class="form-control">
                        <label class="label" for="department"><span class="label-text">{{ $page->copy->text('FORM_DEPARTMENT') }}</span></label>
                        <select id="department" name="department" class="select select-bordered" required>
                            @if ($createUserDraft !== null)<option value="" disabled selected>Abteilung erneut wählen</option>@endif
                            @foreach ($adminPauseSettings->departments as $department)
                            <option value="{{ $department['id'] }}">{{ $department['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-control">
                        <label class="label" for="supervisor"><span class="label-text">{{ $page->copy->text('FORM_SUPERVISOR') }}</span></label>
                        <select id="supervisor" name="supervisor" class="select select-bordered">
                            @if ($createUserDraft !== null)<option value="" disabled selected>Vorgesetzten oder ausdrücklich keinen erneut wählen</option>@endif
                            <option value="">{{ $page->copy->text('FORM_NO_SUPERVISOR') }}</option>
                            @foreach ($adminPauseSettings->supervisors as $supervisor)
                            <option value="{{ $supervisor['id'] }}">{{ $supervisor['username'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-control">
                        <label class="label" for="regelarbeitszeit"><span class="label-text">{{ $page->copy->text('FORM_REGULAR_WORKING_HOURS') }}</span></label>
                        <input type="number" id="regelarbeitszeit" name="regelarbeitszeit"@if ($createUserDraft !== null) value="{{ $createUserDraft['regelarbeitszeit'] ?? '' }}"@endif class="input input-bordered" step="0.01" required>
                    </div>
                    <div class="form-control">
                        <label class="label" for="ueberstunden"><span class="label-text">{{ $page->copy->text('FORM_OVERTIME') }}</span></label>
                        <input type="number" id="ueberstunden" name="ueberstunden" class="input input-bordered" step="0.01" value="{{ $createUserDraft === null ? '0' : ($createUserDraft['ueberstunden'] ?? '') }}">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus mr-2"></i>{{ $page->copy->text('BUTTON_CREATE_USER') }}</button>
                    </fieldset>
                </form>
            </div>
            <div id="user-detail-error" class="alert alert-error mb-4 hidden" role="alert"></div>
            <form action="" method="GET" class="mb-4">
                <div class="form-control"><div class="input-group">
                    <input type="text" name="search" id="userSearch" class="input input-bordered" placeholder="{{ $page->copy->text('SEARCH_USERS') }}" value="{{ $adminPauseSettings->users['search'] ?? '' }}">
                    <button class="btn btn-square" type="submit"><i class="fas fa-search"></i></button>
                </div></div>
            </form>
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead><tr>
                        <th class="text-left">{{ $page->copy->text('TABLE_HEADER_USERNAME') }}</th>
                        <th class="text-left">{{ $page->copy->text('TABLE_HEADER_EMAIL') }}</th>
                        <th class="text-left">{{ $page->copy->text('TABLE_HEADER_ROLE') }}</th>
                        <th class="text-left">{{ $page->copy->text('TABLE_HEADER_DEPARTMENT') }}</th>
                        <th class="text-left">{{ $page->copy->text('TABLE_HEADER_SUPERVISOR') }}</th>
                        <th class="text-left">{{ $page->copy->text('TABLE_HEADER_REGULAR_WORKING_HOURS') }}</th>
                        <th class="text-left">{{ $page->copy->text('TABLE_HEADER_OVERTIME') }}</th>
                        <th class="text-left">{{ $page->copy->text('TABLE_HEADER_ACTIONS') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach (($adminPauseSettings->users['users'] ?? []) as $user)
                        <tr>
                            <td>{{ $user['username'] }}</td><td>{{ $user['email'] }}</td><td>{{ $user['role'] }}</td>
                            <td>{{ $user['department_name'] ?? '' }}@if (($user['department_reference_status'] ?? null) === 'missing')<small class="block text-warning mt-1">Zugeordnete Abteilung fehlt. Zuordnung über „Bearbeiten“ prüfen.</small>@elseif (in_array($user['department_reference_status'] ?? null, ['invalid', 'unknown'], true))<small class="block text-warning mt-1">Abteilungszuordnung nicht eindeutig prüfbar. Bestand über „Bearbeiten“ prüfen.</small>@endif</td><td>{{ $user['supervisor_name'] ?? '' }}@if (($user['supervisor_reference_status'] ?? null) === 'missing')<small class="block text-warning mt-1">Zugeordnete vorgesetzte Person fehlt. Zuordnung über „Bearbeiten“ prüfen.</small>@elseif (in_array($user['supervisor_reference_status'] ?? null, ['invalid', 'unknown'], true))<small class="block text-warning mt-1">Vorgesetztenzuordnung nicht eindeutig prüfbar. Bestand über „Bearbeiten“ prüfen.</small>@endif</td>
                            <td>{{ $user['regelarbeitszeit'] }}</td><td>{{ $user['ueberstunden'] }}</td>
                            <td>
                                <button type="button" class="btn btn-ghost btn-sm" data-read-user="{{ $user['id'] }}" title="{{ $page->copy->text('BUTTON_EDIT') }}"><i class="fas fa-edit"></i></button>
                                @if (($user['has_audit_history'] ?? false) === true)
                                <span class="inline-block text-sm">{{ $page->copy->text('ADMIN_USER_DELETE_HISTORY_LOCKED') }}</span>
                                @elseif ($managedUsersEnabled)
                                <form method="post" class="inline" data-delete-managed-user>
                                    @csrf
                                    <input type="hidden" name="delete_user" value="1">
                                    <input type="hidden" name="user_id" value="{{ $user['id'] }}">
                                    <button type="submit" class="btn btn-ghost btn-sm" title="{{ $page->copy->text('BUTTON_DELETE') }}"><i class="fas fa-trash-alt"></i></button>
                                </form>
                                @else
                                <button type="button" class="btn btn-ghost btn-sm" title="{{ $page->copy->text('BUTTON_DELETE') }}" disabled aria-describedby="user-mutations-hold"><i class="fas fa-trash-alt"></i></button>
                                @endif
                                @if (isset($adminPauseSettings->lifecycleUsers[$user['id']]))
                                    @include('chrono.partials.account-lifecycle-actions', ['lifecycleUser' => $adminPauseSettings->lifecycleUsers[$user['id']], 'lifecycleId' => $user['id']])
                                @endif
                                @if (isset($adminPauseSettings->ldapSessionConfirmations[$user['id']]))
                                <form method="post" class="inline" data-revoke-ldap-sessions aria-describedby="ldap-session-revocation-help">
                                    @csrf
                                    <input type="hidden" name="revoke_ldap_sessions" value="1">
                                    <input type="hidden" name="user_id" value="{{ $user['id'] }}">
                                    <input type="hidden" name="confirmation" value="{{ $adminPauseSettings->ldapSessionConfirmations[$user['id']] }}">
                                    <label class="label cursor-pointer inline-flex gap-2"><input type="checkbox" class="checkbox checkbox-sm" name="confirm" value="1" required><span class="label-text">Sitzungen für {{ $user['username'] }} beenden</span></label>
                                    <button type="submit" class="btn btn-ghost btn-sm">LDAP-Sitzungen beenden</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="btn-group mt-4">
            @for ($i = 1; $i <= ($adminPauseSettings->users['total_pages'] ?? 0); $i++)
                <a href="?page={{ $i }}&amp;search={{ urlencode($adminPauseSettings->users['search']) }}" class="btn {{ $i === $adminPauseSettings->users['page'] ? 'btn-active' : '' }}">{{ $i }}</a>
            @endfor
            </div>
        </div>
    </div>
    <div id="pause-settings" class="card bg-base-100 shadow-xl mb-8">
        <div class="card-body">
            <h2 class="card-title text-2xl mb-4">{{ $page->copy->text('PAUSE_SETTINGS_TITLE') }}</h2>
            <form method="post" class="space-y-4">
                @csrf
                <input type="hidden" name="update_pause_settings" value="1">
                <fieldset @disabled(! $adminPauseSettings->enabled) class="space-y-4">
                <table class="table w-full" id="pauseSettingsTable">
                    <thead><tr>
                        <th class="text-left">{{ $page->copy->text('TABLE_HEADER_HOURS_THRESHOLD') }}</th>
                        <th class="text-left">{{ $page->copy->text('TABLE_HEADER_MINIMUM_PAUSE') }}</th>
                        <th class="text-left">{{ $page->copy->text('TABLE_HEADER_ACTIONS') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($adminPauseSettings->rules as $setting)
                        <tr>
                            <td><input type="number" name="hours_threshold[]" class="input input-bordered w-full" value="{{ $setting['hours_threshold'] }}" required></td>
                            <td><input type="number" name="minimum_pause[]" class="input input-bordered w-full" value="{{ $setting['minimum_pause'] }}" required></td>
                            <td><button type="button" class="btn btn-ghost btn-sm remove-row" title="{{ $page->copy->text('BUTTON_DELETE') }}"><i class="fas fa-trash-alt"></i></button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <button type="button" id="addRow" class="btn btn-secondary"><i class="fas fa-plus mr-2"></i>{{ $page->copy->text('BUTTON_ADD_ROW') }}</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-2"></i>{{ $page->copy->text('BUTTON_SAVE_CHANGES') }}</button>
                </fieldset>
            </form>
        </div>
    </div>
    <div id="department-management" class="card bg-base-100 shadow-xl mb-8">
        <div class="card-body">
            <h2 class="card-title text-2xl mb-4">{{ $page->copy->text('DEPARTMENT_MANAGEMENT_TITLE') }}</h2>
            <form method="post" class="flex space-x-2 mb-4">
                @csrf
                <input type="hidden" name="add_department" value="1">
                <input type="text" name="department_name" class="input input-bordered flex-grow" placeholder="{{ $page->copy->text('FORM_NEW_DEPARTMENT') }}" required @disabled(! $adminPauseSettings->enabled)>
                <button type="submit" class="btn btn-primary" @disabled(! $adminPauseSettings->enabled)><i class="fas fa-plus mr-2"></i>{{ $page->copy->text('BUTTON_ADD_DEPARTMENT') }}</button>
            </form>
            <div class="form-control mb-4">
                <input type="text" id="departmentSearch" class="input input-bordered" placeholder="{{ $page->copy->text('SEARCH_DEPARTMENTS') }}">
            </div>
            <table class="table w-full">
                <thead><tr>
                    <th class="text-left">{{ $page->copy->text('TABLE_HEADER_DEPARTMENT_ID') }}</th>
                    <th class="text-left">{{ $page->copy->text('TABLE_HEADER_DEPARTMENT_NAME') }}</th>
                    <th class="text-left">{{ $page->copy->text('TABLE_HEADER_ACTIONS') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($adminPauseSettings->departments as $department)
                    <tr>
                        <td>{{ $department['id'] }}</td>
                        <td>{{ $department['name'] }}</td>
                        <td>
                            <button type="button" class="btn btn-ghost btn-sm" data-edit-department data-department-id="{{ $department['id'] }}" data-department-name="{{ json_encode($department['name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) }}" title="{{ $page->copy->text('BUTTON_EDIT') }}" @disabled(! $adminPauseSettings->enabled)><i class="fas fa-edit"></i></button>
                            <form method="post" class="inline" data-delete-department>
                                @csrf
                                <input type="hidden" name="delete_department" value="1">
                                <input type="hidden" name="department_id" value="{{ $department['id'] }}">
                                <button type="submit" class="btn btn-ghost btn-sm" title="{{ $page->copy->text('BUTTON_DELETE') }}" @disabled(! $adminPauseSettings->enabled)><i class="fas fa-trash-alt"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</main>
@if ($adminPauseSettings->lifecycleUsers !== [])
@include('chrono.partials.account-lifecycle-dialog')
@endif
<dialog id="editUserModal" class="modal">
    @if ($managedUsersEnabled)
    <form method="post" class="modal-box" id="managed-user-form">
        @csrf
        <input type="hidden" name="edit_user" value="1">
    @else
    <div class="modal-box">
    @endif
        <h3 class="font-bold text-lg mb-4">{{ $page->copy->text('MODAL_TITLE_EDIT_USER') }}</h3>
        @unless ($managedUsersEnabled)
        <div id="user-detail-hold" class="alert alert-info mb-4" role="note">Nur lesende Benutzerdetails. Änderungen und Passwortaktionen sind nicht verbunden; es gibt hier keine Speicherung.</div>
        @endunless
        <input type="hidden" id="edit_user_id" name="user_id">
        <div class="form-control mb-4"><label class="label" for="edit_username"><span class="label-text">{{ $page->copy->text('FORM_USERNAME') }}</span></label><input type="text" id="edit_username" name="username" class="input input-bordered" required @readonly(! $managedUsersEnabled)></div>
        <div class="form-control mb-4"><label class="label" for="edit_email"><span class="label-text">{{ $page->copy->text('FORM_EMAIL') }}</span></label><input type="email" id="edit_email" name="email" class="input input-bordered" required @readonly(! $managedUsersEnabled)></div>
        <div class="form-control mb-4"><label class="label" for="edit_role"><span class="label-text">{{ $page->copy->text('FORM_ROLE') }}</span></label>
            <select id="edit_role" name="role" class="select select-bordered" required @disabled(! $managedUsersEnabled)>
                <option value="user">{{ $page->copy->text('FORM_ROLE_USER') }}</option><option value="admin">{{ $page->copy->text('FORM_ROLE_ADMIN') }}</option><option value="supervisor">{{ $page->copy->text('FORM_ROLE_SUPERVISOR') }}</option>
            </select>
        </div>
        <div class="form-control mb-4"><label class="label" for="edit_department"><span class="label-text">{{ $page->copy->text('FORM_DEPARTMENT') }}</span></label><select id="edit_department" name="department" class="select select-bordered" required @disabled(! $managedUsersEnabled)>
            @if ($managedUsersEnabled) @foreach ($adminPauseSettings->departments as $department)<option value="{{ $department['id'] }}">{{ $department['name'] }}</option>@endforeach @endif
        </select></div>
        <div class="form-control mb-4"><label class="label" for="edit_supervisor"><span class="label-text">{{ $page->copy->text('FORM_SUPERVISOR') }}</span></label><select id="edit_supervisor" name="supervisor" class="select select-bordered" @disabled(! $managedUsersEnabled)>
            @if ($managedUsersEnabled)
            <option value="">{{ $page->copy->text('FORM_NO_SUPERVISOR') }}</option>
            @foreach ($adminPauseSettings->supervisors as $supervisor)<option value="{{ $supervisor['id'] }}">{{ $supervisor['username'] }}</option>@endforeach
            @endif
        </select></div>
        <div class="form-control mb-4"><label class="label" for="edit_regelarbeitszeit"><span class="label-text">{{ $page->copy->text('FORM_REGULAR_WORKING_HOURS') }}</span></label>
            <p>Änderungen am Soll bitte unter <a href="work_models.php">Arbeitszeitmodelle</a> mit Gültigkeitsdatum speichern.</p><input readonly type="number" id="edit_regelarbeitszeit" name="regelarbeitszeit" class="input input-bordered" step="0.01" required>
        </div>
        <div class="form-control mb-4"><label class="label" for="edit_ueberstunden"><span class="label-text">{{ $page->copy->text('FORM_OVERTIME') }}</span></label><input type="number" id="edit_ueberstunden" name="ueberstunden" class="input input-bordered" step="0.01" required @readonly(! $managedUsersEnabled)></div>
        <div class="form-control mb-4"><label class="label" for="edit_password"><span class="label-text">{{ $page->copy->text('FORM_NEW_PASSWORD') }}</span></label><input type="password" id="edit_password" name="password" class="input input-bordered" value="" autocomplete="new-password" @disabled(! $managedUsersEnabled) @unless ($managedUsersEnabled) aria-describedby="user-detail-hold" @endunless></div>
        <div class="modal-action">
            <button type="{{ $managedUsersEnabled ? 'submit' : 'button' }}" id="save-managed-user" class="btn btn-primary" disabled @unless ($managedUsersEnabled) aria-describedby="user-detail-hold" @endunless>{{ $page->copy->text('BUTTON_SAVE_CHANGES') }}</button>
            <button type="button" class="btn" @if ($page->cspNonce === null) onclick="closeEditUserModal()" @else data-local-close-user @endif>{{ $page->copy->text('BUTTON_CANCEL') }}</button>
        </div>
    @if ($managedUsersEnabled) </form> @else </div> @endif
</dialog>
<dialog id="editDepartmentModal" class="modal">
    <form method="post" class="modal-box">
        @csrf
        <h3 class="font-bold text-lg mb-4">{{ $page->copy->text('MODAL_TITLE_EDIT_DEPARTMENT') }}</h3>
        <input type="hidden" id="edit_department_id" name="department_id">
        <input type="hidden" name="edit_department" value="1">
        <div class="form-control mb-4">
            <label class="label" for="edit_department_name"><span class="label-text">{{ $page->copy->text('FORM_DEPARTMENT_NAME') }}</span></label>
            <input type="text" id="edit_department_name" name="department_name" class="input input-bordered" required @disabled(! $adminPauseSettings->enabled)>
        </div>
        <div class="modal-action">
            <button type="submit" class="btn btn-primary" @disabled(! $adminPauseSettings->enabled)>{{ $page->copy->text('BUTTON_SAVE_CHANGES') }}</button>
            <button type="button" class="btn" @if ($page->cspNonce === null) onclick="closeEditDepartmentModal()" @else data-local-close-department @endif>{{ $page->copy->text('BUTTON_CANCEL') }}</button>
        </div>
    </form>
</dialog>
<script @if ($page->cspNonce !== null) nonce="{{ $page->cspNonce }}" @endif>
    // Only the original pause table's add/remove enhancement; no other Admin JS.
    document.addEventListener('DOMContentLoaded', function () {
        const addRowButton = document.getElementById('addRow');
        const pauseSettingsTable = document.getElementById('pauseSettingsTable');
        if (!addRowButton || !pauseSettingsTable) return;
        addRowButton.addEventListener('click', function () {
            const newRow = pauseSettingsTable.insertRow(-1);
            newRow.innerHTML = `
                <td><input type="number" name="hours_threshold[]" class="input input-bordered w-full" required></td>
                <td><input type="number" name="minimum_pause[]" class="input input-bordered w-full" required></td>
                <td><button type="button" class="btn btn-ghost btn-sm remove-row"><i class="fas fa-trash-alt"></i></button></td>
            `;
            newRow.querySelector('.remove-row').title = {!! \Illuminate\Support\Js::from($page->copy->text('BUTTON_DELETE')) !!};
        });
        pauseSettingsTable.addEventListener('click', function (event) {
            if (event.target instanceof Element && event.target.closest('.remove-row')) {
                event.target.closest('tr').remove();
            }
        });
    });
</script>
<script @if ($page->cspNonce !== null) nonce="{{ $page->cspNonce }}" @endif>
    // Original create toggle and department behavior; no edit/delete/LDAP actions.
    document.addEventListener('DOMContentLoaded', function () {
        @if ($page->cspNonce !== null)
        document.querySelectorAll('[data-local-close-department]').forEach(function (button) { button.addEventListener('click', closeEditDepartmentModal); });
        @endif
        document.getElementById('toggleUserForm').addEventListener('click', function () {
            document.getElementById('userForm').classList.toggle('hidden');
        });
        document.getElementById('departmentSearch').addEventListener('input', function () {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('#department-management table tbody tr').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().includes(searchTerm) ? '' : 'none';
            });
        });
        document.querySelectorAll('[data-edit-department]').forEach(function (button) {
            button.addEventListener('click', function () {
                editDepartment(button.dataset.departmentId, JSON.parse(button.dataset.departmentName));
            });
        });
        document.querySelectorAll('[data-delete-department]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!confirm({!! \Illuminate\Support\Js::from($page->copy->text('CONFIRM_DELETE_DEPARTMENT')) !!})) event.preventDefault();
            });
        });
    });
    function editDepartment(departmentId, departmentName) {
        const modal = document.getElementById('editDepartmentModal');
        document.getElementById('edit_department_id').value = departmentId;
        document.getElementById('edit_department_name').value = departmentName;
        modal.showModal();
    }
    function closeEditDepartmentModal() {
        document.getElementById('editDepartmentModal').close();
    }
</script>
<script @if ($page->cspNonce !== null) nonce="{{ $page->cspNonce }}" @endif>
    // Display-only help: keyboard/touch access, hover persistence and Escape dismissal.
    const lifecycleHelp = document.getElementById('account-lifecycle-help');
    const lifecycleTrigger = document.getElementById('account-lifecycle-trigger');
    const lifecycleTooltip = document.getElementById('account-lifecycle-tooltip');
    let lifecyclePointerInside = false;
    function setLifecycleHelp(visible) {
        lifecycleTooltip.hidden = !visible;
        lifecycleTrigger.setAttribute('aria-expanded', String(visible));
    }
    lifecycleHelp.addEventListener('mouseenter', function () {
        lifecyclePointerInside = true;
        setLifecycleHelp(true);
    });
    lifecycleHelp.addEventListener('mouseleave', function () {
        lifecyclePointerInside = false;
        if (document.activeElement !== lifecycleTrigger) setLifecycleHelp(false);
    });
    lifecycleTrigger.addEventListener('focus', function () { setLifecycleHelp(true); });
    lifecycleTrigger.addEventListener('blur', function () {
        if (!lifecyclePointerInside) setLifecycleHelp(false);
    });
    lifecycleTrigger.addEventListener('click', function () { setLifecycleHelp(true); });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') setLifecycleHelp(false);
    });
    document.addEventListener('pointerdown', function (event) {
        if (!lifecycleHelp.contains(event.target)) setLifecycleHelp(false);
    });
    window.addEventListener('pageshow', function () { setLifecycleHelp(false); });
    // Same safe get_user.php projection; no password/source/token is returned.
    const managedUsersEnabled = {{ $managedUsersEnabled ? 'true' : 'false' }};
    const userModal = document.getElementById('editUserModal');
    const saveManagedUser = document.getElementById('save-managed-user');
    const managedUserForm = document.getElementById('managed-user-form');
    const departmentOptions = Array.from(document.getElementById('edit_department').options, function (option) { return option.cloneNode(true); });
    const supervisorOptions = Array.from(document.getElementById('edit_supervisor').options, function (option) { return option.cloneNode(true); });
    let userDetailRequest = 0;
    let userEditReady = false;
    let knownUserCloseEvents = 0;
    @if ($page->cspNonce !== null)
    document.querySelectorAll('[data-local-close-user]').forEach(function (button) { button.addEventListener('click', closeEditUserModal); });
    @endif
    document.querySelectorAll('[data-read-user]').forEach(function (button) {
        button.addEventListener('click', function () { editUser(button.dataset.readUser); });
    });
    document.querySelectorAll('[data-revoke-ldap-sessions]').forEach(function (form) {
        let submitted = false;
        form.addEventListener('submit', function (event) {
            if (submitted) { event.preventDefault(); return; }
            if (!confirm('Bestehende LDAP-Sitzungen für dieses Konto beenden? Das Konto bleibt anmeldbar; Terminal-Zugänge werden nicht gesperrt.')) {
                event.preventDefault(); return;
            }
            submitted = true;
            form.querySelector('button[type="submit"]').disabled = true;
        });
    });
    document.querySelectorAll('[data-delete-managed-user]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!confirm({!! \Illuminate\Support\Js::from($page->copy->text('CONFIRM_DELETE_USER')) !!})) event.preventDefault();
        });
    });
    if (managedUserForm) {
        managedUserForm.addEventListener('submit', function (event) {
            if (!userEditReady || !document.getElementById('edit_user_id').value) {
                event.preventDefault();
                return;
            }
            // Leave the password field intact for normal browser serialization;
            // disable only repeat submission, not the submitted form controls.
            userEditReady = false;
            saveManagedUser.disabled = true;
        });
    }
    function clearUserDetails() {
        userEditReady = false;
        saveManagedUser.disabled = true;
        ['edit_user_id', 'edit_username', 'edit_email', 'edit_role', 'edit_regelarbeitszeit', 'edit_ueberstunden', 'edit_password'].forEach(function (id) { document.getElementById(id).value = ''; });
        document.getElementById('edit_department').replaceChildren();
        document.getElementById('edit_supervisor').replaceChildren();
    }
    // Exact text carries large IDs; only provably safe legacy numbers may be stringified.
    function adminIntegerText(value, signed = false) {
        if (typeof value !== 'string' || value === '') return null;
        const negative = signed && value[0] === '-';
        const digits = negative ? value.slice(1) : value;
        if (digits === '' || /[^0-9]/.test(digits) || (digits.length > 1 && digits[0] === '0')
            || ((!signed || negative) && digits === '0')) return null;
        const maximum = negative ? '9223372036854775808' : '9223372036854775807';
        return digits.length > maximum.length || (digits.length === maximum.length && digits > maximum) ? null : value;
    }
    function adminRequestedId(value) {
        return typeof value === 'number'
            ? (Number.isSafeInteger(value) && value > 0 ? String(value) : null)
            : adminIntegerText(value);
    }
    function adminDetailId(data, requested) {
        if (!data || typeof data !== 'object' || !Object.prototype.hasOwnProperty.call(data, 'id')) throw new Error('Invalid user detail.');
        const hasText = Object.prototype.hasOwnProperty.call(data, 'id_string');
        const exact = hasText ? adminIntegerText(data.id_string) : adminRequestedId(data.id);
        if (exact === null || exact !== requested || (hasText && !(
            (typeof data.id === 'number' && Number.isInteger(data.id) && Number(exact) === data.id)
            || (typeof data.id === 'string' && data.id === exact)))) throw new Error('Invalid user detail.');
        return exact;
    }
    function adminDetailAssignment(data, key) {
        if (!Object.prototype.hasOwnProperty.call(data, key)) throw new Error('Invalid assignment detail.');
        const raw = data[key], hasText = Object.prototype.hasOwnProperty.call(data, key + '_string');
        if (!hasText) {
            if (raw === null || typeof raw === 'string') return raw;
            if (typeof raw === 'number' && Number.isFinite(raw) && Math.abs(raw) <= Number.MAX_SAFE_INTEGER) return String(raw);
            throw new Error('Unconfirmed assignment detail.');
        }
        const exact = data[key + '_string'];
        if (raw === null && exact === null) return null;
        if (typeof exact !== 'string' || raw === null) throw new Error('Invalid assignment detail.');
        if (typeof raw === 'string') {
            if (raw !== exact) throw new Error('Inconsistent assignment detail.');
        } else if (typeof raw === 'number' && Number.isFinite(raw)) {
            if (Number.isSafeInteger(raw) || !Number.isInteger(raw)) {
                if (exact !== String(raw)) throw new Error('Inconsistent assignment detail.');
            } else if (adminIntegerText(exact, true) === null || Number(exact) !== raw) {
                throw new Error('Unconfirmed assignment detail.');
            }
        } else {
            throw new Error('Invalid assignment detail.');
        }
        // Preserve exact stored legacy 0/negative/string references for explicit correction.
        return exact;
    }
    function selectUserAssignment(id, options, value, label) {
        const select = document.getElementById(id);
        const selected = value === null || value === undefined ? '' : String(value);
        select.replaceChildren(...(managedUsersEnabled ? options.map(function (option) { return option.cloneNode(true); }) : []));
        if (!Array.from(select.options).some(function (option) { return option.value === selected; })) {
            select.add(new Option(label, selected));
        }
        // Never silently replace a stale/missing assignment with the first option.
        select.value = selected;
    }
    async function editUser(userId) {
        const request = ++userDetailRequest;
        const error = document.getElementById('user-detail-error');
        // Do not close/reopen an existing dialog: its queued close event could
        // otherwise invalidate this newer fetch. Blank it while loading instead.
        clearUserDetails();
        error.textContent = '';
        error.classList.add('hidden');
        try {
            const requestedUserId = adminRequestedId(userId);
            if (requestedUserId === null) throw new Error('Invalid user detail target.');
            const response = await fetch('get_user.php?id=' + encodeURIComponent(requestedUserId), {headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('Benutzerdetails nicht verfügbar. Bitte neu laden und erneut anmelden.');
            const data = await response.json();
            if (request !== userDetailRequest) return;
            const exactUserId = adminDetailId(data, requestedUserId);
            const exactDepartmentId = adminDetailAssignment(data, 'department_id');
            const exactSupervisorId = adminDetailAssignment(data, 'supervisor_id');
            document.getElementById('edit_user_id').value = exactUserId;
            document.getElementById('edit_username').value = data.username;
            document.getElementById('edit_email').value = data.email;
            document.getElementById('edit_role').value = data.role;
            selectUserAssignment('edit_department', departmentOptions, exactDepartmentId, data.department_name ?? '');
            selectUserAssignment('edit_supervisor', supervisorOptions, exactSupervisorId,
                data.supervisor_name ?? (data.supervisor_id ? '' : {!! \Illuminate\Support\Js::from($page->copy->text('FORM_NO_SUPERVISOR')) !!}));
            document.getElementById('edit_regelarbeitszeit').value = data.regelarbeitszeit;
            document.getElementById('edit_ueberstunden').value = data.ueberstunden;
            userEditReady = managedUsersEnabled;
            saveManagedUser.disabled = !userEditReady;
            if (!userModal.open) userModal.showModal();
        } catch (failure) {
            if (request !== userDetailRequest) return;
            clearUserDetails();
            error.textContent = 'Benutzerdetails nicht verfügbar. Bitte neu laden und erneut anmelden.';
            error.classList.remove('hidden');
        }
    }
    function invalidateUserDetails() {
        ++userDetailRequest;
        clearUserDetails();
    }
    function closeEditUserModal() {
        invalidateUserDetails();
        if (userModal.open) {
            ++knownUserCloseEvents;
            userModal.close();
        }
    }
    userModal.addEventListener('cancel', function () {
        // Escape closes by the browser's normal dialog action after this event.
        ++knownUserCloseEvents;
        invalidateUserDetails();
    });
    userModal.addEventListener('close', function () {
        // An earlier known dismissal was already invalidated synchronously.
        // Its delayed event must not erase a subsequent selection/fetch.
        if (knownUserCloseEvents > 0) { --knownUserCloseEvents; return; }
        invalidateUserDetails();
    });
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) closeEditUserModal();
    });
</script>
@endsection
