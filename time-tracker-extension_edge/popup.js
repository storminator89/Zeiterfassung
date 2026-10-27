'use strict';

function escapeHTML(value) {
    const node = document.createElement('span');
    node.textContent = String(value ?? '');
    return node.innerHTML;
}

// Both bundles use the same controller; only their existing storage API differs.
const extensionStorage = typeof browser !== 'undefined' ? browser.storage.local : null;
const sessionKeys = ['authToken', 'authApiUrl', 'authUserId', 'password', 'latestEntryId'];
const pendingKey = 'attendancePending';
const allowedActions = {absent: ['start'], working: ['pause_start', 'end'], paused: ['pause_end', 'end']};
let apiUrl = null, authToken = null, currentUser = null;
let attendanceState = null, attendanceRecordId = null, statusKnown = false;
let pendingEvent = null, busy = false, refreshing = null, sessionVersion = 0;
let storageQueue = Promise.resolve();
const requests = new Set();

function changeSaved(change) {
    const operation = storageQueue.then(change);
    storageQueue = operation.catch(() => {});
    return operation;
}
async function readSaved(keys) {
    await storageQueue;
    if (extensionStorage) return extensionStorage.get(keys);
    const result = {};
    for (const key of keys) {
        const value = localStorage.getItem(key);
        if (value !== null) result[key] = key === pendingKey ? JSON.parse(value) : value;
    }
    return result;
}
function writeSaved(values) {
    return changeSaved(async () => {
        if (extensionStorage) await extensionStorage.set(values);
        else for (const [key, value] of Object.entries(values)) {
            localStorage.setItem(key, key === pendingKey ? JSON.stringify(value) : String(value));
        }
    });
}
function removeSaved(keys) {
    return changeSaved(async () => {
        // Remove historical copies too, not just the currently selected adapter.
        for (const key of keys) localStorage.removeItem(key);
        if (extensionStorage) await extensionStorage.remove(keys);
    });
}
function validateApiUrl(value) {
    let url;
    try { url = new URL(String(value).trim()); }
    catch (error) { throw new Error('Bitte eine gültige HTTPS-API-Adresse eingeben.'); }
    if (url.protocol !== 'https:' || url.username || url.password || url.search || url.hash
        || url.pathname.replace(/\/$/, '') !== '/api.php') {
        throw new Error('Die Zieladresse muss HTTPS verwenden und auf /api.php enden, ohne Zugangsdaten, Parameter oder Fragment.');
    }
    return `${url.origin}/api.php`;
}
function snapshot() { return {version: sessionVersion, apiUrl, token: authToken}; }
function isCurrent(context) {
    return context.version === sessionVersion && context.apiUrl === apiUrl && context.token === authToken;
}
function staleError() { return Object.assign(new Error('Veraltete Anfrage verworfen.'), {stale: true}); }

// Redirects must never forward a password or bearer token to a different target.
async function request(endpoint, options = {}, authenticated = true, context = snapshot()) {
    if (!isCurrent(context)) throw staleError();
    const target = validateApiUrl(context.apiUrl);
    if (authenticated && !context.token) throw new Error('Bitte zuerst anmelden.');
    const controller = new AbortController();
    requests.add(controller);
    const timeout = window.setTimeout(() => controller.abort(), 15000);
    try {
        const response = await fetch(`${target}/${endpoint}`, {
            ...options, redirect: 'error', credentials: 'omit', cache: 'no-store', referrerPolicy: 'no-referrer',
            headers: {...options.headers, ...(authenticated ? {Authorization: `Bearer ${context.token}`} : {})},
            signal: controller.signal
        });
        if (!isCurrent(context)) throw staleError();
        if (response.status === 401 && authenticated) {
            await clearSession(false);
            setAuthNotice('Anmeldung abgelaufen oder ungültig. Bitte erneut anmelden. Eine unbestätigte Buchung bleibt nur für dasselbe Konto zur Wiederholung erhalten.');
            throw staleError();
        }
        let data;
        try { data = await response.json(); }
        catch (error) { throw new Error('Keine gültige Serverantwort erhalten.'); }
        if (!isCurrent(context)) throw staleError();
        return {ok: response.ok, status: response.status, data};
    } catch (error) {
        if (!isCurrent(context) || error.stale) throw staleError();
        if (error.name === 'AbortError') throw new Error('Keine Antwort vom Server erhalten.');
        throw error;
    } finally {
        window.clearTimeout(timeout);
        requests.delete(controller);
    }
}

