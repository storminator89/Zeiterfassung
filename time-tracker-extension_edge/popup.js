let pauseTimer = null;
let pauseSeconds = 0;

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

    const savedPause = localStorage.getItem('pauseSeconds');
    const pauseStart = localStorage.getItem('pauseStart');
    const timerActive = localStorage.getItem('timerActive');

    if (savedPause) {
        pauseSeconds = parseInt(savedPause, 10);
        updatePauseDisplay();
    }

    // Starten Sie den Timer nur, wenn er aktiv war, als das Fenster geschlossen wurde
    if (pauseStart && timerActive === 'true') {
        const now = new Date().getTime();
        const elapsed = Math.floor((now - parseInt(pauseStart, 10)) / 1000);
        pauseSeconds += elapsed;
        startPauseTimer();
    }
});

document.getElementById('saveUrlButton').addEventListener('click', function() {
    const url = document.getElementById('apiUrl').value;
    localStorage.setItem('apiUrl', url);
    alert('API URL gespeichert.');
});


function sendStartTime() {
    const startzeit = new Date().toISOString();
    const apiUrl = localStorage.getItem('apiUrl');
    const standort = document.getElementById('standort').value; 
    const pauseStr = document.getElementById('pause').value;      
    const pause = convertTimeToMinutes(pauseStr);

    fetch(apiUrl, {  
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'createNewWorkEntry',
            startzeit: startzeit,
            endzeit: startzeit,
            pause: pause,
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
    

    fetch(apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
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

document.getElementById('startPauseButton').addEventListener('click', function() {
    const now = new Date().getTime();
    localStorage.setItem('pauseStart', now.toString());
    startPauseTimer(); // Rufen Sie die Funktion hier auf
});



document.getElementById('stopPauseButton').addEventListener('click', function() {
    if (pauseTimer) {
        clearInterval(pauseTimer);
        pauseTimer = null;
        localStorage.setItem('pauseSeconds', pauseSeconds.toString());
        localStorage.removeItem('pauseStart'); // Entfernen des Startzeitpunkts
        localStorage.setItem('timerActive', 'false'); // Speichern, dass der Timer nicht aktiv ist
    }
});


function updatePauseDisplay() {
    const minutes = Math.floor(pauseSeconds / 60);
    const seconds = pauseSeconds % 60;
    document.getElementById('pause').value = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
}


function startPauseTimer() {
    if (!pauseTimer) {
        pauseTimer = setInterval(function() {
            pauseSeconds++;
            updatePauseDisplay();
        }, 1000);
        localStorage.setItem('timerActive', 'true'); // Speichern, dass der Timer aktiv ist
    }
}
function convertTimeToMinutes(timeStr) {
    const parts = timeStr.split(':');
    const minutes = parseInt(parts[0], 10);
    const seconds = parseInt(parts[1], 10);

    return seconds > 0 ? minutes + 1 : minutes; // Aufrunden, wenn Sekunden > 0
}
