let authToken = null;
let currentUser = null;

document.addEventListener('DOMContentLoaded', function() {
    M.AutoInit();
    loadSavedData();
    setupEventListeners();
    autoLogin();
    setColorScheme();
});

function setColorScheme() {
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.body.classList.add('dark-mode');
    } else {
        document.body.classList.remove('dark-mode');
    }
}

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', setColorScheme);

function loadSavedData() {
    const savedUrl = localStorage.getItem('apiUrl');
    if (savedUrl) {
        document.getElementById('apiUrl').value = savedUrl;
        M.updateTextFields();
    }
    const savedUsername = localStorage.getItem('username');
    if (savedUsername) {
        document.getElementById('username').value = savedUsername;
        M.updateTextFields();
    }
    const savedPassword = localStorage.getItem('password');
    if (savedPassword) {
        document.getElementById('password').value = savedPassword;
        M.updateTextFields();
    }
}

function setupEventListeners() {
    document.getElementById('saveUrlButton').addEventListener('click', saveApiUrl);
    document.getElementById('loginButton').addEventListener('click', manualLogin);
    document.getElementById('kommButton').addEventListener('click', sendStartTime);
    document.getElementById('gehenButton').addEventListener('click', sendEndTime);
    document.getElementById('logoutButton').addEventListener('click', logout);
}

function saveApiUrl() {
    const url = document.getElementById('apiUrl').value;
    if (!url) {
        showError('Bitte geben Sie eine API URL ein.');
        return;
    }
    localStorage.setItem('apiUrl', url);
    showSuccess('API URL gespeichert.');
}

function autoLogin() {
    authToken = localStorage.getItem('authToken');
    if (authToken) {
        fetchUserInfo();
    }
}

function manualLogin() {
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    login(username, password);
}

