@extends('chrono.layouts.app')

@section('content')
<main class="container mx-auto px-4 py-8 chrono-page">
    <div class="alert alert-info mb-4" role="note">In dieser Laravel-Ansicht ist nur die vorhandene Pausenregel-Verwaltung verbunden. Benutzer, Abteilungen, LDAP, Zugangsdaten und API-Token sind hier noch nicht zur Verwaltung angebunden.</div>
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
</main>
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
@endsection
