# #57 — getrennte neustartfeste Klärung, keine Offline-Buchungsqueue

Quellbasis: Main `01beb3166408e3fe2a4fdd38d3dfdbb76f8333a6` (bis PR186).
Der Nutzer hat eine globale Sperre für unbestätigte Vorgänge abgelehnt und die
Vorbereitung einer getrennten privaten dauerhaften Ablage freigegeben. Diese
neun Quelldateien sind **keine Freigabe für tatsächliche Terminaleinrichtung,
Berechtigungs-/Dienständerung, Zugangsdaten, Ereignisse oder Netzwerkaktionen**.

## Opt-in und endliche Verfügbarkeit

Ohne `--pending-dir` bleibt der bisherige schwächere RAM-/Retry-Modus aktiv.
Mit dem Flag muss ein explizites absolutes privates Verzeichnis bereits separat
zugelassen und initialisiert sein. Keine Home-/Tmp-/Konfigurations-Fallbackablage,
keine automatische Initialisierung/Migration/Reparatur, keine automatische
Rechtekorrektur. Eine fehlende/unsichere/beschädigte Ablage führt vor einem neuen
Event zu sichtbarer Verweigerung, nicht zu flüchtigem Versand. Identifikation,
Heartbeat und Datenschutzreset können weiter funktionieren. Bereits verlorene
Legacy-RAM-Vorgänge lassen sich nicht nachträglich wiederherstellen.

Ein ungeklärtes A-Ereignis wird von der aktuellen Interaktion getrennt. B kann
neu identifizieren und eine ausdrücklich neue Aktion auslösen. Keine globale
Backlogsperre, aber **keine unbegrenzte Verfügbarkeit**: höchstens 1000 offene
Einträge und 16MiB logisch abgerechnete Rohpayload-/Klärungsdaten. Reservierung
zählt sämtliche Partitionen und enthält 4096Byte Abschlussbudget pro Eintrag.
Bei Kapazitäts-/Speicherfehler wird kein neues Event gesendet und nichts Altes
überschrieben. Die vorhandene Meldungsfläche verweist auf einen anderen
freigegebenen Erfassungs-/Korrekturweg. Normale Online-Identifikation bleibt nötig.
Die logische Grenze ist keine physische SQLite-/Journal-/Datenträgergrenze.
Auch ein reservierter Abschluss kann an realem IO/Speichermangel scheitern:
ungeklärt lassen, nicht wiederholen oder ein Servercommit für ungeschehen erklären.

## Unveränderliche Absicht und Erstversand

`pending_store.py` speichert das einmalig mit den bisherigen Python-JSON-Einstellungen
kodierte Event als unveränderlichen Blob, SHA256, Event-/Eintragsreferenz, Aktion,
lokale Diagnosezeit und Format-/Server-/Geräte-/Schlüsselfingerprint-Partition.
Der **ursprüngliche Grant** und optionale exakte `expected_record` bleiben allein
in diesen ursprünglichen Bytes. Kein neuer Grant, Benutzer, Zeitwert, Record oder
Event-ID wird angehängt. Keine PIN, Personal-/Kartenkennung, Personenname oder
zweite Geräteschlüsselkopie wird gespeichert. Die lokale Zeit ist keine Buchungszeit.

Kurze atomare Transaktionen reservieren und speichern vor dem ersten Netzwerkaufruf;
der aktuelle Agent beansprucht den neuen Eintrag genau einmal vor Versand.
`Remote.request_raw` signiert und sendet exakt diese Bytes mit dem unveränderten
HMAC-Transportformat (aktuelle Nonce/Zeit, unveränderte Nachricht). Nach Absturz,
Timeout, Reset oder Neustart werden gespeicherte Eventbytes **nie erneut gesendet**.
Es gibt keinen Recovery-Event-/Replay-/Rebook-Befehl, keine Hintergrundsendung,
keine automatische Nachbuchung, Rückdatierung oder Korrekturerstellung.
Eine spätere neue bewusste Aktion ist nicht der alte Vorgang; vorher frischen
Serverzustand identifizieren und nicht blind wegen einer Fehlermeldung wiederbuchen.

## Privater Speicher und Besitz

POSIX-OS-Eigentümer und lokale Administratoren sind Vertrauensgrenzen. Ein
separates kanonisches Verzeichnis muss dem effektiven Benutzer gehören (`0700`),
DB/Lock/zulässiges Rollbackjournal private reguläre Dateien (`0600`, ein Hardlink)
sein; Symlinkpfade werden abgewiesen. Eigene vertrauenswürdige Elternpfade sind
bei der separaten Installation zu prüfen. `device.json` bleibt separat und
schreibgeschützt. Kein Verschlüsselungs-, Root-Isolations-, manipulationssicherer
Audit- oder Secure-Erase-Anspruch; freigegebene SQLite-Seiten/Journale/Backups
können alte sensible Bytes enthalten und benötigen dieselbe Schutzgrenze.

