<p id="account-lifecycle-stale" class="alert alert-info mb-4" role="note" hidden>Bitte die Seite neu laden, bevor der Kontostatus erneut geändert wird.</p>
<dialog id="accountLifecycleModal" class="modal" aria-labelledby="account-lifecycle-title" aria-describedby="account-lifecycle-description">
    <form method="post" class="modal-box" id="account-lifecycle-form">
        @csrf
        <input type="hidden" name="change_account_lifecycle" value="1">
        <input type="hidden" name="user_id" id="account-lifecycle-user">
        <input type="hidden" name="confirmation" id="account-lifecycle-confirmation">
        <h3 class="font-bold text-lg mb-4" id="account-lifecycle-title">Kontostatus ändern</h3>
        <p id="account-lifecycle-description" class="mb-4"></p>
        <x-chrono-help><p class="text-sm mb-4">Historie, Anträge und offene Arbeitszeiten bleiben erhalten; laufende Zeiten werden nicht automatisch beendet. Aktivierung erlaubt nur frische Anmeldung: alte Sitzungen, Tokens und Grants werden nicht wieder gültig. Separat gesperrte Terminal-Geräte oder Zugänge bleiben gesperrt. Der letzte lokal anmeldbare Admin darf nicht gesperrt werden. Bei Änderung des eigenen Kontos erfolgt die Abmeldung.</p></x-chrono-help>
        <label class="label" for="account-lifecycle-reason"><span class="label-text">Begründung (keine Passwörter oder Zugangsdaten)</span></label>
        <input type="text" class="input input-bordered w-full" id="account-lifecycle-reason" name="reason" maxlength="500" required autocomplete="off">
        <p class="text-sm opacity-70 mt-3">Bei unklarer Antwort nicht erneut senden, sondern neu anmelden und den gespeicherten Stand prüfen.</p>
        <div class="modal-action">
            <button type="submit" class="btn btn-primary" id="account-lifecycle-submit" disabled>Kontostatus ändern</button>
            <button type="button" class="btn" id="account-lifecycle-cancel">Abbrechen</button>
        </div>
    </form>
</dialog>
<script @if ($page->cspNonce !== null) nonce="{{ $page->cspNonce }}" @endif>
(function () {
    const modal = document.getElementById('accountLifecycleModal');
    const form = document.getElementById('account-lifecycle-form');
    const submit = document.getElementById('account-lifecycle-submit');
    const actions = document.querySelectorAll('[data-account-lifecycle]');
    const labels = { active: 'Aktiv', inactive: 'Deaktiviert', departed: 'Ausgetreten' };
    const verbs = { active: 'Aktivieren', inactive: 'Deaktivieren', departed: 'Austritt erfassen' };
    let ready = false, submitted = false;
    function clear() {
        ready = false; form.reset(); submit.disabled = true;
        document.getElementById('account-lifecycle-user').value = '';
        document.getElementById('account-lifecycle-confirmation').value = '';
        document.getElementById('account-lifecycle-description').textContent = '';
    }
    actions.forEach(function (button) {
        button.addEventListener('click', function () {
            if (submitted) return;
            clear();
            document.getElementById('account-lifecycle-user').value = button.dataset.userId;
            document.getElementById('account-lifecycle-confirmation').value = button.dataset.confirmation;
            document.getElementById('account-lifecycle-description').textContent = button.dataset.username + ' (Konto #' + button.dataset.userId + '): ' + labels[button.dataset.status] + ' → ' + labels[button.dataset.nextStatus] + '.';
            submit.textContent = verbs[button.dataset.nextStatus]; ready = true; submit.disabled = false;
            modal.showModal();
        });
    });
    document.getElementById('account-lifecycle-cancel').addEventListener('click', function () { modal.close(); });
    modal.addEventListener('close', function () { if (!submitted) clear(); });
    form.addEventListener('submit', function (event) {
        if (!ready || submitted) { event.preventDefault(); return; }
        submitted = true; ready = false; submit.disabled = true;
        actions.forEach(function (button) { button.disabled = true; });
        // Keep posted controls intact for normal browser serialization. No fetch/retry.
    });
    window.addEventListener('pageshow', function (event) {
        if (modal.open) modal.close();
        clear();
        if (event.persisted) {
            submitted = true;
            actions.forEach(function (button) { button.disabled = true; });
            document.getElementById('account-lifecycle-stale').hidden = false;
        }
    });
}());
</script>
