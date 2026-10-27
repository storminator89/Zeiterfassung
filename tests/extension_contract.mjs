// Pure JavaScript contract/memory-storage mocks only. No browser, extension
// runtime, HTTP server, real credentials, or network requests are used.
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
import vm from 'node:vm';
import {randomUUID} from 'node:crypto';
import test from 'node:test';

const root = fileURLToPath(new URL('../', import.meta.url));
const baseUrl = 'https://synthetic.invalid/api.php';
const token = 'synthetic.token.value';
const clone = value => JSON.parse(JSON.stringify(value));
const escape = value => String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const reply = (status, data) => ({status, ok: status >= 200 && status < 300, json: async () => clone(data)});
class Element {
    constructor(tag = 'div') {
        this.tag = tag; this.value = ''; this.textContent = ''; this._html = ''; this.disabled = false; this.hidden = false;
        this.classList = {toggle() {}};
    }
    set innerHTML(value) { this._html = value; }
    get innerHTML() { return this.tag === 'span' ? escape(this.textContent) : this._html; }
    addEventListener() {}
}
function memory(seed = {}, firefox = false) {
    const store = {local: new Map(), browser: clone(seed), writes: [], gets: [], removes: [], failSet: false};
    if (!firefox) for (const [key, value] of Object.entries(seed)) store.local.set(key, typeof value === 'object' ? JSON.stringify(value) : String(value));
    return store;
}
function server() {
    const s = {state: 'absent', recordId: null, nextRecordId: 42, userId: 7, events: new Map(), transitions: [], records: []};
    s.handle = async (url, options = {}) => {
        const path = new URL(url).pathname.split('/').at(-1);
        if (path === 'login') return reply(200, {success: true, token});
        if (path === 'users') return reply(200, {success: true, data: [{id: s.userId, username: 'synthetic-user', email: 'synthetic@invalid', role: 'user'}]});
        if (path === 'timeentries') return reply(200, {success: true, data: s.records.slice(-1).reverse()});
        if (path === 'attendance' && !options.method) return reply(200, {success: true, state: s.state, record_id: s.recordId});
        const body = JSON.parse(options.body);
        assert.match(body.event_id, /^[A-Za-z0-9_-]{16,80}$/);
        const payload = JSON.stringify(body);
        if (s.events.has(body.event_id)) {
            const cached = s.events.get(body.event_id);
            return cached.payload === payload ? reply(200, cached.data) : reply(409, {success: false, message: 'Different event payload'});
        }
        const action = path === 'setendzeit' ? 'end' : body.action;
        if (action === 'start' && s.state !== 'absent') return reply(409, {success: false, message: 'Already working'});
        if (action === 'pause_start' && s.state !== 'working') return reply(409, {success: false, message: 'Not working'});
        if (action === 'pause_end' && s.state !== 'paused') return reply(409, {success: false, message: 'Not paused'});
        if (action === 'end' && (s.state === 'absent' || body.id !== s.recordId || (s.state === 'paused' && !body.confirm_end_pause))) {
            return reply(409, {success: false, message: 'End needs matching record and pause confirmation'});
        }
        let recordId = s.recordId;
        if (action === 'start') {
            recordId = s.nextRecordId++; s.recordId = recordId; s.state = 'working';
            s.records.push({id: recordId, startzeit: '2026-10-27T09:00:00+01:00', endzeit: null, pause: 0, standort: 'office', beschreibung: ''});
        } else if (action === 'pause_start') s.state = 'paused';
        else if (action === 'pause_end') s.state = 'working';
        else { s.state = 'absent'; s.recordId = null; s.records.at(-1).endzeit = '2026-10-27T17:00:00+01:00'; }
        const data = {success: true, event_id: body.event_id, record_id: recordId, state: s.state, message: 'Synthetic acknowledgement', local_time: '2026-10-27T09:00:00+01:00'};
        s.events.set(body.event_id, {payload, data}); s.transitions.push(action);
        return reply(200, data);
    };
    return s;
}
function environment(bundle, {store, mockServer = server(), handler} = {}) {
    const firefox = bundle === 'firefox_add_on';
    store ||= memory({apiUrl: baseUrl, authToken: token, authApiUrl: baseUrl}, firefox);
    const elements = new Map(), calls = [], toasts = [], tabs = [], listeners = new Map();
    const element = id => { if (!elements.has(id)) elements.set(id, new Element()); return elements.get(id); };
    const sandbox = {
        URL, AbortController, crypto: {randomUUID},
        document: {getElementById: element, createElement: tag => new Element(tag), addEventListener: (name, handler) => listeners.set(name, handler), querySelector: () => element('tabs'), body: new Element()},
        window: {matchMedia: () => ({matches: false, addEventListener() {}}), setTimeout, clearTimeout, confirm: () => true},
        M: {AutoInit() {}, updateTextFields() {}, toast: value => toasts.push(value), Tabs: {getInstance: () => ({select: tab => tabs.push(tab)})}},
        localStorage: {
            getItem: key => store.local.get(key) ?? null,
            setItem: (key, value) => { if (store.failSet) throw new Error('Synthetic storage unavailable'); store.local.set(key, value); store.writes.push({[key]: value}); },
            removeItem: key => { store.local.delete(key); store.removes.push(key); }
        },
        fetch: async (url, options) => {
            const call = {url, options}; calls.push(call);
            assert.equal(new URL(url).origin, new URL(baseUrl).origin, 'No unexpected target');
            assert.equal(options.redirect, 'error'); assert.equal(options.credentials, 'omit');
            assert.equal(options.cache, 'no-store'); assert.equal(options.referrerPolicy, 'no-referrer');
            return (handler || mockServer.handle)(url, options, mockServer);
        }
    };
    if (firefox) sandbox.browser = {storage: {local: {
        get: async keys => { store.gets.push(keys); return Object.fromEntries(keys.filter(k => Object.hasOwn(store.browser, k)).map(k => [k, clone(store.browser[k])])); },
        set: async values => { if (store.failSet) throw new Error('Synthetic storage unavailable'); Object.assign(store.browser, clone(values)); store.writes.push(clone(values)); },
        remove: async keys => { for (const key of keys) { delete store.browser[key]; store.removes.push(key); } }
    }}};
    const context = vm.createContext(sandbox);
    vm.runInContext(readFileSync(`${root}${bundle}/popup.js`, 'utf8'), context);
    const run = code => vm.runInContext(code, context);
    return {run, store, calls, toasts, tabs, element, sandbox, server: mockServer,
        start: async () => { await run('loadSavedData()'); await run('autoLogin()'); },
        startDom: () => listeners.get('DOMContentLoaded')(),
        saved: async key => JSON.parse(JSON.stringify(await run(`readSaved([${JSON.stringify(key)}])`)))[key],
        posts: () => calls.filter(c => c.options.method === 'POST')};
}

