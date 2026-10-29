/* Shared presentation helpers for the existing Quodara Chrono interface.
 * Attendance mutations belong exclusively to attendance.js. Historical direct
 * save/edit/delete and client-clock/localStorage booking handlers are retired.
 */
(() => {
    'use strict';
    const ready = callback => document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', callback, {once: true}) : callback();
    const expandedDates = new Set();
    let tableRequest = 0;
    let requestedReview = null, requestedPage = null;

    function prepareTimeRecords(container) {
        container.querySelectorAll('.summary-row').forEach(row => {
            row.tabIndex = 0;
            row.setAttribute('role', 'button');
            const details = [...container.querySelectorAll('.details-row')]
                .find(detail => detail.dataset.date === row.dataset.date);
            if (!details) return;
            const expanded = expandedDates.has(row.dataset.date);
            row.setAttribute('aria-expanded', String(expanded));
            row.setAttribute('aria-label', `Arbeitszeiten vom ${row.dataset.date} ${expanded ? 'einklappen' : 'anzeigen'}`);
            details.style.display = expanded ? 'table-row' : 'none';
            row.querySelector('.fa-chevron-right')?.classList.toggle('rotate-90', expanded);
        });
        container.querySelectorAll('.btn-group button').forEach(button => {
            const text = button.textContent.trim();
            const labels = {'«': 'Erste Seite', '‹': 'Vorherige Seite', '›': 'Nächste Seite', '»': 'Letzte Seite'};
            button.setAttribute('aria-label', labels[text] || `Seite ${text}`);
            if (button.classList.contains('btn-active')) button.setAttribute('aria-current', 'page');
        });
    }

    // Same in-page Native date-range draft only, never stored or submitted here.
    // Laravel keeps its CSV form outside this replaceable table container.
    function csvPeriodControls(container) {
        const from = container.querySelector('#csv-period-from');
        const to = container.querySelector('#csv-period-to');
        const form = from?.form;
        const details = form?.closest('details');
        if (!form || to?.form !== form || !details || !container.contains(details)
            || from.type !== 'date' || to.type !== 'date'
            || from.name !== 'from' || to.name !== 'to'
            || form.method !== 'get' || form.getAttribute('action') !== 'export_csv.php') return null;
        return {from, to, details};
    }

    function tableMessage(container, message) {
        let status = document.getElementById('time-records-message');
        if (!status) {
            status = document.createElement('p');
            status.id = 'time-records-message';
            status.setAttribute('role', 'status');
            status.setAttribute('aria-live', 'polite');
            container.before(status);
        }
        status.textContent = message;
    }

    // Used by the existing server-rendered pagination buttons and attendance.js.
    window.updateTimeRecordsTable = async function(page, review) {
        const container = document.getElementById('timeRecordsContainer');
        if (!container) return true;
        const confirmedReview = container.dataset.review ?? 'all';
        const selectedReview = review ?? requestedReview ?? confirmedReview;
        if (!['all', 'review'].includes(selectedReview)) {
            tableMessage(container, 'Ungültige Listenansicht. Bitte Alle oder Prüfhinweise wählen.');
            return false;
        }
        // A refresh or old pagination click must not replace an in-flight
        // filter choice with the last confirmed view or an old-view page.
        const switchingView = selectedReview !== confirmedReview;
        const selectedPage = Math.max(1, Number.parseInt(switchingView ? '1'
            : page ?? requestedPage ?? container.dataset.page ?? '1', 10) || 1);
        requestedReview = selectedReview;
        requestedPage = selectedPage;
        const request = ++tableRequest;
        container.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(`get_time_records.php?page=${selectedPage}${selectedReview === 'review' ? '&review=1' : ''}`, {cache: 'no-store', redirect: 'error'});
            if (!response.ok) throw new Error('Arbeitszeiten konnten nicht geladen werden. Bitte erneut versuchen.');
            const html = await response.text();
            if (request !== tableRequest) return true;
            const restorePaginationFocus = container.contains(document.activeElement)
                && document.activeElement.matches('.btn-group button');
            const restoreFilterFocus = container.contains(document.activeElement)
                && document.activeElement.matches('[data-record-review-filter]');
            // Capture at the last possible moment, AFTER the latest-response guard:
            // an edit/clear while fetch was pending must win over its older value.
            const csvControls = csvPeriodControls(container);
            const csvDraft = csvControls ? {
                from: csvControls.from.value, to: csvControls.to.value,
                open: csvControls.details.open,
                focus: document.activeElement === csvControls.from ? 'from'
                    : document.activeElement === csvControls.to ? 'to' : null,
            } : null;
            container.innerHTML = html;
            if (csvDraft) {
                const currentCsv = csvPeriodControls(container);
                if (currentCsv) {
                    currentCsv.from.value = csvDraft.from;
                    currentCsv.to.value = csvDraft.to;
                    currentCsv.details.open = csvDraft.open;
                    if (csvDraft.focus) currentCsv[csvDraft.focus].focus();
                }
            }
            const reviewControl = container.querySelector('[data-record-review-filter]');
            container.dataset.page = reviewControl?.dataset.page ?? String(selectedPage);
            container.dataset.review = selectedReview;
            requestedReview = selectedReview;
            requestedPage = container.dataset.page;
            prepareTimeRecords(container);
            if (restorePaginationFocus) container.querySelector('[aria-current="page"]')?.focus();
            if (restoreFilterFocus) reviewControl?.focus();
            tableMessage(container, '');
            return true;
        } catch (error) {
            if (request === tableRequest) {
                const reviewControl = container.querySelector('[data-record-review-filter]');
                if (reviewControl) reviewControl.value = container.dataset.review ?? 'all';
                requestedReview = container.dataset.review ?? 'all';
                requestedPage = container.dataset.page ?? '1';
                tableMessage(container, error.message || 'Arbeitszeiten konnten nicht geladen werden.');
            }
            return false;
        } finally {
            if (request === tableRequest) container.removeAttribute('aria-busy');
        }
    };

    // The download endpoint remains server-authoritative and already CSV-safe.
    window.exportToCSV = function() { window.location.assign('export_csv.php'); };

    ready(() => {
        document.querySelectorAll('.main-title').forEach(title => {
            title.addEventListener('click', () => {
                const content = title.nextElementSibling;
                if (!content?.classList.contains('toggle-content')) return;
                if (window.jQuery) window.jQuery(content).slideToggle();
                else content.hidden = !content.hidden;
                const icon = title.querySelector('i.fas');
                icon?.classList.toggle('fa-chevron-down');
                icon?.classList.toggle('fa-chevron-up');
            });
        });

        document.getElementById('importDbButton')?.addEventListener('click', () => {
            document.getElementById('importModal')?.showModal();
        });
        document.getElementById('importButton')?.addEventListener('click', event => {
            const file = document.getElementById('dbFile');
            if (file?.files?.length) return;
            event.preventDefault();
            if (window.Swal) window.Swal.fire({icon: 'warning', title: 'Keine Datei ausgewählt', text: 'Bitte wählen Sie eine Datei aus.', confirmButtonText: 'OK'});
        });

        const container = document.getElementById('timeRecordsContainer');
        if (container) {
            container.addEventListener('change', event => {
                if (event.target.matches('[data-record-review-filter]')) {
                    void window.updateTimeRecordsTable(1, event.target.value);
                }
            });
            container.addEventListener('click', event => {
                if (event.target.closest('a, button, input, select, textarea')) return;
                const row = event.target.closest('.summary-row');
                if (!row) return;
                const date = row.dataset.date;
                if (expandedDates.has(date)) expandedDates.delete(date);
                else expandedDates.add(date);
                prepareTimeRecords(container);
            });
            container.addEventListener('keydown', event => {
                if ((event.key === 'Enter' || event.key === ' ') && event.target.matches('.summary-row')) {
                    event.preventDefault();
                    event.target.click();
                }
            });
            if (container.querySelector('table')) prepareTimeRecords(container);
            else void window.updateTimeRecordsTable(1);
        }
    });
})();