Neuer Store: SQLite `journal_mode=DELETE`, `synchronous=FULL`, 1000ms Busy-Timeout,
kurze `BEGIN IMMEDIATE`-Transaktionen; Version, Application-ID und exaktes Schema
werden geprüft. Kein Business-DB-Pragma wird verändert. WAL/SHM, unbekannte
Versionen/Schema oder unsichere Sidecars werden verweigert, nicht konvertiert,
checkpointed oder gelöscht. Nur ein sicheres eigenes Rollbackjournal darf SQLite
beim Öffnen normal lokal wiederherstellen. Das ist keine Serverwiederholung und
kein realer Stromausfall-/Dateisystem-/Hardware-Nachweis.

Ein nichtblockierendes Kernel-`flock` besitzt die Agentinstanz; keine PID-Alter-/TTL-
Heuristik und kein Löschen eines lebenden Locks. Neustart kann verwaiste Versuche
nur als **ungeklärt** klassifizieren; Leaseverlust beweist keinen ausgebliebenen
Servercommit. CLI und Agent nutzen kurze lokale Transaktionen, keine Netzwerksperre.
Manuelle Klärung eines aktuell laufenden Senders wird verweigert; andere Personen
bleiben unabhängig. Eine exakt positive historische Quittung darf ihren eigenen
Eintrag schließen, auch wenn die ursprüngliche Antwort noch unterwegs ist.

Partitionen binden exakten Endpunkt, Geräte-ID und Fingerprint des vorhandenen
Geräteschlüssels. Neue URL/Schlüssel koppeln keinen alten Eintrag um. Fremde alte
Partitionen bleiben redaktiert erhalten und zählen zur Kapazität. Kein automatisches
Lesen alter Schlüssel, Pairing, Leeren oder Versenden mit neuen Zugangsdaten.

## Zwei Besitzgrenzen statt verspäteter Personenwechsel

Vor dem Identify-Netzwerkaufruf beansprucht der Agent eine neue interne Generation.
Der Kiosk erzeugt vorher eine nur im RAM gehaltene zufällige `identify_request_id`.
Reset kann damit auch eine noch ausstehende Anmeldung abbrechen. Ein erfolgreicher
Identify installiert nur unter demselben aktuellen Besitzer ein zufälliges
`interaction_token`, gebunden an den ursprünglichen Grant. Nur dieses aktuelle
Token kann eine neue Aktion auslösen; atomare Beanspruchung verhindert Doppelclicks.

Reset/Timeout/neue Anmeldung lösen sichtbare Person/PIN und aktuelle Berechtigung,
nicht die private Queue. Reset selektiert ausschließlich seine eigene Request-ID
oder sein Token; fehlende/alte Selektoren dürfen keine neuere Anmeldung löschen.
Verspätete Identify- und Event-Erfolge **und Fehler/finally/busy-Aufräumarbeiten**
dürfen weder Agent-Grant noch Browseransicht der neueren Person überschreiben.
Kein Mutex bleibt über langsame Netzwerkarbeit gehalten. Browserablage enthält
weder Queue, Grant, Gerätekey noch historische Personen; keine lokale Adminroute.
Host/Origin/CSRF/CSP/Loopback- und normale HMAC-/Nonce-Prüfungen bleiben bestehen.

## Ausschließlich historische Quittungsprüfung

Das additive `op=receipt` ist ein **anderer Lookup-Envelope** aus den gespeicherten
Feldern, niemals ein erneuter Eventversand. Gemeinsam für native und Laravel-
Terminaltransporte, ohne neue Adapter-/Route-/Schema-/Defaultaktivierung:

- vorhandene Modulfunktion, signiertes aktuelles Gerät und Generation;
- ursprünglicher Grant für genau dieses Gerät, `used_event` exakt ursprüngliche ID;
- aktueller Credential-/Standort-/Versionscheck wie beim bisherigen Replay;
- ursprüngliches Benutzerkonto existiert weiterhin (sonst derselbe403-Refusal);
- Event passt exakt zu Quelle `terminal:<device>`, ursprünglichem Benutzer,
  Aktion und bestehendem Hash von `[user,action,confirm,expectedRecord]`.

Der Hashhelper ist nur die mechanische Extraktion der unveränderten bisherigen
Ausdrucksbytes. Keine Änderung von Hashformat, alten Quittungen oder Replaypolitik.
Receipt ruft **nicht** `attendance_transition_locked` auf: kein Event/Record,
Grantverbrauch/-verlängerung/-ausstellung, Last-Seen, Status-/Audit-/Korrekturschreiben.
Normale Transportnonce-Buchführung bleibt bestehen: nicht absolut schreibfrei.

