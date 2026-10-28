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

## Noch offen

**Auditiertes nachträgliches Ändern** von Ort/Notiz ist nicht implementiert; der
bestehende Vier-Augen-Zeitkorrekturpfad ändert diese Metadaten nicht. Projektabrechnung,
Notizrichtlinien für reale personenbezogene Angaben, neue Korrektur-UI, Terminal-
Metadaten und operative Migration sind ausdrücklich außerhalb dieses Ausschnitts.
Issue #51 darf deshalb nicht als erledigt oder produktiv abgenommen markiert werden.

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