function setColorScheme() {
    document.body.classList.toggle('dark-mode', window.matchMedia('(prefers-color-scheme: dark)').matches);
}
function setAuthNotice(message) { document.getElementById('authNotice').textContent = message; }
function showError(message) { M.toast({html: escapeHTML(message), classes: 'red', displayLength: 5000}); }
function showSuccess(message) { M.toast({html: escapeHTML(message), classes: 'green', displayLength: 4000}); }
function handleError(error) { if (!error.stale) showError(error.message || 'Die Anfrage konnte nicht bestätigt werden.'); }
function switchToZeitbuchungTab() { M.Tabs.getInstance(document.querySelector('.tabs')).select('zeitbuchungTab'); }
function switchToLoginTab() { M.Tabs.getInstance(document.querySelector('.tabs')).select('loginTab'); }

async function loadSavedData(version = sessionVersion) {
    // Never read or populate the old saved password. Purge it before any login.
    await removeSaved(['password', 'latestEntryId']);
    if (version !== sessionVersion) throw staleError();
    let saved;
    try { saved = await readSaved(['apiUrl', 'username', 'authToken', 'authApiUrl']); }
    catch (error) {
        if (version !== sessionVersion) throw staleError();
        throw new Error('Gespeicherte Anmeldung konnte nicht gelesen werden. Bitte erneut anmelden.');
    }
    if (version !== sessionVersion) throw staleError();
    if (saved.apiUrl) {
        try { apiUrl = validateApiUrl(saved.apiUrl); }
        catch (error) {
            const clearedVersion = await clearSession(true);
            if (clearedVersion !== sessionVersion) throw staleError();
            setAuthNotice(error.message);
            return;
        }
        document.getElementById('apiUrl').value = apiUrl;
    }
    document.getElementById('username').value = saved.username || '';
    if (saved.authToken && saved.authApiUrl === apiUrl) authToken = saved.authToken;
    else if (saved.authToken) {
        await removeSaved([...sessionKeys, pendingKey]);
        if (version !== sessionVersion) throw staleError();
        setAuthNotice('Gespeicherte Anmeldung wurde bereinigt. Bitte am bestätigten HTTPS-Ziel erneut anmelden.');
    }
    M.updateTextFields();
    renderAttendance();
}
function setupEventListeners() {
    const handlers = {saveUrlButton: saveApiUrl, loginButton: manualLogin, kommButton: sendStartTime,
        gehenButton: sendEndTime, pauseButton: sendPauseTime, retryButton: retryPending,
        refreshButton: refreshAll, logoutButton: logout};
    for (const [id, handler] of Object.entries(handlers)) {
        document.getElementById(id).addEventListener('click', () => { Promise.resolve(handler()).catch(handleError); });
    }
}
async function saveApiUrl() {
    const nextUrl = validateApiUrl(document.getElementById('apiUrl').value);
    if (nextUrl !== apiUrl) {
        await clearSession(true);
        apiUrl = nextUrl;
    }
    await writeSaved({apiUrl: nextUrl});
    document.getElementById('apiUrl').value = nextUrl;
    setAuthNotice(`Anmeldeziel: ${new URL(nextUrl).origin}. Passwörter werden nicht gespeichert.`);
    showSuccess('API URL gespeichert.');
}
async function autoLogin() { if (authToken) await fetchUserInfo(); }
async function manualLogin() {
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    document.getElementById('password').value = '';
    await login(username, password);
}
async function login(username, password) {
    if (busy) return;
    if (!apiUrl) throw new Error('Bitte zuerst die HTTPS-API-Adresse speichern.');
    if (validateApiUrl(document.getElementById('apiUrl').value) !== apiUrl) {
        throw new Error('Geänderte Zieladresse bitte zuerst speichern.');
    }
    if (!username || !password) throw new Error('Bitte Benutzername und Passwort eingeben.');
    const version = await clearSession(false);
    if (version !== sessionVersion) throw staleError();
    busy = true; renderAttendance();
    const context = snapshot();
    try {
        const {ok, data} = await request('login', {method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({username, password})}, false, context);
        if (!ok || !data.success || typeof data.token !== 'string' || !/^[A-Za-z0-9_.-]+$/.test(data.token)) {
            throw new Error('Anmeldung nicht bestätigt. Benutzername und Passwort prüfen.');
        }
        await writeSaved({authToken: data.token, authApiUrl: apiUrl, username});
        if (!isCurrent(context)) throw staleError();
        authToken = data.token;
        setAuthNotice(`Angemeldet bei ${new URL(apiUrl).origin}. Tokens laufen nach einer Stunde ab.`);
        await fetchUserInfo();
    } finally {
        if (context.version === sessionVersion) { busy = false; renderAttendance(); }
    }
}
async function clearSession(clearPending) {
    const version = ++sessionVersion;
    for (const controller of requests) controller.abort();
    authToken = null; currentUser = null; statusKnown = false; attendanceState = null; attendanceRecordId = null;
    busy = true; refreshing = null; pendingEvent = null;
    document.getElementById('password').value = '';
    document.getElementById('latestEntry').innerHTML = '';
    updateUserInfoDisplay(); renderAttendance(); switchToLoginTab();
    await removeSaved([...sessionKeys, ...(clearPending ? [pendingKey, 'username'] : [])]);
    if (version === sessionVersion) {
        if (clearPending) document.getElementById('username').value = '';
        busy = false; renderAttendance();
    }
    return version;
}
async function logout() {
    await clearSession(true);
    setAuthNotice('Abgemeldet. Gespeicherte Anmeldedaten und unbestätigte Vorgänge wurden entfernt.');
    showSuccess('Erfolgreich ausgeloggt.');
}
async function fetchUserInfo() {
    const context = snapshot();
    const {ok, data} = await request('users', {}, true, context);
    const user = data?.data?.[0];
    if (!ok || !data.success || !user || !Number.isSafeInteger(Number(user.id)) || Number(user.id) < 1) {
        throw new Error('Keine gültigen Benutzerdaten empfangen. Bitte erneut anmelden.');
    }
    currentUser = {...user, id: Number(user.id)};
    await writeSaved({authUserId: currentUser.id});
    if (!isCurrent(context)) throw staleError();
    await restorePending(context);
    if (!isCurrent(context)) throw staleError();
    updateUserInfoDisplay(); switchToZeitbuchungTab();
    await refreshAll();
}
function updateUserInfoDisplay() {
    document.getElementById('userInfo').innerHTML = currentUser ? `
        <p><strong>Angemeldet als:</strong> ${escapeHTML(currentUser.username)}</p>
        <p><strong>E-Mail:</strong> ${escapeHTML(currentUser.email)}</p>
        <p><strong>Rolle:</strong> ${escapeHTML(currentUser.role)}</p>`
        : '<p>Bitte melden Sie sich an, um Ihre Benutzerinformationen zu sehen.</p>';
}
function validPending(saved) {
    const body = saved?.body;
    return saved?.apiUrl === apiUrl && saved.userId === currentUser.id
        && /^[A-Za-z0-9_-]{16,80}$/.test(body?.event_id)
        && ['start', 'pause_start', 'pause_end', 'end'].includes(body.action)
        && typeof body.confirm_end_pause === 'boolean'
        && (body.action === 'end' ? saved.endpoint === 'setendzeit' && Number.isSafeInteger(body.id) && body.id > 0
            : saved.endpoint === 'attendance' && body.confirm_end_pause === false && body.id === undefined)
        && Object.keys(body).every(key => ['event_id', 'action', 'confirm_end_pause', 'id'].includes(key));
}
async function restorePending(context) {
    let saved;
    try { saved = (await readSaved([pendingKey]))[pendingKey]; }
    catch (error) { await removeSaved([pendingKey]); return; }
    if (!isCurrent(context)) throw staleError();
    if (saved && validPending(saved)) pendingEvent = saved;
    else await removeSaved([pendingKey]);
    renderAttendance();
}
function applyStatus(data) {
    if (!data?.success || !Object.hasOwn(allowedActions, data.state)
        || (data.state !== 'absent' && (!Number.isSafeInteger(data.record_id) || data.record_id < 1))) {
        throw new Error('Keine gültige Anwesenheitsauskunft erhalten.');
    }
    attendanceState = data.state;
    attendanceRecordId = data.state === 'absent' ? null : data.record_id;
    statusKnown = true;
    renderAttendance();
}
function renderAttendance() {
    const signedIn = Boolean(authToken && currentUser);
    const labels = {absent: 'Nicht angemeldet', working: 'Arbeitszeit läuft', paused: 'Pause läuft'};
    document.getElementById('attendanceStatus').textContent = pendingEvent
        ? 'Unbestätigte Buchung: bitte denselben Vorgang erneut versuchen.'
        : statusKnown ? labels[attendanceState] : 'Serverstatus nicht verfügbar. Bitte aktualisieren oder anmelden.';
    for (const [id, action] of [['kommButton', 'start'], ['gehenButton', 'end'], ['pauseButton', attendanceState === 'paused' ? 'pause_end' : 'pause_start']]) {
        const button = document.getElementById(id);
        button.disabled = !signedIn || busy || !statusKnown || Boolean(pendingEvent) || !allowedActions[attendanceState]?.includes(action);
    }
    document.getElementById('pauseButton').textContent = attendanceState === 'paused' ? 'Pause beenden' : 'Pause beginnen';
    document.getElementById('retryButton').hidden = !pendingEvent;
    document.getElementById('retryButton').disabled = !signedIn || busy;
    document.getElementById('refreshButton').disabled = !signedIn || busy;
    document.getElementById('loginButton').disabled = busy;
}
async function refreshAttendance() {
    if (!currentUser || !authToken) return;
    if (refreshing) return refreshing;
    const context = snapshot();
    statusKnown = false; renderAttendance();
    const operation = (async () => {
        const {ok, data} = await request('attendance', {}, true, context);
        if (!ok) throw new Error(data?.message || 'Serverstatus konnte nicht geladen werden.');
        applyStatus(data);
    })();
    refreshing = operation;
    try { await operation; }
    finally { if (refreshing === operation) refreshing = null; }
}
async function refreshAll() {
    await refreshAttendance();
    await fetchLatestTimeEntry();
}
async function fetchLatestTimeEntry() {
    const context = snapshot();
    const {ok, data} = await request('timeentries?limit=1', {}, true, context);
    if (!ok || !data.success || !Array.isArray(data.data)) throw new Error('Letzter Zeiteintrag konnte nicht geladen werden.');
    if (data.data.length) displayLatestTimeEntry(data.data[0]);
    else document.getElementById('latestEntry').textContent = 'Keine Einträge gefunden.';
}

