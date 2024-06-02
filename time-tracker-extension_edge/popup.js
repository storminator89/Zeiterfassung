let authToken = null;

document.addEventListener('DOMContentLoaded', function() {
    const savedUrl = localStorage.getItem('apiUrl');
    if (savedUrl) {
        document.getElementById('apiUrl').value = savedUrl;
    }
    const currentDate = new Date().toDateString();
    const lastAccessDate = localStorage.getItem('lastAccessDate');

    if (lastAccessDate !== currentDate) {
        localStorage.removeItem('latestEntryId');
    }

    localStorage.setItem('lastAccessDate', currentDate);

    authToken = localStorage.getItem('authToken');

    const savedUsername = localStorage.getItem('username');
    const savedPassword = localStorage.getItem('password');

    if (savedUsername) {
        document.getElementById('username').value = savedUsername;
    }

    if (savedPassword) {
        document.getElementById('password').value = savedPassword;
    }

    if (authToken) {
        fetchLatestTimeEntry();
    }
});

document.getElementById('saveUrlButton').addEventListener('click', function() {
    const url = document.getElementById('apiUrl').value;
    localStorage.setItem('apiUrl', url);
    alert('API URL gespeichert.');
});

document.getElementById('loginButton').addEventListener('click', function() {
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const apiUrl = localStorage.getItem('apiUrl');

    console.log('Login attempt:', username, apiUrl);

    fetch(`${apiUrl}/login`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            username: username,
            password: password
        }),
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            authToken = data.token;
            localStorage.setItem('authToken', authToken);
            localStorage.setItem('username', username);
            localStorage.setItem('password', password);
            alert('Login erfolgreich.');
            fetchLatestTimeEntry();
        } else {
            alert('Login fehlgeschlagen: ' + data.message);
        }
    })
    .catch((error) => {
        console.error('Error:', error);
        document.getElementById('response').textContent = 'Fehler: ' + error.message;
    });
});

function fetchLatestTimeEntry() {
    const apiUrl = localStorage.getItem('apiUrl');
    fetch(`${apiUrl}/timeentries`, {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${authToken}`
        },
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data.length > 0) {
            const latestEntry = data.data.reduce((max, entry) => entry.id > max.id ? entry : max);
            displayLatestTimeEntry(latestEntry);
        } else {
            document.getElementById('response').textContent = 'Keine Einträge gefunden.';
        }
    })
    .catch((error) => {
        console.error('Error:', error);
        document.getElementById('response').textContent = 'Fehler: ' + error.message;
    });
}

function displayLatestTimeEntry(entry) {
    const formatDate = (dateString) => {
        const options = { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' };
        return new Date(dateString).toLocaleDateString('de-DE', options);
    };

    let tableContent = `
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Startzeit</th>
                    <th>Endzeit</th>
                    <th>Pause</th>
                    <th>Beschreibung</th>
                    <th>Standort</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>${entry.id}</td>
                    <td>${formatDate(entry.startzeit)}</td>
                    <td>${entry.endzeit ? formatDate(entry.endzeit) : ''}</td>
                    <td>${entry.pause}</td>
                    <td>${entry.beschreibung}</td>
                    <td>${entry.standort}</td>
                </tr>
            </tbody>
        </table>
    `;
    document.getElementById('response').innerHTML = tableContent;
}

function sendStartTime() {
    const startzeit = new Date().toISOString();
    const apiUrl = localStorage.getItem('apiUrl');
    const standort = document.getElementById('standort').value;
    const beschreibung = document.getElementById('beschreibung').value;

    fetch(`${apiUrl}/workentry`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${authToken}`
        },
        body: JSON.stringify({
            action: 'createNewWorkEntry',
            startzeit: startzeit,
            endzeit: startzeit,
            pause: 0,
            beschreibung: beschreibung,
            standort: standort
        }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            localStorage.setItem('latestEntryId', data.data.id);
            let entryDetails = 'Eintrag Details:<br>';
            for (const key in data.data) {
                entryDetails += `${key}: ${data.data[key]}<br>`;
            }
            document.getElementById('response').innerHTML = entryDetails;
        } else {
            document.getElementById('response').textContent = 'Fehler: ' + data.message;
        }
    })
    .catch((error) => {
        console.error('Error:', error);
        document.getElementById('response').textContent = 'Fehler: ' + error.message;
    });
}

document.getElementById('kommButton').addEventListener('click', sendStartTime);

function sendEndTime() {
    const id = localStorage.getItem('latestEntryId');

    if (!id) {
        document.getElementById('response').textContent = 'Keine aktive Sitzung gefunden.';
        return;
    }
    const apiUrl = localStorage.getItem('apiUrl');

    fetch(`${apiUrl}/setendzeit`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${authToken}`
        },
        body: JSON.stringify({
            action: 'setEndzeit',
            id: id
        }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('response').textContent = 'Endzeit aktualisiert für Eintrag-ID: ' + id;
            fetchLatestTimeEntry(); 
        } else {
            document.getElementById('response').textContent = 'Fehler: ' + data.message;
        }
    })
    .catch((error) => {
        console.error('Error:', error);
        document.getElementById('response').textContent = 'Fehler: ' + error.message;
    });
}

document.getElementById('gehenButton').addEventListener('click', sendEndTime);
