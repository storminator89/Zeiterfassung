
function sendStartTime() {
    const startzeit = new Date().toISOString();
    // Beispielwerte für Testzwecke
    const endzeit = new Date(new Date().getTime() + (2 * 60 * 60 * 1000)).toISOString(); // +2 Stunden für Endzeit
    const pause = 30;  // 30 Minuten Pause
    const beschreibung = 'Testarbeitstag';
    const standort = 'Home Office';

    fetch('http://pmeyhoefer/jobrouter/RestAPISchulung/Git_Zeiterfassung/Zeiterfassung/api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'createNewWorkEntry',
            startzeit: startzeit,
            endzeit: endzeit,
            pause: pause,
            beschreibung: beschreibung,
            standort: standort
        }),
    })
    .then(response => response.json())
    .then(data => {
        console.log(data);
        if(data.success) {
            document.getElementById('response').textContent = 'Eintrag-ID: ' + data.id;
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
