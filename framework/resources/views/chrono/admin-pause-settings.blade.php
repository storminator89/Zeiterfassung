@extends('chrono.layouts.app')

@section('content')
<main class="container mx-auto px-4 py-8 chrono-page">
    <div class="alert alert-info mb-4" role="note">In dieser Laravel-Ansicht sind die vorhandene Pausenregel-, Abteilungsverwaltung und das Erstellen lokaler Benutzer verbunden. Bestehende Benutzerdetails bleiben nur lesend; Änderungen, Löschen, Passwortänderungen, API-Token sind nicht angebunden. LDAP-Synchronisierung erfordert zusätzlich die ausdrücklich zugelassene V4-HTTP-Komposition und gespeicherte Aufnahmefreigabe.</div>
    @if ($adminPauseSettings->error)
    <div class="alert alert-error shadow-lg mb-4" role="alert"><div><i class="fas fa-exclamation-circle"></i><span>{{ $adminPauseSettings->error }}</span></div></div>
    @endif
    @if ($adminPauseSettings->success)
    <div class="alert alert-success shadow-lg mb-4" role="status"><div><i class="fas fa-check-circle"></i><span>{{ $adminPauseSettings->success }}</span></div></div>
    @endif
    @if ($adminPauseSettings->intakePolicy !== null)
    <div id="user-intake-policy" class="card bg-base-100 shadow-xl mb-8">
        <div class="card-body">
            <h2 class="card-title text-2xl mb-4">Benutzeraufnahme · Laravel V4</h2>
            <p class="mb-4">Aufnahmewege unabhängig freigeben. Bestehende Konten und Anmeldungen bleiben unverändert; die Erst-Admin-Einrichtung ist davon getrennt. LDAP benötigt zusätzlich einen zugelassenen Connector; Speichern startet keinen Verzeichnislauf.</p>
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
            <h2 class="card-title text-2xl mb-4">{{ $page->copy->text('USER_MANAGEMENT_TITLE') }}</h2>
            @if ($adminPauseSettings->intakePolicy !== null && ! $adminPauseSettings->intakePolicy['manual_enabled'])<p class="alert alert-info mb-4" role="note">Manuelle Benutzeraufnahme ist durch die gespeicherte Auswahl deaktiviert.</p>@endif
            <div id="user-mutations-hold" class="alert alert-info mb-4" role="note">Bestehende Benutzer bleiben nur lesend: Ändern, Löschen und Passwortänderungen sind nicht verbunden.</div>
            <button type="button" class="btn btn-primary mb-4" id="toggleUserForm" @disabled(! $adminPauseSettings->enabled || ($adminPauseSettings->intakePolicy !== null && ! $adminPauseSettings->intakePolicy['manual_enabled']))><i class="fas fa-plus mr-2"></i>{{ $page->copy->text('BUTTON_CREATE_USER') }}</button>
            <div id="userForm" class="hidden mb-6">
                <form method="post" class="space-y-4">
                    @csrf
                    <input type="hidden" name="add_user" value="1">
                    <fieldset @disabled(! $adminPauseSettings->enabled || ($adminPauseSettings->intakePolicy !== null && ! $adminPauseSettings->intakePolicy['manual_enabled'])) class="space-y-4">
                    <div class="form-control">
                        <label class="label" for="username"><span class="label-text">{{ $page->copy->text('FORM_USERNAME') }}</span></label>
                        <input type="text" id="username" name="username" class="input input-bordered" required>
                    </div>
                    <div class="form-control">
                        <label class="label" for="password"><span class="label-text">{{ $page->copy->text('FORM_PASSWORD') }}</span></label>
                        <input type="password" id="password" name="password" class="input input-bordered" required>
                    </div>
                    <div class="form-control">
                        <label class="label" for="email"><span class="label-text">{{ $page->copy->text('FORM_EMAIL') }}</span></label>
                        <input type="email" id="email" name="email" class="input input-bordered" required>
                    </div>
                    <div class="form-control">
                        <label class="label" for="role"><span class="label-text">{{ $page->copy->text('FORM_ROLE') }}</span></label>
                        <select id="role" name="role" class="select select-bordered" required>
                            <option value="user">{{ $page->copy->text('FORM_ROLE_USER') }}</option>
                            <option value="admin">{{ $page->copy->text('FORM_ROLE_ADMIN') }}</option>
                            <option value="supervisor">{{ $page->copy->text('FORM_ROLE_SUPERVISOR') }}</option>
                        </select>
                    </div>
                    <div class="form-control">
                        <label class="label" for="department"><span class="label-text">{{ $page->copy->text('FORM_DEPARTMENT') }}</span></label>
                        <select id="department" name="department" class="select select-bordered" required>
                            @foreach ($adminPauseSettings->departments as $department)
                            <option value="{{ $department['id'] }}">{{ $department['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-control">
                        <label class="label" for="supervisor"><span class="label-text">{{ $page->copy->text('FORM_SUPERVISOR') }}</span></label>
                        <select id="supervisor" name="supervisor" class="select select-bordered">
                            <option value="">{{ $page->copy->text('FORM_NO_SUPERVISOR') }}</option>
                            @foreach ($adminPauseSettings->supervisors as $supervisor)
                            <option value="{{ $supervisor['id'] }}">{{ $supervisor['username'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-control">
                        <label class="label" for="regelarbeitszeit"><span class="label-text">{{ $page->copy->text('FORM_REGULAR_WORKING_HOURS') }}</span></label>
                        <input type="number" id="regelarbeitszeit" name="regelarbeitszeit" class="input input-bordered" step="0.01" required>
                    </div>
                    <div class="form-control">
                        <label class="label" for="ueberstunden"><span class="label-text">{{ $page->copy->text('FORM_OVERTIME') }}</span></label>
                        <input type="number" id="ueberstunden" name="ueberstunden" class="input input-bordered" step="0.01" value="0">
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
                            <td>{{ $user['department_name'] ?? '' }}</td><td>{{ $user['supervisor_name'] ?? '' }}</td>
                            <td>{{ $user['regelarbeitszeit'] }}</td><td>{{ $user['ueberstunden'] }}</td>
                            <td>
                                <button type="button" class="btn btn-ghost btn-sm" data-read-user="{{ $user['id'] }}" title="{{ $page->copy->text('BUTTON_EDIT') }}"><i class="fas fa-edit"></i></button>
                                <button type="button" class="btn btn-ghost btn-sm" title="{{ $page->copy->text('BUTTON_DELETE') }}" disabled aria-describedby="user-mutations-hold"><i class="fas fa-trash-alt"></i></button>
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
<dialog id="editUserModal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">{{ $page->copy->text('MODAL_TITLE_EDIT_USER') }}</h3>
        <div id="user-detail-hold" class="alert alert-info mb-4" role="note">Nur lesende Benutzerdetails. Änderungen und Passwortaktionen sind nicht verbunden; es gibt hier keine Speicherung.</div>
        <input type="hidden" id="edit_user_id">
        <div class="form-control mb-4"><label class="label" for="edit_username"><span class="label-text">{{ $page->copy->text('FORM_USERNAME') }}</span></label><input type="text" id="edit_username" class="input input-bordered" readonly></div>
        <div class="form-control mb-4"><label class="label" for="edit_email"><span class="label-text">{{ $page->copy->text('FORM_EMAIL') }}</span></label><input type="email" id="edit_email" class="input input-bordered" readonly></div>
        <div class="form-control mb-4"><label class="label" for="edit_role"><span class="label-text">{{ $page->copy->text('FORM_ROLE') }}</span></label>
            <select id="edit_role" class="select select-bordered" disabled>
                <option value="user">{{ $page->copy->text('FORM_ROLE_USER') }}</option><option value="admin">{{ $page->copy->text('FORM_ROLE_ADMIN') }}</option><option value="supervisor">{{ $page->copy->text('FORM_ROLE_SUPERVISOR') }}</option>
            </select>
        </div>
        <div class="form-control mb-4"><label class="label" for="edit_department"><span class="label-text">{{ $page->copy->text('FORM_DEPARTMENT') }}</span></label><select id="edit_department" class="select select-bordered" disabled></select></div>
        <div class="form-control mb-4"><label class="label" for="edit_supervisor"><span class="label-text">{{ $page->copy->text('FORM_SUPERVISOR') }}</span></label><select id="edit_supervisor" class="select select-bordered" disabled></select></div>
        <div class="form-control mb-4"><label class="label" for="edit_regelarbeitszeit"><span class="label-text">{{ $page->copy->text('FORM_REGULAR_WORKING_HOURS') }}</span></label>
            <p>Änderungen am Soll bitte unter <a href="work_models.php">Arbeitszeitmodelle</a> mit Gültigkeitsdatum speichern.</p><input readonly type="number" id="edit_regelarbeitszeit" class="input input-bordered" step="0.01">
        </div>
        <div class="form-control mb-4"><label class="label" for="edit_ueberstunden"><span class="label-text">{{ $page->copy->text('FORM_OVERTIME') }}</span></label><input type="number" id="edit_ueberstunden" class="input input-bordered" step="0.01" readonly></div>
        <div class="form-control mb-4"><label class="label" for="edit_password"><span class="label-text">{{ $page->copy->text('FORM_NEW_PASSWORD') }}</span></label><input type="password" id="edit_password" class="input input-bordered" value="" disabled aria-describedby="user-detail-hold"></div>
        <div class="modal-action">
            <button type="button" class="btn btn-primary" disabled aria-describedby="user-detail-hold">{{ $page->copy->text('BUTTON_SAVE_CHANGES') }}</button>
            <button type="button" class="btn" @if ($page->cspNonce === null) onclick="closeEditUserModal()" @else data-local-close-user @endif>{{ $page->copy->text('BUTTON_CANCEL') }}</button>
        </div>
    </div>
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
    // Existing get_user.php fresh-on-open interaction, with UI-only read projection.
    let userDetailRequest = 0;
    @if ($page->cspNonce !== null)
    document.querySelectorAll('[data-local-close-user]').forEach(function (button) { button.addEventListener('click', closeEditUserModal); });
    @endif
    document.querySelectorAll('[data-read-user]').forEach(function (button) {
        button.addEventListener('click', function () { editUser(button.dataset.readUser); });
    });
    async function editUser(userId) {
        const request = ++userDetailRequest;
        const modal = document.getElementById('editUserModal');
        const error = document.getElementById('user-detail-error');
        if (modal.open) modal.close();
        error.textContent = '';
        error.classList.add('hidden');
        ['edit_user_id', 'edit_username', 'edit_email', 'edit_role', 'edit_regelarbeitszeit', 'edit_ueberstunden', 'edit_password'].forEach(function (id) { document.getElementById(id).value = ''; });
        document.getElementById('edit_department').replaceChildren();
        document.getElementById('edit_supervisor').replaceChildren();
        try {
            const response = await fetch('get_user.php?id=' + encodeURIComponent(userId), {headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('Benutzerdetails nicht verfügbar. Bitte neu laden und erneut anmelden.');
            const data = await response.json();
            if (request !== userDetailRequest) return;
            if (!data || String(data.id) !== String(userId)) throw new Error('Benutzerdetails nicht verfügbar. Bitte neu laden.');
            document.getElementById('edit_user_id').value = data.id;
            document.getElementById('edit_username').value = data.username;
            document.getElementById('edit_email').value = data.email;
            document.getElementById('edit_role').value = data.role;
            document.getElementById('edit_department').replaceChildren(new Option(data.department_name ?? '', data.department_id ?? '', true, true));
            document.getElementById('edit_supervisor').replaceChildren(new Option(data.supervisor_name ?? (data.supervisor_id ? '' : {!! \Illuminate\Support\Js::from($page->copy->text('FORM_NO_SUPERVISOR')) !!}), data.supervisor_id || '', true, true));
            document.getElementById('edit_regelarbeitszeit').value = data.regelarbeitszeit;
            document.getElementById('edit_ueberstunden').value = data.ueberstunden;
            modal.showModal();
        } catch (failure) {
            if (request !== userDetailRequest) return;
            error.textContent = 'Benutzerdetails nicht verfügbar. Bitte neu laden und erneut anmelden.';
            error.classList.remove('hidden');
        }
    }
    function closeEditUserModal() {
        ++userDetailRequest;
        document.getElementById('editUserModal').close();
    }
</script>
@endsection
