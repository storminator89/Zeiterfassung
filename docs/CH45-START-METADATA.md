# #51 / CH-45: enger Startmetadaten-Entwurf

Lokaler Entwurf auf dem exakten Quellbaum von Main `8053587c77e568fa24c32a81ab3b140ed17eedc6`.
Keine produktive Aktivierung, kein Release und keine vollständige Issue-Abnahme.

## Vertrag

Neue Starts in Web und API können `location` und `note` enthalten. Das sind neue,
sprachunabhängige Feldnamen; alte `standort`-/`beschreibung`-Schreibparameter bleiben
ignoriert. Die API-Aliase `/workentry` und `/attendance` verwenden dieselbe
Validierung. Zeitstempel, Pause, Benutzer- und Quellenidentität bleiben serverseitig.

- `location`: exakt `office`, `home_office` oder `business_trip`; ohne Feld `office`.
- `note`: einzeiliger gültiger UTF-8-Text, höchstens **1000 Bytes**, ohne ASCII-
  Steuerzeichen U+0000–U+001F/U+007F; ohne Feld leer. Keine automatische Kürzung,
  Übersetzung, Trimmung oder Unicode-Normalisierung. Vorhandenes `null`, Zahlen
  und Arrays sind keine Auslassung und werden mit HTTP 400 abgewiesen.
- Die exakten Alt-Abwesenheitsmarker `Urlaub`, `Krankheit`, `Feiertag` sind als Notiz
  gesperrt, damit eine Arbeitsbuchung nicht als Abwesenheit ausgewertet wird.
- Die neuen Felder sind ausschließlich bei `start` zulässig; auch explizite Defaults
  bei Pause/Ende werden abgewiesen. Kein Metadatenupdate über Zeitaktionen.

Die Projektion nutzt die vorhandenen Spalten `standort`/`beschreibung` und deren
bestehende INSERT-Audittrigger. Keine neue Tabelle oder Migration. Bestehende
Arbeitszeit-/Letzte-Buchung-Ansichten und CSV lesen dieselben gespeicherten Werte;
CSV behält seine bestehende Formula-Injection-Absicherung.

## Identität und Wiederholung

Ausgelassene oder explizite Defaultmetadaten (`office`, leer) behalten exakt den
bisherigen SHA-256-Hash des Tupels `[user,action,confirm,expectedRecord]` und die alte
Quittungsform. Nichtdefault-Metadaten ergänzen dieses Tupel um
`["start-metadata-v1",location,note]`. Gleiche Quelle/Ereignis-ID mit anderen
Metadaten führt zu HTTP 409, nicht zu einer Änderung. Bestehende Quittungen werden
unverändert gelesen, niemals nachgebaut oder nachträglich ergänzt.

Nur neue Nichtdefault-Starts ergänzen ihre gespeicherte Quittung um
`start_metadata:{location,note}`. Native und Laravel Web/API-Statusantworten nennen
`start_metadata_version:1`; dies ist Transportinformation, keine Berechtigung.
Der gemeinsame Statuskern, Terminalaufrufe, Hashes und Defaultquittungen bleiben
unverändert. Der Workflow-Status reicht dieselbe Version additiv weiter.

Web, Firefox und Edge aktivieren die optionalen Felder erst nach dieser expliziten
Serverauskunft. Ein neuer Nichtdefault-Start sichert die Werte zusammen mit der
bisherigen Ereignis-ID im selben Pending-Objekt. Wiederholungen verändern dieses
Objekt nicht. Fehlende Serverunterstützung oder eine Quittung mit abweichenden/
fehlenden Werten behält den unbestätigten Vorgang; keine vermeintliche Bestätigung.
Logout/Konto-/Serverwechsel setzt Eingabewerte und Capability zurück. Eigene alte
Pending-Objekte werden nur über die bestehende Konto-/Zielprüfung wiederhergestellt.
Nach bestätigtem Start werden die Eingabewerte für den nächsten Start zurückgesetzt.

## Nachträglicher Ort/Notiz-Antrag — Ergänzung auf Main e5ad8f64

Im vorhandenen Korrekturbereich ergänzt ein eingeklappter Formularteil die neue
Aktion `request_metadata_v1`. Nur eine bestehende eigene abgeschlossene
Arbeitsbuchung ist zulässig, keine Abwesenheit, laufende Buchung oder Stornierung.
Beide Felder verwenden unverändert den obigen sprachunabhängigen Vertrag.
Eine explizite Bestätigung verlangt das Ersetzen beider Werte; leer löscht die
Notiz. Eine Begründung und die bisherige fremde Admin-/Direktvorgesetztenentscheidung
bleiben zwingend. Selbstfreigabe ist ausgeschlossen. Zeiten, Pause, Gutschriften,
Sessions und Originalereignisse werden nicht verändert.