function displayLatestTimeEntry(entry) {
    const formatDate = value => new Date(value).toLocaleString('de-DE');
    const duration = entry.endzeit ? Math.max(0, new Date(entry.endzeit) - new Date(entry.startzeit)) : null;
    document.getElementById('latestEntry').innerHTML = `
        <div class="entry-header"><span class="entry-title">Letzter Zeiteintrag</span><span class="entry-date">${formatDate(entry.startzeit)}</span></div>
        <div class="entry-details">
            <div class="entry-item"><div class="entry-label">Startzeit</div><div class="entry-value">${formatDate(entry.startzeit)}</div></div>
            <div class="entry-item"><div class="entry-label">Endzeit</div><div class="entry-value">${entry.endzeit ? formatDate(entry.endzeit) : 'Noch aktiv'}</div></div>
            <div class="entry-item"><div class="entry-label">Dauer (brutto)</div><div class="entry-value">${duration === null ? 'Noch aktiv' : `${Math.floor(duration / 3600000)}h ${Math.floor(duration % 3600000 / 60000)}m`}</div></div>
            <div class="entry-item"><div class="entry-label">Standort</div><div class="entry-value">${escapeHTML(entry.standort || '-')}</div></div>
            <div class="entry-item"><div class="entry-label">Beschreibung</div><div class="entry-value">${escapeHTML(entry.beschreibung || '-')}</div></div>
        </div>`;
}