Nur HTTP200 mit exakt `{success:true,historical_receipt:true,event_id,received_at}`
(literale Booleans, dieselbe ID, echter Integerzeitwert) bestätigt historische
Evidenz. Keine aktuelle Person/Record-/Statusbindung kommt zurück. Fehlender,
gelöschter oder widerrufener Grant, Account-/Credential-/Gerätesperre, fehlendes
Event, Mismatch,401/403/404, Altserver/ungültige200/Timeout bedeuten **ungeklärt**,
nicht „nicht gebucht“. Identify räumt alte Grants nach seiner bisherigen Grenze
auf; danach kann selbst ein wirklich committed Event konservativ unauflösbar
bleiben. Kein Ersatzgrant/App-Admin-Bypass oder Fallback zu mutierendem Event.

Unmittelbarer Abschluss verlangt ebenfalls HTTP200, literal `success:true`,
dieselbe Event-ID, aktionsgerechten Zustand, kanonische UTC-Serverzeit, begrenzte
Nachricht und echte positive Integer-Record-ID; Nicht-Start muss die ursprüngliche
Bindung treffen, ein vorhandener String muss exakt konsistent sein. Fehlende/
ungültige200 bleiben ungeklärt. Bestätigung aus einem Receipt wird nie als frischer
Buchungsstatus verwendet. Lokaler Abschlussfehler widerlegt keine positive
Serverbestätigung: deutlich warnen, nicht automatisch wiederholen.

## Lokale OS-Owner-Klärung, keine App-Admin-Imitation

`pending_admin.py` hat nur `init`, `list`, `receipt-check`, `resolve`. Kein Replay,
Neubuchen, Korrigieren, Massendiscard, Fremdbenutzerlogin oder HTTP-Adminservice.
Die Befugnis ist der zugelassene OS-/Dienstspeichereigentümer; effektive UID wird
abgeleitet, nicht als Actorparameter angenommen. Tatsächlicher Operator und
Speicherberechtigung sind eine separate Setup-/Betriebsfreigabe.

- `init`: explizit neuer Store im bereits privat zugelassenen Verzeichnis; keine
  Verzeichniserstellung, Rechteänderung oder Diensteinrichtung.
- `list`: JSON-escaped Eintrags-/Eventreferenz, Aktion, Diagnosezeit, redaktierter
  Partitionsfingerprint und Zustand; nie Grant/Rohbytes/PIN/Personen/Notizen.
- `receipt-check`: braucht explizit die vorhandene eigene private Konfiguration
  derselben Partition; ausschließlich Lookup. Unbekannt bleibt erhalten.
- `resolve`: bewusste **bereits extern geklärte** lokale Entscheidung mit Grund und
  optionalem Verweis. Beides begrenzt über verdeckte Eingabe/bounded stdin, nicht
  Kommandozeilenargumente oder Logs. Keine Namen, PINs, Grants, Kartenwerte oder
  Schlüssel eintragen. Minimalnotiz speichert UID/Zeit/Event/Hash/Art/Grund/Verweis
  atomar vor Entfernung des Raw-Eintrags; unveränderliche private Notizen bleiben.
  Das behauptet weder Servercommit noch Nichtbuchung und ersetzt keine fachlich
  autorisierte Korrektur im bestehenden Vieraugenworkflow.

Keine automatische Löschung ungeklärter Einträge. Notizen zählen zur logischen
Grenze; spätere explizite Aufbewahrungs-/Archivregeln fehlen bewusst. Kein raw
Serverdict, Pfad, Grant oder beliebiger Serverfehler wird durch die CLI ausgegeben.
Unbekannte/beschädigte Einträge erfordern Betreiberklärung, kein stilles Reparieren.

## Begrenzter Quellnachweis und verbleibende Freigaben

Ein zusammenhängender erfundener Ablauf prüft Persistenz vor Fake-Versand,
verlorene Antwort A, unabhängige B-Aktion, verspätete Eigentümerantworten,
Schließen/Öffnen ohne Eventwiederholung, historische Quittung versus unbekannt,
explizite lokale Klärung, eine volle Kapazitätsgrenze sowie unveränderten Hash und
keine Businessmutation im reinen SQLite-Memory-Receipt. Dazu Syntax und statischer
Quellerhalt, keine kleinteilige Produkt-Testmatrix.

Keine echten Konten, Keys, Grants, PIN-/Kartenaktionen, Requests, Listener, Browser,
Geräte, Power-Cut-, Host-/Hardware-/TLS-/Operator-/Betriebsabnahme; keine bestehende
Dateidatenbank und keine tatsächliche Setup-/Dienst-/Sicherheitsänderung. Private
synthetische Tempdateien werden entfernt. Unabhängiger eingefrorener Quellreview
ist vor Veröffentlichung nötig. #57 bleibt partiell, Deployment/Retention/Pilot-
und volle Crosschannel-/Hardwareabnahme offen; Sourcepublikation ist kein Setup.