Keine Schemaänderung: Der Antrags-Snapshot `original_json` enthält die vollständige
unveränderte Originalzeile und zusätzlich ausschließlich das reservierte Feld
`_chrono_metadata_request_v1` mit exakt `version:1`, `location`, `note`.
Bei Freigabe wird genau dieses Feld entfernt und der übrige Snapshot ohne weitere
Normalisierung bytegleich mit der aktuellen Zeile verglichen. Metadaten, unveränderte
Zeitvorschläge und `cancel=0` werden unter demselben Writerlock erneut geprüft.
Unbekannte/fehlerhafte reservierte Formate oder eine Markerkollision verweigern die
Freigabe; es gibt keinen Rückfall auf einen normalen Zeitrequest. Alte Anträge
bleiben unverändert. Ein alter Core verweigert die neue Action mit 400 und eine
Genehmigung des erweiterten Snapshots mit 409 vor Buchungsmutation. **Ablehnen**
bleibt auch dort möglich und ändert nur Antragsstatus/Audit, nicht die Buchung.
Ein Rollback auf alte Software macht neue offene Metadatenanträge daher nicht
genehmigungsfähig; nichts wird automatisch konvertiert oder erneut gesendet.

Antragskarten und die feste Historienprojektion zeigen typisierte alte/beantragte
Ort-/Notizwerte statt beliebiger JSONfelder. Der bestehende Änderungsjournaltrigger
erfasst bei Freigabe Akteur, Grund, Serverzeit und die tatsächlichen Alt-/Neuwerte
atomar mit dem Status. Keine Rückschreibung historischer Quittungen. Bei ungewissem
Commitausgang keine abgelehnte/erfolgreiche Speicherung behaupten und keinen Draft
oder automatischen Retry anbieten. Vorhandene Erweiterungs-, Web-/API-Leser und CSV
lesen weiterhin dieselben Spalten; neue Korrekturendpunkte für API/Terminal sind
nicht Teil dieser Ergänzung. LDAP nutzt die mit PR318 bereits freigegebene normale
Business-Fassade, keine zusätzliche Auth-/Writer-/Verzeichnisänderung.

Grob geprüft: ursprünglicher Alt-Core und reale Memory-Migrationen/Audittrigger;
Pending → unabhängige Freigabe → Nur-Metadaten-Diff → Journal → CSV; unveränderte
normale Zeitkorrektur und begrenzte Format-/Scope-/Stale-Verweigerungen. PHP8.5.12,
SQLite3.46.1, Laravel13.38.0: tatsächliche Provider/SessionGuard/Fassade/Writer/Reads/
Controller/kompilierte Blade mit ausdrücklich gesetzten **unbrauchbaren Fixture-Usern**
für V4/V5 local und LDAP, einschließlich Direktvorgesetzten-/Adminfreigabe und
post-physical-commit Throwable. Kein Credentiallogin, echter Directory, Middleware-/
HTTP-/Browser-/Runtimezulassungs- oder Produktionsnachweis; keine Credentials,
Passworthashes oder Schlüssel erzeugt. Projektabrechnung, Terminal-Metadaten und
betriebliche Datenschutzregeln werden dadurch nicht eingeführt. Historische
Nachweisgrenzen unten bleiben historisch, eine Veröffentlichung ist separat zu belegen.

## Lokale Nachweise und Grenzen

PHP 8.5.12: ein grober, anonymer In-Memory-Durchlauf verwendet die vorhandenen
Migrationen und den realen gemeinsamen Buchungskern: Start → Audit → identische
Wiederholung → Metadatenkonflikt → Pause → Fortsetzen → Ende → historische
Wiederholung → vorhandener CSV-Export. Alte Default-/Terminalhashes und gespeicherte
Altquittungen bleiben gleich; abgewiesene Eingaben und Replays schreiben keine Daten.
Vorhandene 34 Extension-Vertragstests, PHP-/JS-Syntax und `git diff --check` geprüft.

Kein echter HTTP-/Auth-/Terminalgerätepfad, kein zugelassener Laravel/V5-Datei-Writer,
kein installierter Browser-/Extensionlauf, keine reale Betriebsdatenbank, keine
Zugangsdaten, keine Remoteänderung und keine produktive Veröffentlichung. Diese
Grenzen sind keine offenen Abnahmekriterien, die hier als bestanden gelten.