for (const bundle of ['time-tracker-extension_edge', 'firefox_add_on']) {
    test(`${bundle}: HTTPS and exact target validation`, async () => {
        const e = environment(bundle);
        assert.equal(e.run(`validateApiUrl(' https://synthetic.invalid:443/api.php/ ')`), baseUrl);
        for (const url of ['http://synthetic.invalid/api.php', 'https://u:p@synthetic.invalid/api.php', 'https://synthetic.invalid/api.php?x=1',
            'https://synthetic.invalid/api.php#x', 'https://synthetic.invalid/other.php', '//synthetic.invalid/api.php', 'javascript:alert(1)', 'https://synthetic.invalid/nested/api.php']) {
            assert.throws(() => e.run(`validateApiUrl(${JSON.stringify(url)})`));
        }
        assert.equal(e.calls.length, 0);
    });
    test(`${bundle}: migration purges passwords and unbound legacy tokens without requests`, async () => {
        const firefox = bundle === 'firefox_add_on';
        const store = memory({apiUrl: baseUrl, username: 'synthetic-user', password: 'SYNTHETIC-ONLY', authToken: token, latestEntryId: '99'}, firefox);
        store.local.set('password', 'SYNTHETIC-OLD-COPY');
        const e = environment(bundle, {store}); await e.start();
        assert.ok(store.gets.every(keys => !keys.includes('password')));
        assert.equal(await e.saved('password'), undefined); assert.equal(await e.saved('authToken'), undefined);
        assert.equal(store.local.has('password'), false); assert.equal(e.element('password').value, '');
        assert.equal(e.calls.length, 0);
    });
    test(`${bundle}: synthetic login persists no password and binds token to HTTPS API`, async () => {
        const e = environment(bundle, {store: memory({apiUrl: baseUrl}, bundle === 'firefox_add_on')});
        await e.start();
        e.element('username').value = 'synthetic-user'; e.element('password').value = 'SYNTHETIC-TEST-PASSWORD-NOT-REAL';
        await e.run('manualLogin()');
        assert.equal(e.element('password').value, ''); assert.equal(await e.saved('password'), undefined);
        assert.ok(e.store.writes.every(values => !Object.hasOwn(values, 'password')));
        assert.equal(await e.saved('authApiUrl'), baseUrl); assert.equal(await e.saved('authToken'), token);
        const [login] = e.posts(); assert.equal(login.url, `${baseUrl}/login`);
        assert.equal(login.options.headers.Authorization, undefined);
        assert.equal(JSON.parse(login.options.body).password, 'SYNTHETIC-TEST-PASSWORD-NOT-REAL');
        assert.equal(e.run('attendanceState'), 'absent');
    });
    test(`${bundle}: current server state gates start, pause, resume and confirmed end`, async () => {
        const e = environment(bundle); await e.start();
        assert.equal(e.element('kommButton').disabled, false); assert.equal(e.element('gehenButton').disabled, true);
        await Promise.all([e.run('sendStartTime()'), e.run('sendStartTime()')]);
        assert.deepEqual(e.server.transitions, ['start']); assert.equal(e.run('attendanceRecordId'), 42);
        await e.run('sendPauseTime()'); assert.equal(e.run('attendanceState'), 'paused');
        await e.run('sendPauseTime()'); assert.equal(e.run('attendanceState'), 'working');
        await e.run('sendPauseTime()');
        e.sandbox.window.confirm = () => false;
        await e.run('sendEndTime()'); assert.equal(e.server.transitions.length, 4);
        e.sandbox.window.confirm = () => true;
        await e.run('sendEndTime()'); assert.equal(e.run('attendanceState'), 'absent');
        assert.deepEqual(e.server.transitions, ['start', 'pause_start', 'pause_end', 'pause_start', 'end']);
        const end = e.posts().at(-1); assert.equal(end.url, `${baseUrl}/setendzeit`);
        assert.equal(JSON.parse(end.options.body).confirm_end_pause, true); assert.equal(JSON.parse(end.options.body).id, 42);
        assert.equal(new Set(e.posts().map(c => JSON.parse(c.options.body).event_id)).size, 5);
        assert.ok(e.posts().every(c => !/startzeit|endzeit|beschreibung|standort|"pause":/.test(c.options.body)));
        assert.equal(await e.saved('attendancePending'), undefined);
    });
    test(`${bundle}: response loss survives popup restart and replays exact event once`, async () => {
        const s = server(); let loseResponse = true;
        const e = environment(bundle, {mockServer: s, handler: async (url, options) => {
            const response = await s.handle(url, options);
            if (options.method === 'POST' && loseResponse) { loseResponse = false; throw new Error('Synthetic response loss'); }
            return response;
        }});
        await e.start(); await e.run('sendStartTime()');
        const persisted = await e.saved('attendancePending'); assert.equal(s.transitions.length, 1);
        assert.equal(e.element('retryButton').hidden, false); assert.equal(e.element('gehenButton').disabled, true);
        const restarted = environment(bundle, {store: e.store, mockServer: s}); await restarted.start();
        assert.equal(restarted.run('attendanceState'), 'working'); assert.equal(restarted.element('retryButton').hidden, false);
        await assert.rejects(restarted.run('sendEndTime()'), /Unbestätigte/);
        await restarted.run('retryPending()');
        assert.deepEqual(JSON.parse(restarted.posts()[0].options.body), persisted.body);
        assert.deepEqual(s.transitions, ['start']); assert.equal(await restarted.saved('attendancePending'), undefined);
    });
    test(`${bundle}: lost end response uses original record and refreshes past a stale replay`, async () => {
        const s = server(), e = environment(bundle, {mockServer: s}); await e.start(); await e.run('sendStartTime()');
        e.sandbox.fetch = async (url, options) => {
            e.calls.push({url, options}); const response = await s.handle(url, options);
            if (options.method === 'POST') throw new Error('Synthetic end response lost');
            return response;
        };
        await e.run('sendEndTime()');
        const event = await e.saved('attendancePending'); assert.equal(event.body.id, 42);
        // A different client has already started and paused another own session.
        s.state = 'paused'; s.recordId = 99;
        const restarted = environment(bundle, {store: e.store, mockServer: s}); await restarted.start(); await restarted.run('retryPending()');
        assert.equal(restarted.run('attendanceState'), 'paused'); assert.equal(restarted.run('attendanceRecordId'), 99);
        assert.deepEqual(s.transitions, ['start', 'end']);
        assert.deepEqual(JSON.parse(restarted.posts()[0].options.body), event.body);
    });
    test(`${bundle}: conflict clears only rejected event and refreshes server status`, async () => {
        const s = server(), e = environment(bundle, {mockServer: s}); await e.start();
        s.state = 'paused'; s.recordId = 77;
        await e.run('sendStartTime()');
        assert.equal(e.run('attendanceState'), 'paused'); assert.equal(e.run('attendanceRecordId'), 77);
        assert.equal(await e.saved('attendancePending'), undefined); assert.equal(s.transitions.length, 0);
    });
    test(`${bundle}: 503 and malformed/mismatched acknowledgements preserve exact retry`, async () => {
        for (const mode of ['503', 'json', 'event', 'record', 'state']) {
            const s = server(), e = environment(bundle, {mockServer: s, handler: async (url, options) => {
                if (options.method !== 'POST') return s.handle(url, options);
                if (mode === '503') return reply(503, {success: false, message: 'Synthetic unavailable'});
                if (mode === 'json') return {status: 200, ok: true, json: async () => { throw new Error('Synthetic invalid JSON'); }};
                const data = await (await s.handle(url, options)).json();
                if (mode === 'event') data.event_id = 'wrong-event-id-00000000';
                if (mode === 'record') delete data.record_id;
                if (mode === 'state') data.state = 'unknown';
                return reply(200, data);
            }});
            await e.start(); await e.run('sendStartTime()');
            assert.ok((await e.saved('attendancePending'))?.body.event_id, mode);
            assert.equal(e.element('retryButton').hidden, false, mode);
        }
    });
    test(`${bundle}: storage failure prevents mutation and unavailable status fails closed`, async () => {
        const e = environment(bundle); await e.start(); e.store.failSet = true;
        await e.run('sendStartTime()'); assert.equal(e.posts().length, 0);
        const blocked = environment(bundle, {handler: async (url, options, s) => url.endsWith('/attendance') ? reply(404, {success: false}) : s.handle(url, options)});
        await assert.rejects(blocked.start(), /Serverstatus/);
        await assert.rejects(blocked.run('sendStartTime()'), /Serverstatus/);
        assert.equal(blocked.posts().length, 0);
    });
    test(`${bundle}: expiry clears auth and explains manual reauthentication without password replay`, async () => {
        const s = server(), e = environment(bundle, {mockServer: s}); await e.start();
        e.sandbox.fetch = async () => reply(401, {success: false, message: 'Synthetic expired'});
        await e.run('sendStartTime()');
        assert.equal(await e.saved('authToken'), undefined); assert.ok(await e.saved('attendancePending'));
        assert.match(e.element('authNotice').textContent, /abgelaufen/); assert.equal(e.element('password').value, '');
        const same = environment(bundle, {store: e.store, mockServer: s}); await same.run('loadSavedData()');
        await same.run(`login('synthetic-user', 'SYNTHETIC-ONLY')`); assert.equal(same.element('retryButton').hidden, false);
        s.userId = 8;
        const other = environment(bundle, {store: e.store, mockServer: s}); await other.start();
        assert.equal(await other.saved('attendancePending'), undefined); assert.equal(other.element('retryButton').hidden, true);
    });
    test(`${bundle}: logout and server change purge auth, password, identity and pending state`, async () => {
        for (const mode of ['logout', 'server']) {
            const e = environment(bundle, {handler: async (url, options, s) => options.method === 'POST' ? reply(503, {success: false}) : s.handle(url, options)});
            await e.start(); await e.run('sendStartTime()');
            if (mode === 'logout') await e.run('logout()');
            else { e.element('apiUrl').value = 'https://new-synthetic.invalid/api.php'; await e.run('saveApiUrl()'); }
            for (const key of ['authToken', 'authApiUrl', 'authUserId', 'password', 'latestEntryId', 'attendancePending', 'username']) assert.equal(await e.saved(key), undefined, `${mode} ${key}`);
            assert.equal(e.run('currentUser'), null); assert.equal(e.run('statusKnown'), false);
        }
    });
    test(`${bundle}: late mutation and login responses cannot restore a logged-out session`, async () => {
        for (const mode of ['mutation', 'login']) {
            let resolve, started;
            const waiting = new Promise(r => { started = r; });
            const response = new Promise(r => { resolve = r; });
            const e = environment(bundle, {handler: async (url, options, s) => {
                if ((mode === 'login' && url.endsWith('/login')) || (mode === 'mutation' && options.method === 'POST')) { started(); return response; }
                return s.handle(url, options);
            }});
            await e.start();
            const task = mode === 'mutation' ? e.run('sendStartTime()') : e.run(`login('synthetic-user', 'SYNTHETIC-ONLY')`);
            await waiting; await e.run('logout()');
            resolve(reply(200, mode === 'login' ? {success: true, token} : {success: true, state: 'working', record_id: 42, event_id: 'synthetic-stale-event'}));
            await task.catch(error => { assert.ok(error.stale); });
            assert.equal(await e.saved('authToken'), undefined); assert.equal(await e.saved('attendancePending'), undefined);
            assert.equal(e.run('currentUser'), null); assert.equal(e.run('statusKnown'), false);
        }
    });
    test(`${bundle}: delayed status JSON cannot overwrite logout and server messages stay text`, async () => {
        const e = environment(bundle); await e.start();
        let resolve, started;
        const waiting = new Promise(r => { started = r; });
        e.sandbox.fetch = async () => ({status: 200, ok: true, json: () => { started(); return new Promise(r => { resolve = r; }); }});
        const refresh = e.run('refreshAttendance()'); await waiting; await e.run('logout()');
        resolve({success: true, state: 'paused', record_id: 99});
        await assert.rejects(refresh, error => error.stale === true);
        assert.equal(e.run('statusKnown'), false); assert.equal(e.run('currentUser'), null);
        e.run(`showError('<img src=x onerror=alert(1)>')`);
        assert.ok(!e.toasts.at(-1).html.includes('<img')); assert.ok(e.toasts.at(-1).html.includes('&lt;img'));
    });
    test(`${bundle}: invalid stored HTTP origin is cleaned without requests`, async () => {
        const store = memory({apiUrl: 'http://synthetic.invalid/api.php', authToken: token, authApiUrl: 'http://synthetic.invalid/api.php'}, bundle === 'firefox_add_on');
        const e = environment(bundle, {store}); await e.start();
        assert.equal(e.calls.length, 0); assert.equal(await e.saved('authToken'), undefined);
        assert.match(e.element('authNotice').textContent, /HTTPS/);
    });
    test(`${bundle}: failed account-switch lookup cannot reuse previous user or status`, async () => {
        const e = environment(bundle); await e.start(); await e.run('sendStartTime()');
        e.sandbox.fetch = async (url, options) => url.endsWith('/login') ? reply(200, {success: true, token: 'other.synthetic.token'}) : reply(503, {success: false});
        await assert.rejects(e.run(`login('other-synthetic-user', 'SYNTHETIC-ONLY')`), /Benutzerdaten/);
        assert.equal(e.run('currentUser'), null); assert.equal(e.run('statusKnown'), false);
        assert.equal(e.element('gehenButton').disabled, true);
    });
    test(`${bundle}: unsaved target changes do not transmit synthetic credentials`, async () => {
        const e = environment(bundle); await e.start();
        e.element('apiUrl').value = 'https://different.invalid/api.php';
        await assert.rejects(e.run(`login('synthetic-user', 'SYNTHETIC-ONLY')`), /zuerst speichern/);
        assert.equal(e.posts().length, 0);
    });
}
test('deferred startup storage cannot restore auth/API or auto-login after session invalidation', async () => {
    for (const bundle of ['time-tracker-extension_edge', 'firefox_add_on']) {
        for (const action of ['logout', 'server-change']) {
            const e = environment(bundle);
            let releaseRead, readStarted;
            const deferred = new Promise(resolve => { releaseRead = resolve; });
            const started = new Promise(resolve => { readStarted = resolve; });
            // Defer completion of the real memory-backed async storage read,
            // retaining its old snapshot like a delayed browser.storage.get.
            e.sandbox.deferStartupRead = async saved => { readStarted(); await deferred; return saved; };
            e.run(`const originalReadSaved = readSaved;
                readSaved = async keys => {
                    const saved = await originalReadSaved(keys);
                    return keys.includes('apiUrl') && keys.includes('authToken') ? deferStartupRead(saved) : saved;
                };`);
            const startup = e.startDom();
            await started;
            if (action === 'logout') await e.run('logout()');
            else {
                e.element('apiUrl').value = 'https://new-synthetic.invalid/api.php';
                await e.run('saveApiUrl()');
            }
            const expectedApi = e.run('apiUrl');
            const expectedNotice = e.element('authNotice').textContent;
            releaseRead(); await startup;
            assert.equal(e.run('apiUrl'), expectedApi, `${bundle} ${action}: stale API restored`);
            assert.equal(e.run('authToken'), null, `${bundle} ${action}: stale auth restored`);
            assert.equal(e.run('currentUser'), null);
            assert.equal(e.run('statusKnown'), false);
            assert.equal(e.element('authNotice').textContent, expectedNotice);
            assert.equal(e.calls.length, 0, `${bundle} ${action}: stale continuation auto-logged in`);
            assert.equal(await e.saved('authToken'), undefined);
            if (action === 'server-change') assert.equal(await e.saved('apiUrl'), 'https://new-synthetic.invalid/api.php');
        }
    }
});
test('bundled controller parity and markup hooks; manifests remain untouched', () => {
    assert.equal(readFileSync(`${root}firefox_add_on/popup.js`, 'utf8'), readFileSync(`${root}time-tracker-extension_edge/popup.js`, 'utf8'));
    for (const bundle of ['firefox_add_on', 'time-tracker-extension_edge']) {
        const html = readFileSync(`${root}${bundle}/popup.html`, 'utf8');
        for (const id of ['attendanceStatus', 'authNotice', 'pauseButton', 'retryButton', 'refreshButton']) assert.ok(html.includes(`id="${id}"`));
        assert.ok(!html.includes('browser-polyfill.min.js'));
        const css = readFileSync(`${root}${bundle}/popup.css`, 'utf8');
        assert.match(css, /\.btn-container \[hidden\]\s*\{\s*display: none/);
        assert.match(css, /flex-wrap: wrap/);
        assert.deepEqual(JSON.parse(readFileSync(`${root}${bundle}/manifest.json`, 'utf8')).permissions, ['storage', 'activeTab']);
    }
});