function login(username, password) {
    const apiUrl = localStorage.getItem('apiUrl');

    if (!apiUrl) {
        showError('Bitte zuerst API URL speichern.');
        return;
    }
    if (!username || !password) {
        showError('Bitte Benutzername und Passwort eingeben.');
        return;
    }

    fetch(`${apiUrl}/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password }),
    })
    .then(handleResponse)
    .then(data => {
        authToken = data.token;
        localStorage.setItem('authToken', authToken);
        localStorage.setItem('username', username);
        localStorage.setItem('password', password);
        showSuccess('Login erfolgreich.');
        fetchUserInfo();
        switchToZeitbuchungTab();
    })
    .catch(handleError);
}

function logout() {
    authToken = null;
    currentUser = null;
    localStorage.removeItem('authToken');
    document.getElementById('latestEntry').innerHTML = '';
    updateUserInfoDisplay();
    showSuccess('Erfolgreich ausgeloggt.');
    switchToLoginTab();
}

function fetchUserInfo() {
    const apiUrl = localStorage.getItem('apiUrl');
    fetch(`${apiUrl}/users`, {
        method: 'GET',
        headers: { 'Authorization': `Bearer ${authToken}` },
    })
    .then(handleResponse)
    .then(data => {
        if (data.success && data.data.length > 0) {
            currentUser = data.data[0];
            updateUserInfoDisplay();
            fetchLatestTimeEntry();
            switchToZeitbuchungTab();
        } else {
            throw new Error('Keine Benutzerdaten empfangen');
        }
    })
    .catch(handleError);
}

function updateUserInfoDisplay() {
    const userInfoElement = document.getElementById('userInfo');
    if (currentUser) {
        userInfoElement.innerHTML = `
            <p><strong>Angemeldet als:</strong> ${currentUser.username}</p>
            <p><strong>E-Mail:</strong> ${currentUser.email}</p>
            <p><strong>Rolle:</strong> ${currentUser.role}</p>
        `;
    } else {
        userInfoElement.innerHTML = `
            <p>Bitte melden Sie sich an, um Ihre Benutzerinformationen zu sehen.</p>
        `;
    }
}

function fetchLatestTimeEntry() {
    const apiUrl = localStorage.getItem('apiUrl');
    fetch(`${apiUrl}/timeentries`, {
        method: 'GET',
        headers: { 'Authorization': `Bearer ${authToken}` },
    })
    .then(handleResponse)
    .then(data => {
        if (data.data.length > 0) {
            const latestEntry = data.data.reduce((max, entry) => entry.id > max.id ? entry : max);
            displayLatestTimeEntry(latestEntry);
        } else {
            document.getElementById('latestEntry').innerHTML = 'Keine Einträge gefunden.';
        }
    })
    .catch(handleError);
}

function displayLatestTimeEntry(entry) {
    const formatDate = (dateString) => {
        const options = { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' };
        return new Date(dateString).toLocaleDateString('de-DE', options);
    };

    const formatDuration = (startDate, endDate) => {
        if (!endDate) return 'Noch aktiv';
        const diff = new Date(endDate) - new Date(startDate);
        const hours = Math.floor(diff / 3600000);
        const minutes = Math.floor((diff % 3600000) / 60000);
        return `${hours}h ${minutes}m`;
    };

    const latestEntryElement = document.getElementById('latestEntry');
    latestEntryElement.innerHTML = `
        <div class="entry-header">
            <span class="entry-title">Letzter Zeiteintrag</span>
            <span class="entry-date">${formatDate(entry.startzeit)}</span>
        </div>
        <div class="entry-details">
            <div class="entry-item">
                <div class="entry-label">Startzeit</div>
                <div class="entry-value">${formatDate(entry.startzeit)}</div>
            </div>
            <div class="entry-item">
                <div class="entry-label">Endzeit</div>
                <div class="entry-value">${entry.endzeit ? formatDate(entry.endzeit) : 'Noch aktiv'}</div>
            </div>
            <div class="entry-item">
                <div class="entry-label">Dauer</div>
                <div class="entry-value">${formatDuration(entry.startzeit, entry.endzeit)}</div>
            </div>
            <div class="entry-item">
                <div class="entry-label">Standort</div>
                <div class="entry-value">${entry.standort || '-'}</div>
            </div>
            <div class="entry-item">
                <div class="entry-label">Beschreibung</div>
                <div class="entry-value">${entry.beschreibung || '-'}</div>
            </div>
        </div>
    `;
}

function sendStartTime() {
    sendTimeEntry('createNewWorkEntry');
}

function sendEndTime() {
    sendTimeEntry('setEndzeit');
}

function sendTimeEntry(action) {
    const apiUrl = localStorage.getItem('apiUrl');
    const standort = document.getElementById('standort').value;
    const beschreibung = document.getElementById('beschreibung').value;

    if (!standort) {
        showError('Bitte wählen Sie einen Standort aus.');
        return;
    }

    const data = {
        action: action,
        startzeit: new Date().toISOString(),
        endzeit: action === 'setEndzeit' ? new Date().toISOString() : null,
        pause: 0,
        beschreibung: beschreibung,
        standort: standort
    };

    if (action === 'setEndzeit') {
        data.id = localStorage.getItem('latestEntryId');
    }

    fetch(`${apiUrl}/${action === 'setEndzeit' ? 'setendzeit' : 'workentry'}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${authToken}`
        },
        body: JSON.stringify(data),
    })
    .then(handleResponse)
    .then(data => {
        if (action === 'createNewWorkEntry') {
            localStorage.setItem('latestEntryId', data.data.id);
        }
        showSuccess(`${action === 'createNewWorkEntry' ? 'Kommen' : 'Gehen'} erfolgreich gebucht.`);
        fetchLatestTimeEntry();
    })
    .catch(handleError);
}

function handleResponse(response) {
    if (!response.ok) {
        return response.json().then(err => {
            throw new Error(err.message || 'Ein Fehler ist aufgetreten bei der Serveranfrage.');
        });
    }
    return response.json();
}

function handleError(error) {
    console.error('Error:', error);
    showError(`Fehler: ${error.message}`);
}

function showError(message) {
    M.toast({html: message, classes: 'red', displayLength: 4000});
}

function showSuccess(message) {
    M.toast({html: message, classes: 'green', displayLength: 3000});
}

function switchToZeitbuchungTab() {
    const tabs = M.Tabs.getInstance(document.querySelector('.tabs'));
    tabs.select('zeitbuchungTab');
}

function switchToLoginTab() {
    const tabs = M.Tabs.getInstance(document.querySelector('.tabs'));
    tabs.select('loginTab');
}