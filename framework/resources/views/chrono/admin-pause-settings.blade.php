@extends('chrono.layouts.app')

@section('content')
<main class="container mx-auto px-4 py-8 chrono-page">
    <div class="alert alert-info mb-4" role="note">In dieser Laravel-Ansicht sind die vorhandene Pausenregel- und Abteilungsverwaltung verbunden. Benutzer, LDAP, Zugangsdaten und API-Token sind hier noch nicht zur Verwaltung angebunden.</div>
    @if ($adminPauseSettings->error)
    <div class="alert alert-error shadow-lg mb-4" role="alert"><div><i class="fas fa-exclamation-circle"></i><span>{{ $adminPauseSettings->error }}</span></div></div>
    @endif
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
            <button type="button" class="btn" onclick="closeEditDepartmentModal()">{{ $page->copy->text('BUTTON_CANCEL') }}</button>
        </div>
    </form>
</dialog>
<script>
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
<script>
    // Native department search/modal behavior only; no account/LDAP Admin JS.
    document.addEventListener('DOMContentLoaded', function () {
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
@endsection
