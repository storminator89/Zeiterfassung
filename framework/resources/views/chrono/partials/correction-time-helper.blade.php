<script>
(function () {
    'use strict';
    const helper = document.getElementById('correction-time-helper');
    const form = document.getElementById('correction-request-form');
    if (!helper || !form) return;
    const fieldset = form.querySelector('fieldset');
    const cancel = form.querySelector('[name="cancel"]');
    if (!fieldset || fieldset.disabled || !cancel) return;

    let berlin;
    try {
        berlin = new Intl.DateTimeFormat('en-GB-u-ca-gregory-nu-latn', {
            timeZone: 'Europe/Berlin', year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23'
        });
        if (berlin.resolvedOptions().timeZone !== 'Europe/Berlin'
            || berlin.resolvedOptions().hourCycle !== 'h23') return;
    } catch (_) { return; } // Manual ISO fields remain available without browser zone support.

    function wallTime(instant) {
        const parts = {};
        berlin.formatToParts(new Date(instant)).forEach(function (part) { parts[part.type] = part.value; });
        return parts.year.padStart(4, '0') + '-' + parts.month + '-' + parts.day
            + 'T' + parts.hour + ':' + parts.minute + ':' + parts.second;
    }

    function choices(value) {
        if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/.test(value) || value.startsWith('0000-')) return null;
        const local = value.length === 16 ? value + ':00' : value;
        // Parse ONLY as UTC for component arithmetic; never use the device's local zone.
        const nominal = Date.parse(local + 'Z');
        if (!Number.isFinite(nominal) || new Date(nominal).toISOString().slice(0, 19) !== local) return null;
        const offsets = new Set();
        // Discover nearby Berlin offsets from the browser's IANA data, then verify
        // every candidate by a full fixed-zone round trip. No gap is normalized.
        for (let hour = -48; hour <= 48; hour += 6) {
            const sample = nominal + hour * 3600000;
            const offset = Date.parse(wallTime(sample) + 'Z') - sample;
            if (Number.isInteger(offset / 60000) && Math.abs(offset) <= 14 * 3600000) offsets.add(offset);
        }
        return Array.from(offsets).map(function (offset) {
            const instant = nominal - offset;
            if (wallTime(instant) !== local) return null;
            const minutes = Math.abs(offset / 60000);
            const suffix = (offset < 0 ? '-' : '+') + String(Math.floor(minutes / 60)).padStart(2, '0')
                + ':' + String(minutes % 60).padStart(2, '0');
            return { instant: instant, value: local + suffix, offset: suffix };
        }).filter(Boolean).sort(function (a, b) { return a.instant - b.instant; });
    }

    const groups = [];
    try {
        helper.querySelectorAll('[data-correction-time]').forEach(function (group) {
            const name = group.dataset.correctionTime;
            const raw = document.getElementById(name);
            const local = document.getElementById(name + '-local');
            const occurrence = document.getElementById(name + '-occurrence');
            const occurrenceBox = group.querySelector('[data-time-occurrence]');
            const status = document.getElementById(name + '-time-status');
            const apply = group.querySelector('[data-time-apply]');
            if (!raw || !local || local.type !== 'datetime-local' || !occurrence || !occurrenceBox || !status || !apply) throw new Error();
            let current = [];
            let applying = false;

            function selected(items) {
                return items.length === 1 ? items[0] : items.find(function (item) { return item.value === occurrence.value; });
            }

            function updateButton() {
                apply.disabled = fieldset.disabled || cancel.checked || !selected(current);
            }

            function refresh() {
                apply.disabled = true;
                occurrenceBox.hidden = true;
                occurrence.replaceChildren();
                current = [];
                local.disabled = cancel.checked || fieldset.disabled;
                occurrence.disabled = local.disabled;
                if (!local.value) { status.textContent = ''; return; }
                try { current = choices(local.value); }
                catch (_) { current = null; }
                if (current === null) { current = []; status.textContent = 'Datum und Uhrzeit prüfen.'; return; }
                if (!current.length) { status.textContent = 'Diese Uhrzeit gibt es in Berlin nicht. Bitte eine andere Uhrzeit wählen.'; return; }
                if (current.length > 1) {
                    const placeholder = document.createElement('option');
                    placeholder.value = '';
                    placeholder.textContent = 'Bitte ausdrücklich wählen';
                    occurrence.append(placeholder);
                    current.forEach(function (item, index) {
                        const option = document.createElement('option');
                        option.value = item.value;
                        option.textContent = (index === 0 ? 'Erstes Mal' : 'Zweites Mal') + ' · UTC' + item.offset;
                        occurrence.append(option);
                    });
                    occurrence.value = '';
                    occurrenceBox.hidden = false;
                    status.textContent = 'Diese Uhrzeit kommt zweimal vor.';
                } else { status.textContent = ''; }
                updateButton();
            }

            function copyFromLocal() {
                if (fieldset.disabled || cancel.checked) return;
                let fresh;
                try { fresh = choices(local.value); } catch (_) { fresh = null; }
                const choice = fresh && selected(fresh);
                // A changed local input must never submit an older hidden value.
                // Repeated Berlin hours require the explicit occurrence selection.
                raw.value = choice ? choice.value : '';
                applying = true;
                try { raw.dispatchEvent(new Event('input', { bubbles: true })); }
                finally { applying = false; }
            }
            local.addEventListener('input', function () { refresh(); copyFromLocal(); });
            local.addEventListener('change', function () { refresh(); copyFromLocal(); });
            occurrence.addEventListener('change', function () { updateButton(); copyFromLocal(); });
            apply.hidden = true;
            raw.addEventListener('input', function () {
                if (applying) return;
                local.value = '';
                refresh();
            });
            apply.addEventListener('click', function () {
                if (fieldset.disabled || cancel.checked) return;
                let fresh;
                try { fresh = choices(local.value); } catch (_) { fresh = null; }
                const choice = fresh && selected(fresh);
                if (!choice) { refresh(); return; }
                // Only an explicit click copies a verified ISO value. No submission,
                // account lookup, storage, network request or correction calculation.
                raw.value = choice.value;
                applying = true;
                try { raw.dispatchEvent(new Event('input', { bubbles: true })); }
                finally { applying = false; }
                status.textContent = 'Übernommen. ISO-Feld unten prüfen.';
                raw.focus();
            });
            groups.push({ local: local, refresh: refresh });
            refresh();
        });
        if (groups.length !== 2) return;
    } catch (_) { return; }

    function syncCancel() {
        helper.hidden = cancel.checked || fieldset.disabled;
        if (cancel.checked) { helper.open = false; groups.forEach(function (group) { group.local.value = ''; }); }
        groups.forEach(function (group) { group.refresh(); });
    }
    cancel.addEventListener('change', syncCancel);
    syncCancel();
}());
</script>
