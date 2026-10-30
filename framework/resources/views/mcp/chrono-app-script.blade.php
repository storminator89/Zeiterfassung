<script>
(() => {
    'use strict';
    const words = {
        en: {
            ownOnly: 'Your own account', language: 'Language', connecting: 'Connecting to the host…', attendance: 'Attendance', entries: 'Stored entries', corrections: 'Corrections', models: 'Work models',
            record: 'Record', start: 'Start work', pause_start: 'Start break', pause_end: 'End break', end: 'End work', refresh: 'Refresh',
            serverTime: 'Times and state come from the server. Review every action before confirming.', unknown: 'The outcome is unknown. Check attendance and retry only this same event.', event: 'Event', retry: 'Retry same event',
            from: 'From', through: 'Through', load: 'Load', storedOnly: 'Stored values only. No balances or entitlements are calculated.', status: 'Status', all: 'All', pending: 'Pending', approved: 'Approved', rejected: 'Rejected', withdrawn: 'Withdrawn', date: 'Date',
            modelNote: 'Stored weekly minutes, Monday to Sunday. Holiday adjustments and legacy fallback are not applied.', effective: 'Effective model', versions: 'Stored versions', review: 'Review action', endPause: 'I confirm ending the active break and work session.',
            confirmNote: 'This sends the reviewed event using your connected profile.', confirm: 'Confirm', cancel: 'Cancel', absent: 'Not working', working: 'Working', paused: 'On break', profile: 'Profile', action: 'Action', binding: 'Profile binding',
            ready: 'Up to date.', loading: 'Loading…', failed: 'The request could not be confirmed. Refresh to check the server.', changed: 'The profile changed. Previous data and confirmations were cleared. Reopen the app with the intended profile.',
            noData: 'No stored records.', id: 'ID', startTime: 'Start', endTime: 'End', pause: 'Break (stored)', description: 'Description', location: 'Location', request: 'Request', created: 'Created (epoch)', reviewed: 'Reviewed (epoch)', validFrom: 'Valid from',
            monday: 'Mon', tuesday: 'Tue', wednesday: 'Wed', thursday: 'Thu', friday: 'Fri', saturday: 'Sat', sunday: 'Sun', previous: 'Previous', next: 'Next', pageNote: 'Live pages; records can move between requests.',
            confirmed: 'The server confirmed the event.', eventMismatch: 'The server did not confirm this exact event. Retry only the same event.', noCrypto: 'Secure event IDs are unavailable in this host.', dateOrder: 'The end date must not precede the start date.',
            expired: 'Attendance or profile changed. Review a fresh action.', hostRequired: 'An MCP Apps host is required. The text tools remain available.', kind: 'Absence kind (stored)', minutes: 'Planned minutes (stored)',
        },
        de: {
            ownOnly: 'Nur das eigene Konto', language: 'Sprache', connecting: 'Verbindung zum Host wird hergestellt…', attendance: 'Anwesenheit', entries: 'Gespeicherte Zeiten', corrections: 'Korrekturen', models: 'Arbeitsmodelle',
            record: 'Datensatz', start: 'Arbeit beginnen', pause_start: 'Pause beginnen', pause_end: 'Pause beenden', end: 'Arbeit beenden', refresh: 'Aktualisieren',
            serverTime: 'Zeiten und Status stammen vom Server. Jede Aktion vor dem Bestätigen prüfen.', unknown: 'Das Ergebnis ist unklar. Anwesenheit prüfen und nur dasselbe Ereignis erneut senden.', event: 'Ereignis', retry: 'Dasselbe Ereignis erneut senden',
            from: 'Von', through: 'Bis', load: 'Laden', storedOnly: 'Nur gespeicherte Werte. Keine Berechnung von Salden oder Ansprüchen.', status: 'Status', all: 'Alle', pending: 'Offen', approved: 'Genehmigt', rejected: 'Abgelehnt', withdrawn: 'Zurückgezogen', date: 'Datum',
            modelNote: 'Gespeicherte Wochenminuten, Montag bis Sonntag. Ohne Feiertagsanpassung oder Altmodell-Ersatz.', effective: 'Gültiges Modell', versions: 'Gespeicherte Versionen', review: 'Aktion prüfen', endPause: 'Ich bestätige das Ende der laufenden Pause und der Arbeit.',
            confirmNote: 'Das geprüfte Ereignis wird mit dem verbundenen Profil gesendet.', confirm: 'Bestätigen', cancel: 'Abbrechen', absent: 'Nicht anwesend', working: 'Bei der Arbeit', paused: 'In Pause', profile: 'Profil', action: 'Aktion', binding: 'Profilbindung',
            ready: 'Aktueller Stand.', loading: 'Wird geladen…', failed: 'Die Anfrage konnte nicht bestätigt werden. Den Serverstatus erneut abrufen.', changed: 'Das Profil hat sich geändert. Vorherige Daten und Bestätigungen wurden verworfen. Die App mit dem gewünschten Profil neu öffnen.',
            noData: 'Keine gespeicherten Datensätze.', id: 'ID', startTime: 'Beginn', endTime: 'Ende', pause: 'Pause (gespeichert)', description: 'Beschreibung', location: 'Standort', request: 'Antrag', created: 'Erstellt (Epoch)', reviewed: 'Geprüft (Epoch)', validFrom: 'Gültig ab',
            monday: 'Mo', tuesday: 'Di', wednesday: 'Mi', thursday: 'Do', friday: 'Fr', saturday: 'Sa', sunday: 'So', previous: 'Zurück', next: 'Weiter', pageNote: 'Aktuelle Seiten; Datensätze können sich zwischen Abrufen verschieben.',
            confirmed: 'Der Server hat das Ereignis bestätigt.', eventMismatch: 'Der Server hat dieses Ereignis nicht eindeutig bestätigt. Nur dasselbe Ereignis erneut senden.', noCrypto: 'Sichere Ereignis-IDs sind in diesem Host nicht verfügbar.', dateOrder: 'Das Enddatum darf nicht vor dem Startdatum liegen.',
            expired: 'Anwesenheit oder Profil hat sich geändert. Eine neue Aktion prüfen.', hostRequired: 'Ein MCP-Apps-Host ist erforderlich. Die Textwerkzeuge bleiben verfügbar.', kind: 'Abwesenheitsart (gespeichert)', minutes: 'Planminuten (gespeichert)',
        },
        zh: {
            ownOnly: '仅限自己的账户', language: '语言', connecting: '正在连接主机…', attendance: '出勤', entries: '已存工时', corrections: '更正', models: '工作模式',
            record: '记录', start: '开始工作', pause_start: '开始休息', pause_end: '结束休息', end: '结束工作', refresh: '刷新',
            serverTime: '时间和状态均来自服务器。请在确认前核对每项操作。', unknown: '结果尚未确认。请检查出勤状态，仅重试同一事件。', event: '事件', retry: '重试同一事件',
            from: '开始日期', through: '结束日期', load: '加载', storedOnly: '仅显示已存储的值，不计算余额或权益。', status: '状态', all: '全部', pending: '待审核', approved: '已批准', rejected: '已拒绝', withdrawn: '已撤回', date: '日期',
            modelNote: '已存每周分钟数，周一至周日。不应用节假日调整或旧模式替代。', effective: '生效模式', versions: '已存版本', review: '核对操作', endPause: '我确认结束当前休息及工作。',
            confirmNote: '使用当前连接的配置发送已核对事件。', confirm: '确认', cancel: '取消', absent: '未工作', working: '工作中', paused: '休息中', profile: '账户', action: '操作', binding: '配置绑定',
            ready: '已更新。', loading: '正在加载…', failed: '无法确认请求，请刷新以检查服务器状态。', changed: '连接配置已更改，之前的数据和确认已清除。请使用所需配置重新打开应用。',
            noData: '没有已存记录。', id: '编号', startTime: '开始', endTime: '结束', pause: '休息（已存）', description: '说明', location: '地点', request: '申请', created: '创建（纪元秒）', reviewed: '审核（纪元秒）', validFrom: '生效日期',
            monday: '一', tuesday: '二', wednesday: '三', thursday: '四', friday: '五', saturday: '六', sunday: '日', previous: '上一页', next: '下一页', pageNote: '实时分页，记录可能在两次请求之间变动。',
            confirmed: '服务器已确认此事件。', eventMismatch: '服务器尚未确认此确切事件，请仅重试同一事件。', noCrypto: '此主机无法生成安全事件编号。', dateOrder: '结束日期不能早于开始日期。',
            expired: '出勤状态或连接配置已更改，请重新核对操作。', hostRequired: '需要 MCP Apps 主机，仍可使用文本工具。', kind: '缺勤类型（已存）', minutes: '计划分钟（已存）',
        },
    };
    const $ = id => document.getElementById(id);
    let language = (navigator.language || 'en').slice(0, 2);
    if (!words[language]) language = 'en';
    const t = key => words[language][key] || words.en[key] || key;
    let app, binding = null, identity = null, attendance = null, prepared = null, pending = null, busy = false, stopped = false, tab = 'attendance';
    let latestEndWarnings = null;
    let noticeKey = 'connecting';
    const pages = {entries: null, corrections: null, models: null, effective: null};
    const offsets = {entries: 0, corrections: 0, models: 0};
    const tools = {start: 'chrono_start', pause_start: 'chrono_pause_start', pause_end: 'chrono_pause_end', end: 'chrono_end'};
    const storageKey = 'chrono.mcp.pending.v1';
    words.en.corruptPending = 'A saved unconfirmed event cannot be read. Use the text tools to reconcile it before clearing this host session.';
    words.de.corruptPending = 'Ein gespeichertes unbestätigtes Ereignis ist nicht lesbar. Vor dem Löschen dieser Hostsitzung mit den Textwerkzeugen abgleichen.';
    words.zh.corruptPending = '无法读取已保存的未确认事件。清除此主机会话前，请先使用文本工具核对。';
    Object.assign(words.en, {receiptWarnings: 'Last confirmed end: review stored totals', overlappingRecords: 'Overlapping records'});
    Object.assign(words.de, {receiptWarnings: 'Letztes bestätigtes Ende: gespeicherte Summen prüfen', overlappingRecords: 'Überlappende Datensätze'});
    Object.assign(words.zh, {receiptWarnings: '上次已确认结束：请核对已存总计', overlappingRecords: '重叠记录'});
    const exactId = value => typeof value === 'string' && /^[1-9][0-9]{0,18}$/.test(value) && (value.length < 19 || value <= '9223372036854775807');
    const profileId = value => typeof value === 'string' && /^[a-f0-9]{64}$/.test(value);
    const eventId = value => typeof value === 'string' && /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i.test(value);
    function validPending(value) {
        const args = value?.args;
        return value && Object.hasOwn(tools, value.action) && value.tool === tools[value.action] && args && profileId(args.profile_binding)
            && eventId(args.event_id) && args.confirmed_event_id === args.event_id
            && (value.action === 'start' ? args.expected_record === undefined && args.confirmed_record_id === undefined : exactId(args.expected_record) && args.confirmed_record_id === args.expected_record)
            && Object.keys(args).every(key => ['event_id', 'confirmed_event_id', 'profile_binding', 'expected_record', 'confirmed_record_id', 'confirm_end_pause'].includes(key))
            && (args.confirm_end_pause === undefined || (value.action === 'end' && typeof args.confirm_end_pause === 'boolean'));
    }
    try {
        const raw = sessionStorage.getItem(storageKey);
        if (raw !== null) {
            try { const saved = JSON.parse(raw); if (!validPending(saved)) throw new Error('invalid_pending'); pending = saved; }
            catch (_) { stopped = true; noticeKey = 'corruptPending'; }
        }
    } catch (_) { /* Opaque sandbox: keep the in-memory receipt. */ }
    function persist() { try { if (pending) sessionStorage.setItem(storageKey, JSON.stringify(pending)); else sessionStorage.removeItem(storageKey); } catch (_) {} }
    function notice(key, error = false) { noticeKey = key; $('notice').textContent = t(key); $('notice').classList.toggle('error', error); }
    function invalidate() { prepared = null; if ($('confirmation').open) $('confirmation').close(); }
    function controls() {
        $('chrono-app').setAttribute('aria-busy', String(busy));
        document.querySelectorAll('[data-action]').forEach(button => {
            button.disabled = busy || stopped || !!pending || !identity || !attendance?.workflow?.allowed_actions?.includes(button.dataset.action);
        });
        document.querySelectorAll('form button, .pagination button').forEach(button => { button.disabled = busy || stopped || !binding; });
        $('refresh-attendance').disabled = busy || stopped || !app;
        $('retry-event').disabled = busy || stopped || !pending || !identity || pending.args.profile_binding !== binding;
        $('pending-box').hidden = !pending;
        $('pending-id').textContent = pending?.args.event_id || '';
        $('confirm-action').disabled = busy || !prepared || ($('pause-confirmation').hidden === false && !$('confirm-end-pause').checked);
    }
    function renderAttendance() {
        $('identity').textContent = identity ? `${identity.username} · #${identity.id}` : '—';
        $('attendance-state').textContent = attendance ? t(attendance.state) : '—';
        $('record-id').textContent = attendance?.record_id_string || '—';
        const warning = latestEndWarnings;
        $('receipt-warnings').hidden = !warning;
        $('warning-record').textContent = warning?.record || '';
        $('warning-event').textContent = warning?.event || '';
        [['receipt-pause-warning', warning?.pause], ['receipt-overlap-warning', warning?.overlap],
            ['receipt-overlap-records', warning?.records.length ? `${t('overlappingRecords')}: ${warning.records.join(', ')}` : '']].forEach(([id, text]) => {
                $(id).textContent = text || ''; $(id).hidden = !text;
            });
        controls();
    }
    function translate() {
        document.documentElement.lang = language;
        $('language').value = language;
        document.querySelectorAll('[data-i18n]').forEach(node => { node.textContent = t(node.dataset.i18n); });
        $('notice').textContent = t(noticeKey);
        renderAttendance(); renderPages();
    }
    function decode(result) {
        if (result?.structuredContent && typeof result.structuredContent === 'object') return result.structuredContent;
        for (const item of result?.content || []) {
            if (item.type === 'text') { try { const value = JSON.parse(item.text); if (value && typeof value === 'object') return value; } catch (_) {} }
        }
        throw new Error('invalid_response');
    }
    function accept(data) {
        if (data.success !== true || data.scope !== 'own' || !profileId(data.profile_binding)) throw new Error('refused');
        const expectedBinding = binding || pending?.args.profile_binding;
        if (expectedBinding && expectedBinding !== data.profile_binding) {
            stopped = true; identity = attendance = null; invalidate();
            latestEndWarnings = null;
            Object.keys(pages).forEach(key => { pages[key] = null; });
            renderPages(); renderAttendance(); notice('changed', true);
            throw new Error('profile_changed');
        }
        binding = data.profile_binding;
        return data;
    }
    async function call(name, args = {}) {
        let timer;
        try {
            const result = await Promise.race([app.callServerTool(name, args), new Promise((_, reject) => { timer = setTimeout(() => reject(new Error('unconfirmed')), 20000); })]);
            const data = decode(result);
            if (result.isError || data.success !== true) throw new Error('unconfirmed');
            return accept(data);
        } finally { clearTimeout(timer); }
    }
    async function readAttendance() {
        identity = attendance = null; renderAttendance();
        const who = await call('chrono_identity');
        if (!Array.isArray(who.data) || who.data.length !== 1 || !exactId(who.data[0].id) || typeof who.data[0].username !== 'string') throw new Error('invalid_identity');
        const status = await call('chrono_attendance', {workflow: true});
        if (!['absent', 'working', 'paused'].includes(status.state) || !Array.isArray(status.workflow?.allowed_actions)
            || !Array.isArray(status.workflow?.confirmation_required_actions)
            || (status.state === 'absent' ? status.workflow.expected_record !== null || status.record_id_string !== null : !exactId(status.workflow.expected_record) || status.workflow.expected_record !== status.record_id_string)) throw new Error('invalid_status');
        identity = who.data[0]; attendance = status; renderAttendance();
    }
    async function run(operation) {
        if (busy || stopped) return;
        busy = true; controls(); notice('loading');
        try { await operation(); if (!stopped) notice(pending ? 'unknown' : 'ready', !!pending); }
        catch (error) { if (!stopped) notice(pending ? 'unknown' : ['expired', 'dateOrder', 'noCrypto'].includes(error.message) ? error.message : 'failed', true); }
        finally { busy = false; renderAttendance(); }
    }
    function rowTable(target, rows, columns) {
        const node = $(target); node.replaceChildren();
        if (!rows?.length) { const empty = document.createElement('p'); empty.textContent = t('noData'); node.append(empty); return; }
        const table = document.createElement('table'), head = document.createElement('thead'), body = document.createElement('tbody'), header = document.createElement('tr');
        columns.forEach(([label]) => { const th = document.createElement('th'); th.scope = 'col'; th.textContent = t(label); header.append(th); });
        head.append(header);
        rows.forEach(row => {
            const tr = document.createElement('tr');
            columns.forEach(([, field]) => { const td = document.createElement('td'); const value = typeof field === 'function' ? field(row) : row[field]; td.textContent = value === null || value === undefined ? '—' : String(value); tr.append(td); });
            body.append(tr);
        });
        table.append(head, body); node.append(table);
    }
    const modelColumns = () => [['id', 'model_id'], ['validFrom', 'valid_from'], ...['monday','tuesday','wednesday','thursday','friday','saturday','sunday'].map((day, i) => [day, row => row.weekday_minutes?.[i]])];
    function renderPages() {
        rowTable('entries-result', pages.entries?.data, [['id','id'],['startTime','startzeit'],['endTime','endzeit'],['pause','pause'],['description','beschreibung'],['location','standort'],['kind','absence_kind'],['minutes','planned_minutes']]);
        rowTable('corrections-result', pages.corrections?.data, [['request','request_id'],['record','record_id'],['status',row => t(row.status)],['created','created_at_epoch'],['reviewed','reviewed_at_epoch']]);
        rowTable('effective-result', pages.effective?.data ? [pages.effective.data] : [], modelColumns());
        rowTable('models-result', pages.models?.data, modelColumns());
        ['entries','corrections','models'].forEach(kind => {
            const node = $(`${kind}-pages`); node.replaceChildren();
            if (!pages[kind]) return;
            const hint = document.createElement('small'); hint.textContent = t('pageNote'); node.append(hint);
            const previous = offsets[kind] > 0 ? Math.max(0, offsets[kind] - 25) : null;
            const next = pages[kind].pagination?.next_offset;
            [['previous', previous], ['next', next]].forEach(([label, offset]) => {
                if (!Number.isSafeInteger(offset) || offset < 0) return;
                const button = document.createElement('button'); button.type = 'button'; button.textContent = t(label);
                button.addEventListener('click', () => run(() => load(kind, offset))); node.append(button);
            });
        });
        controls();
    }
    async function load(kind, offset = 0) {
        const args = {limit: 25, offset}; let name;
        if (kind === 'entries') {
            name = 'chrono_time_entries';
            const from = $('entries-from').value, through = $('entries-through').value;
            if (from && through && from > through) throw new Error('dateOrder');
            if (from) args.start_date = from;
            if (through) args.end_date = through;
        } else if (kind === 'corrections') {
            name = 'chrono_corrections'; args.status = $('corrections-status').value;
        } else {
            name = 'chrono_work_models';
            const date = $('model-date').value;
            pages.effective = await call('chrono_work_model', date ? {date} : {});
        }
        const result = await call(name, args);
        if (!Array.isArray(result.data)) throw new Error('invalid_page');
        pages[kind] = result; offsets[kind] = offset; renderPages();
    }
    function newEvent() {
        if (typeof crypto?.randomUUID === 'function') return crypto.randomUUID();
        if (typeof crypto?.getRandomValues !== 'function') throw new Error('noCrypto');
        const bytes = crypto.getRandomValues(new Uint8Array(16)); bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
        const hex = Array.from(bytes, n => n.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`;
    }
    function details(action) {
        const node = $('confirmation-details'); node.replaceChildren();
        [['profile', `${identity.username} · #${identity.id}`], ['action', t(action.action)], ['record', action.args.expected_record || '—'], ['event', action.args.event_id], ['binding', action.args.profile_binding]].forEach(([key, value]) => {
            const dt = document.createElement('dt'), dd = document.createElement('dd'); dt.textContent = t(key); dd.textContent = value; node.append(dt, dd);
        });
    }
    async function prepare(action, retry = false) {
        invalidate(); await readAttendance();
        if (pending && !retry) return;
        if (retry) {
            if (!pending || pending.args.profile_binding !== binding) throw new Error('expired');
            prepared = JSON.parse(JSON.stringify(pending));
        } else {
            if (!attendance.workflow.allowed_actions.includes(action)) throw new Error('expired');
            const event = newEvent();
            const args = {event_id: event, confirmed_event_id: event, profile_binding: binding};
            if (action !== 'start') args.expected_record = args.confirmed_record_id = attendance.workflow.expected_record;
            prepared = {action, tool: tools[action], args};
        }
        prepared.state = attendance.state;
        prepared.record = attendance.record_id_string;
        details(prepared);
        $('pause-confirmation').hidden = prepared.action !== 'end' || !(prepared.args.confirm_end_pause === true || attendance.workflow.confirmation_required_actions.includes('end'));
        $('confirm-end-pause').checked = false;
        $('confirmation').showModal();
    }
    async function submit() {
        const reviewed = prepared;
        if (!reviewed) return;
        const endPause = !$('pause-confirmation').hidden;
        if (endPause && !$('confirm-end-pause').checked) return;
        invalidate();
        // Recheck after the person's review. Never adapt their confirmation to a
        // new record, profile, or state in the background.
        await readAttendance();
        if (binding !== reviewed.args.profile_binding || (!pending && (attendance.state !== reviewed.state || attendance.record_id_string !== reviewed.record))) {
            throw new Error('expired');
        }
        if (!pending && !attendance.workflow.allowed_actions.includes(reviewed.action)) throw new Error('expired');
        if (!pending && endPause) reviewed.args.confirm_end_pause = true;
        if (pending && JSON.stringify(pending.args) !== JSON.stringify(reviewed.args)) throw new Error('expired');
        pending = {action: reviewed.action, tool: reviewed.tool, args: reviewed.args}; persist(); controls();
        const result = await call(pending.tool, pending.args);
        const expectedState = {start: 'working', pause_start: 'paused', pause_end: 'working', end: 'absent'}[pending.action];
        if (result.outcome !== 'confirmed' || result.event_id !== pending.args.event_id || result.profile_binding !== pending.args.profile_binding
            || result.state !== expectedState || !exactId(result.record_id_string)
            || (pending.action !== 'start' && result.record_id_string !== pending.args.expected_record)) throw new Error('event_mismatch');
        if (pending.action === 'end') {
            const pause = typeof result.pause_warning === 'string' ? result.pause_warning : '';
            const overlap = typeof result.overlap_warning === 'string' ? result.overlap_warning : '';
            const records = Array.isArray(result.overlap_record_ids) ? result.overlap_record_ids.filter(exactId) : [];
            latestEndWarnings = pause || overlap || records.length
                ? {record: result.record_id_string, event: result.event_id, pause, overlap, records} : null;
        }
        pending = null; persist();
        attendance = null;
        await readAttendance();
    }
    function activate(kind) {
        tab = kind;
        document.querySelectorAll('[data-tab]').forEach(button => { const selected = button.dataset.tab === kind; button.setAttribute('aria-selected', String(selected)); button.tabIndex = selected ? 0 : -1; $(`panel-${button.dataset.tab}`).hidden = !selected; });
        if (app && !busy && !stopped && kind !== 'attendance' && !pages[kind]) run(() => load(kind));
    }
    $('language').addEventListener('change', event => { invalidate(); language = event.target.value; translate(); });
    document.querySelectorAll('[data-tab]').forEach(button => {
        button.addEventListener('click', () => activate(button.dataset.tab));
        button.addEventListener('keydown', event => {
            const tabs = [...document.querySelectorAll('[data-tab]')], index = tabs.indexOf(button);
            const target = event.key === 'ArrowRight' ? (index + 1) % tabs.length : event.key === 'ArrowLeft' ? (index + tabs.length - 1) % tabs.length : event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : null;
            if (target !== null) { event.preventDefault(); tabs[target].focus(); activate(tabs[target].dataset.tab); }
        });
    });
    document.querySelectorAll('[data-action]').forEach(button => button.addEventListener('click', () => run(() => prepare(button.dataset.action))));
    $('refresh-attendance').addEventListener('click', () => { invalidate(); run(readAttendance); });
    $('retry-event').addEventListener('click', () => run(() => prepare(pending?.action, true)));
    $('confirm-action').addEventListener('click', () => run(submit));
    $('cancel-action').addEventListener('click', invalidate);
    $('confirmation').addEventListener('cancel', invalidate);
    $('confirm-end-pause').addEventListener('change', controls);
    ['entries','corrections','models'].forEach(kind => $(`${kind}-filter`).addEventListener('submit', event => { event.preventDefault(); invalidate(); run(() => load(kind)); }));
    translate();
    const hostTimer = setTimeout(() => { if (!app) notice('hostRequired', true); }, 12000);
    createMcpApp(async bridge => {
        app = bridge; clearTimeout(hostTimer);
        // Host notifications invalidate a displayed confirmation. Only a fresh
        // identity + attendance read may make a new action reviewable.
        bridge.onToolResult(() => { invalidate(); if (!busy && !stopped) run(readAttendance); });
        bridge.onToolCancelled(() => { invalidate(); notice(pending ? 'unknown' : 'failed', true); controls(); });
        await run(readAttendance);
        if (tab !== 'attendance') await run(() => load(tab));
    }).catch(() => { clearTimeout(hostTimer); notice(pending ? 'unknown' : 'hostRequired', true); });
})();
</script>
