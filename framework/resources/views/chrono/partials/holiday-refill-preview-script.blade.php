<script @if ($page->cspNonce !== null) nonce="{{ $page->cspNonce }}" @endif>
(() => {
    'use strict';
    const button = document.getElementById('holiday-refill-preview-button');
    const status = document.getElementById('holiday-refill-preview-status');
    const result = document.getElementById('holiday-refill-preview-result');
    const form = button && button.form;
    if (!form || !status || !result || typeof fetch !== 'function' || typeof FormData !== 'function') return;
    let sequence = 0;
    const connected = () => form.isConnected && button.isConnected;
    const fields = () => [form.elements.holiday_year.value, form.elements.holiday_json.value,
        form.elements.confirm_bw_source.checked];
    const node = (tag, text, cls = '') => {
        const item = document.createElement(tag);
        item.textContent = text;
        if (cls) item.className = cls;
        return item;
    };
    const unchanged = (snapshot) => snapshot.every((value, i) => value === fields()[i]);
    const show = (data) => {
        const states = {missing: 'Fehlt derzeit', unchanged: 'Identisch gespeichert', conflict: 'Abweichender Bestand', not_compared: 'Nicht vollständig verglichen'};
        if (!data || data.comparison_only !== true || data.region !== 'BW' || data.scope !== 'selected_year_prefix'
            || !Array.isArray(data.rows) || data.rows.length < 1 || data.rows.length > 366
            || !data.counts || !['missing', 'unchanged', 'conflict', 'extra_existing', 'not_compared'].every(
                (key) => Number.isInteger(data.counts[key]) && data.counts[key] >= 0 && data.counts[key] <= 367)
            || !data.rows.every((row) => row && typeof row.day === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(row.day)
                && typeof row.name === 'string' && row.name.length <= 250 && Object.hasOwn(states, row.status))) {
            throw new Error('Invalid preview');
        }
        result.replaceChildren();
        result.append(node('p', `Nur Vorabvergleich BW ${data.year}: ${data.counts.missing} als fehlend erkannt, ${data.counts.unchanged} identisch, ${data.counts.conflict} abweichend, ${data.counts.not_compared} wegen Scanlimit ungeklärt, ${data.counts.extra_existing} weitere geprüfte Bestandszeilen.`, 'font-semibold'));
        result.append(node('p', 'Diese Vorabprüfung speichert keine Feiertagszeile. Nur der gewählte Jahrespräfix wurde verglichen; keine Quellen-, Regions- oder Vollständigkeitszusage. Speichern prüft den dann aktuellen Bestand erneut und bleibt ein eigener bewusster Vorgang.', 'text-sm opacity-70 my-2'));
        if (data.has_conflicts || data.existing_scan_limited) {
            result.append(node('p', 'Bestandsabweichung oder begrenzter Scan: zuerst den gespeicherten Jahrgang prüfen. Es wird keine Teiländerung zugesagt.', 'text-warning font-semibold my-2'));
        }
        const table = document.createElement('table');table.className = 'table';
        const head = document.createElement('thead');const titles = document.createElement('tr');
        ['Datum der Quelle', 'Feiertag der Quelle', 'Vergleich jetzt'].forEach((text) => titles.append(node('th', text)));
        head.append(titles);table.append(head);const body = document.createElement('tbody');
        data.rows.forEach((row) => {const line = document.createElement('tr');
            line.append(node('td', row.day), node('td', row.name), node('td', states[row.status]));body.append(line);});
        table.append(body);const scroll = document.createElement('div');scroll.className = 'table-responsive';scroll.append(table);result.append(scroll);
    };
    form.addEventListener('input', () => {sequence++;button.disabled = false;result.replaceChildren();
        status.textContent = 'Eingaben geändert. Eine vorherige Vorabprüfung gilt nicht mehr; bei Bedarf bewusst erneut prüfen.';});
    button.addEventListener('click', async () => {
        if (!form.reportValidity()) return;
        const snapshot = fields();const current = ++sequence;button.disabled = true;result.replaceChildren();
        status.textContent = 'Bestand wird nur gelesen und verglichen …';
        const data = new FormData(form);data.delete('refill_holidays');data.set('preview_holidays', '1');
        try {
            const response = await fetch('admin.php', {method: 'POST', body: data, credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
            const answer = await response.json();
            if (!connected() || current !== sequence) return;
            if (!unchanged(snapshot)) {
                result.replaceChildren();status.textContent = 'Eingaben geändert. Bei Bedarf bewusst erneut prüfen.';return;
            }
            if (!response.ok || answer.success !== true) {
                status.textContent = answer && answer.success === false && typeof answer.message === 'string'
                    && answer.message.length <= 500 ? answer.message : 'Vorabprüfung nicht verfügbar. Neu laden und Eingaben prüfen.';
                return;
            }
            show(answer.data);status.textContent = 'Vorabprüfung gelesen · keine Feiertagszeile gespeichert.';
        } catch (_) {
            if (connected() && current === sequence) {
                result.replaceChildren();status.textContent = unchanged(snapshot)
                    ? 'Vorabprüfung nicht verfügbar. Neu laden und Eingaben prüfen; nichts wird automatisch erneut gesendet.'
                    : 'Eingaben geändert. Bei Bedarf bewusst erneut prüfen.';
            }
        } finally {if (connected() && current === sequence) button.disabled = false;}
    });
    button.disabled = false;
    status.textContent = 'Nur vorab prüfen liest den aktuellen Bestand. Es speichert keine Feiertagszeile.';
})();
</script>
