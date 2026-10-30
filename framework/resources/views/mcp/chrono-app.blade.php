<x-mcp::app :title="$title">
    <x-slot:head>
        <meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; connect-src 'none'; img-src 'none'; font-src 'none'; frame-src 'none'; object-src 'none'; base-uri 'none'; form-action 'none'">
        <style>
            :root { color-scheme: light dark; font: 15px/1.5 system-ui, sans-serif; background: var(--color-background-primary, Canvas); color: var(--color-text-primary, CanvasText); }
            * { box-sizing: border-box; }
            body { margin: 0; padding: 20px; }
            main { max-width: 960px; margin: auto; }
            header, .toolbar, .actions, .pagination { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
            header { justify-content: space-between; }
            h1 { font-size: 1.4rem; margin: 0; }
            h2 { font-size: 1.15rem; margin-top: 0; }
            p { margin: 8px 0 16px; }
            small, .muted { color: var(--color-text-secondary, GrayText); }
            button, input, select { font: inherit; color: inherit; background: var(--color-background-secondary, Canvas); border: 1px solid var(--color-border-primary, GrayText); border-radius: 7px; padding: 8px 12px; }
            button { cursor: pointer; }
            button:disabled { opacity: .5; cursor: default; }
            :focus-visible { outline: 3px solid #3677db; outline-offset: 3px; }
            .primary { background: #175bb5; color: white; border-color: #175bb5; }
            [role=tablist] { display: flex; gap: 6px; overflow-x: auto; padding: 5px 3px 10px; margin-top: 20px; }
            [role=tab] { white-space: nowrap; border-color: transparent; }
            [role=tab][aria-selected=true] { border-color: #3677db; font-weight: 650; }
            .card { border: 1px solid var(--color-border-primary, GrayText); border-radius: 12px; padding: 18px; margin-top: 12px; }
            .state { font-size: 1.65rem; font-weight: 650; margin: 6px 0; }
            #notice { min-height: 1.5em; overflow-wrap: anywhere; }
            #notice.error, #pending-box { color: var(--color-text-danger, #b93820); }
            .scroll { overflow-x: auto; }
            table { width: 100%; border-collapse: collapse; font-size: .9rem; }
            caption { text-align: left; padding: 10px 0; color: var(--color-text-secondary, GrayText); }
            th, td { text-align: left; vertical-align: top; padding: 10px 8px; border-bottom: 1px solid var(--color-border-primary, GrayText); overflow-wrap: anywhere; }
            .pagination { justify-content: flex-end; padding-top: 14px; }
            .toolbar { margin-bottom: 16px; }
            label { display: inline-flex; gap: 8px; align-items: center; }
            dialog { color: inherit; background: var(--color-background-primary, Canvas); border: 1px solid GrayText; border-radius: 12px; padding: 24px; max-width: min(92vw, 600px); }
            dialog::backdrop { background: #0008; }
            dl { display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; }
            dt { font-weight: 600; } dd { margin: 0; overflow-wrap: anywhere; }
            code { overflow-wrap: anywhere; }
            [hidden] { display: none !important; }
            @media (max-width: 520px) { body { padding: 12px; } .card { padding: 12px; } dl { grid-template-columns: 1fr; gap: 4px; } dd { margin-bottom: 8px; } }
        </style>
    </x-slot:head>

    <main id="chrono-app" aria-busy="true">
        <header>
            <div><h1>Quodara Chrono</h1><small data-i18n="ownOnly">Your own account</small></div>
            <label><span data-i18n="language">Language</span><select id="language"><option value="de">Deutsch</option><option value="en">English</option><option value="zh">中文</option></select></label>
        </header>
        <p id="identity" class="muted">—</p>
        <p id="notice" role="status" aria-live="polite" data-i18n="connecting">Connecting to the host…</p>
        <nav role="tablist" aria-label="Chrono">
            <button id="tab-attendance" role="tab" aria-selected="true" aria-controls="panel-attendance" data-tab="attendance" data-i18n="attendance">Attendance</button>
            <button id="tab-entries" role="tab" aria-selected="false" aria-controls="panel-entries" tabindex="-1" data-tab="entries" data-i18n="entries">Stored entries</button>
            <button id="tab-corrections" role="tab" aria-selected="false" aria-controls="panel-corrections" tabindex="-1" data-tab="corrections" data-i18n="corrections">Corrections</button>
            <button id="tab-models" role="tab" aria-selected="false" aria-controls="panel-models" tabindex="-1" data-tab="models" data-i18n="models">Work models</button>
        </nav>

        <section id="panel-attendance" class="card" role="tabpanel" aria-labelledby="tab-attendance" tabindex="0">
            <h2 data-i18n="attendance">Attendance</h2>
            <p id="attendance-state" class="state">—</p>
            <p><span data-i18n="record">Record</span>: <code id="record-id">—</code></p>
            <div class="actions">
                <button type="button" data-action="start" data-i18n="start" disabled>Start work</button>
                <button type="button" data-action="pause_start" data-i18n="pause_start" disabled>Start break</button>
                <button type="button" data-action="pause_end" data-i18n="pause_end" disabled>End break</button>
                <button type="button" data-action="end" data-i18n="end" disabled>End work</button>
                <button type="button" id="refresh-attendance" data-i18n="refresh" disabled>Refresh</button>
            </div>
            <p class="muted" data-i18n="serverTime">Times and state come from the server. Review every action before confirming.</p>
            <div id="receipt-warnings" class="card" role="status" aria-live="polite" hidden>
                <h3 data-i18n="receiptWarnings">Last confirmed end: review stored totals</h3>
                <p><span data-i18n="record">Record</span>: <code id="warning-record"></code> · <span data-i18n="event">Event</span>: <code id="warning-event"></code></p>
                <p id="receipt-pause-warning" hidden></p>
                <p id="receipt-overlap-warning" hidden></p>
                <p id="receipt-overlap-records" hidden></p>
            </div>
            <div id="pending-box" class="card" hidden>
                <p data-i18n="unknown">The outcome is unknown. Check attendance and retry only this same event.</p>
                <p><span data-i18n="event">Event</span>: <code id="pending-id"></code></p>
                <button type="button" id="retry-event" data-i18n="retry" disabled>Retry same event</button>
            </div>
        </section>

        <section id="panel-entries" class="card" role="tabpanel" aria-labelledby="tab-entries" tabindex="0" hidden>
            <h2 data-i18n="entries">Stored entries</h2>
            <form id="entries-filter" class="toolbar">
                <label><span data-i18n="from">From</span><input type="date" id="entries-from"></label>
                <label><span data-i18n="through">Through</span><input type="date" id="entries-through"></label>
                <button type="submit" data-i18n="load" disabled>Load</button>
            </form>
            <p class="muted" data-i18n="storedOnly">Stored values only. No balances or entitlements are calculated.</p>
            <div id="entries-result" class="scroll"></div><div id="entries-pages" class="pagination"></div>
        </section>

        <section id="panel-corrections" class="card" role="tabpanel" aria-labelledby="tab-corrections" tabindex="0" hidden>
            <h2 data-i18n="corrections">Corrections</h2>
            <form id="corrections-filter" class="toolbar">
                <label><span data-i18n="status">Status</span><select id="corrections-status"><option value="all" data-i18n="all">All</option><option value="pending" data-i18n="pending">Pending</option><option value="approved" data-i18n="approved">Approved</option><option value="rejected" data-i18n="rejected">Rejected</option><option value="withdrawn" data-i18n="withdrawn">Withdrawn</option></select></label>
                <button type="submit" data-i18n="load" disabled>Load</button>
            </form>
            <div id="corrections-result" class="scroll"></div><div id="corrections-pages" class="pagination"></div>
        </section>

        <section id="panel-models" class="card" role="tabpanel" aria-labelledby="tab-models" tabindex="0" hidden>
            <h2 data-i18n="models">Work models</h2>
            <form id="models-filter" class="toolbar">
                <label><span data-i18n="date">Date</span><input type="date" id="model-date"></label>
                <button type="submit" data-i18n="load" disabled>Load</button>
            </form>
            <p class="muted" data-i18n="modelNote">Stored weekly minutes, Monday to Sunday. Holiday adjustments and legacy fallback are not applied.</p>
            <h3 data-i18n="effective">Effective model</h3><div id="effective-result" class="scroll"></div>
            <h3 data-i18n="versions">Stored versions</h3><div id="models-result" class="scroll"></div><div id="models-pages" class="pagination"></div>
        </section>
    </main>

    <dialog id="confirmation" aria-labelledby="confirmation-title">
        <h2 id="confirmation-title" data-i18n="review">Review action</h2>
        <dl id="confirmation-details"></dl>
        <label id="pause-confirmation" hidden><input type="checkbox" id="confirm-end-pause"><span data-i18n="endPause">I confirm ending the active break and work session.</span></label>
        <p data-i18n="confirmNote">This sends the reviewed event using your connected profile.</p>
        <div class="actions"><button id="confirm-action" class="primary" data-i18n="confirm">Confirm</button><button id="cancel-action" data-i18n="cancel">Cancel</button></div>
    </dialog>

    @include('mcp.chrono-app-script')
</x-mcp::app>