async function sendStartTime() { await sendTimeEntry('start'); }
async function sendEndTime() { await sendTimeEntry('end'); }
async function sendPauseTime() { await sendTimeEntry(attendanceState === 'paused' ? 'pause_end' : 'pause_start'); }
async function retryPending() { if (pendingEvent) await sendTimeEntry(pendingEvent.body.action); }
async function sendTimeEntry(action) {
    if (busy || refreshing) return;
    if (!currentUser || !authToken) throw new Error('Bitte zuerst anmelden.');
    if (pendingEvent && pendingEvent.body.action !== action) throw new Error('Unbestätigte Buchung zuerst erneut versuchen.');
    if (!pendingEvent) {
        if (!statusKnown || !allowedActions[attendanceState]?.includes(action)) throw new Error('Bitte zuerst den aktuellen Serverstatus prüfen.');
        const confirmEndPause = action === 'end' && attendanceState === 'paused';
        if (confirmEndPause && !window.confirm('Pause und Arbeitszeit gemeinsam beenden?')) return;
        if (typeof crypto.randomUUID !== 'function') throw new Error('Sichere Ereignis-ID nicht verfügbar. Bitte Browser aktualisieren.');
        pendingEvent = {apiUrl, userId: currentUser.id, endpoint: action === 'end' ? 'setendzeit' : 'attendance',
            body: {event_id: crypto.randomUUID(), action, confirm_end_pause: confirmEndPause,
                ...(action === 'end' ? {id: attendanceRecordId} : {})}};
    }
    busy = true; renderAttendance();
    const context = snapshot(), event = pendingEvent;
    let acknowledged = false, rejected = false;
    try {
        // A storage failure must not allow an unrecoverable mutation to be sent.
        await writeSaved({[pendingKey]: event});
        if (!isCurrent(context)) throw staleError();
        const {ok, status, data} = await request(event.endpoint, {method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(event.body)}, true, context);
        if (!ok) {
            if (status >= 400 && status < 500) {
                await removeSaved([pendingKey]);
                if (!isCurrent(context)) throw staleError();
                pendingEvent = null; rejected = true;
            }
            throw new Error(data?.message || 'Buchung wurde nicht bestätigt.');
        }
        if (data?.event_id !== event.body.event_id || !Number.isSafeInteger(data?.record_id) || data.record_id < 1) {
            throw new Error('Keine passende Buchungsbestätigung erhalten.');
        }
        applyStatus(data);
        await removeSaved([pendingKey]);
        if (!isCurrent(context)) throw staleError();
        pendingEvent = null; acknowledged = true;
        showSuccess([data.message || 'Buchung bestätigt.', data.local_time].filter(Boolean).join(' '));
    } catch (error) {
        if (!error.stale) showError(`${error.message}${pendingEvent ? ' Denselben Vorgang erneut versuchen.' : ''}`);
    } finally {
        if (isCurrent(context)) { busy = false; renderAttendance(); }
    }
    if (isCurrent(context) && (acknowledged || rejected)) {
        // A cached idempotency replay can describe an older state. Always read
        // the current server status before enabling the next different action.
        try { await refreshAll(); }
        catch (error) { handleError(error); }
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    const version = sessionVersion;
    M.AutoInit(); setupEventListeners(); setColorScheme(); renderAttendance();
    try {
        await loadSavedData(version);
        if (version !== sessionVersion) throw staleError();
        await autoLogin();
    }
    catch (error) { handleError(error); }
});
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', setColorScheme);
