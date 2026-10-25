# Optionales Zeiterfassungs-Terminal für eine Zahnarztpraxis

Status: Der Online-Pilot ist implementiert; Einrichtung und tatsächlicher Umfang stehen in [TERMINAL.md](TERMINAL.md). Dieses Dokument hält den ursprünglichen Ausbauplan fest. Reale NFC-Hardware-Abnahme, kryptografische Karten und Offline-Sync bleiben Folgearbeiten.

## Ziel und Annahmen

Beschäftigte sollen Arbeitsbeginn, Arbeitsende und tatsächliche Pausen direkt in der Praxis buchen können, ohne eigenes Smartphone oder Anmeldung am Arbeitsplatz-PC. Die bestehende Web-App bleibt verfügbar. Das Terminal-Modul ist standardmäßig deaktiviert und wird pro Installation ausdrücklich aktiviert. Ohne Aktivierung nehmen Terminal-Routen keine Buchungen an. Die Web-Zeiterfassung verwendet inzwischen immer echte Pausen, Korrekturen und Historie; siehe [Praxisfunktionen](PRAXISFUNKTIONEN.md).

Planungsannahme: eine Praxis, ungefähr 5–30 Beschäftigte und zunächst ein Terminal am Personaleingang oder im Personalraum. Mehrere Standorte und Terminals werden im Datenmodell vorbereitet. Arbeitsplatz-PC und Terminal buchen auf dieselben Zeitkonten. Es werden keine Patienteninformationen verarbeitet. Die konkrete Personalzahl, Infrastruktur und gewünschte Authentifizierung müssen vor Hardwarekauf feststehen.

## Empfohlene Ausstattung

Ein fest montiertes Touch-Terminal mit kabelgebundenem Netzwerk, NFC-Leser und persönlichem Chip/Schlüsselanhänger pro Beschäftigtem. Ein kleiner Linux-PC mit Touchdisplay ist für USB-Leser und einen lokalen Gerätedienst flexibel; ein verwaltetes Tablet ist eine Alternative, wenn NFC-/Leser-Unterstützung vorher nachgewiesen wurde. Stromversorgung, Wandhalterung, Reinigung und ein geeigneter Standort außerhalb des Behandlungsbereichs gehören zur Beschaffung. Ein kleiner Server oder eine VM betreibt die PHP-App und speichert Daten außerhalb des Webverzeichnisses.

| Variante | Vorteil | Grenze / Voraussetzung |
| --- | --- | --- |
| Touch-Terminal mit persönlicher PIN | Günstiger Pilot, keine Leserhardware | PIN kann beobachtet/weitergegeben werden; Fehlversuche begrenzen, keine öffentliche Mitarbeiterliste |
| NFC-Chip mit zusätzlicher PIN | Schnelle Identifikation, Verlust des Chips allein reicht nicht zum Buchen | Einfach auslesbare Chip-UID ist kopierbar; PIN bleibt erforderlich |
| NFC mit kryptografischer Kartenprüfung | Komfortable Anmeldung ohne tägliche PIN möglich | Passende Karten, Leser und Geräteintegration erforderlich; mit realer Hardware nachweisen |
| Bestehende Web-App | Weiterhin nutzbar, z. B. für Verwaltung oder berechtigte mobile Arbeit | Kein Ersatz für einen gemeinsam genutzten, eingeschränkten Terminal-Modus |

Empfehlung: die Geräteschnittstelle zunächst mit PIN entwickeln, danach einen realen Leser pilotieren. Für den ersten NFC-Ausbau UID plus PIN einsetzen, sofern keine kryptografische Kartenprüfung implementiert ist. Eine UID allein niemals als sicheren Identitätsnachweis ausgeben. Fingerabdruck oder Gesichtserkennung sind für diesen Anwendungsfall nicht vorgesehen.

## Ablauf für die Belegschaft

1. Chip vorhalten und ggf. persönliche PIN eingeben; alternativ PIN-Anmeldung am Terminal.
2. Das Terminal zeigt kurz den eigenen Namen und den aktuellen Status. Kein Zugriff auf fremde Zeiten, Mitarbeiterverzeichnisse, API-Tokens oder Administration.
3. Passende Aktion auswählen: **Kommen**, **Pause beginnen**, **Pause beenden**, **Gehen**. Eindeutige Aktionen statt eines automatischen Kommen/Gehen-Wechsels bei jedem Scan.
4. Server bestätigt die Buchung mit Uhrzeit, kurzer Textanzeige und optionalem Signalton. Farbe allein reicht als Rückmeldung nicht aus.
5. Nach Bestätigung oder spätestens nach zehn Sekunden wird die Personenanzeige geleert und die Terminalsitzung beendet. Keine persistente Mitarbeitersitzung und kein allgemeines Mitarbeiter-JWT auf dem Gerät.

Doppelte Scans erzeugen keine zweite Buchung. Bei „Gehen“ während einer Pause muss das System die Pause und den Arbeitstag in einer Transaktion schließen und die Bestätigung entsprechend formulieren. Vergessene Buchungen werden über einen Korrekturantrag bearbeitet; das gemeinsam genutzte Terminal bietet keine freie rückwirkende Änderung.

## Gemeinsame Buchungslogik

Die bestehende Tabelle `zeiterfassung` speichert Beginn, Ende und einen Pausenwert. Sie bildet mehrere tatsächliche Pausen, Ereignisquellen und Korrekturhistorie bisher nicht ausreichend ab. Vor dem Terminal-Betrieb muss eine gemeinsame serverseitige Buchungslogik für **Web und Terminal** eingeführt werden. Terminal-Regeln dürfen nicht durch die bisherigen Web-/API-Schreibwege umgangen werden.

| Status | Zulässige nächste Aktion | Neuer Status |
| --- | --- | --- |
| Nicht anwesend | Kommen | Arbeitet |
| Arbeitet | Pause beginnen | In Pause |
| Arbeitet | Gehen | Nicht anwesend |
| In Pause | Pause beenden | Arbeitet |
| In Pause | Gehen, mit ausdrücklicher Bestätigung | Nicht anwesend |

Alle Aktionen prüfen aktive Beschäftigung, Mandanten-/Praxiszuordnung, Terminalfreigabe und aktuellen Zustand innerhalb einer Datenbanktransaktion. Pro Person höchstens eine offene Arbeitssitzung und eine offene Pause; Teilzeit, mehrere Arbeitsblöcke an einem Tag, Nachtschichten, Zeitzone `Europe/Berlin` und Sommerzeitwechsel berücksichtigen. Technische Zeitpunkte in UTC speichern, lokal anzeigen.

Tatsächliche Pausen als einzelne Intervalle erfassen. Eine Mindestpausenregel soll auf Abweichungen hinweisen, nicht automatisch eine nicht genommene Pause als Tatsache eintragen. Das ist eine bewusste Änderung gegenüber der derzeitigen automatischen Pausenberechnung und muss vor Einführung mit der Praxis abgestimmt werden. Historische Summen werden bei der Migration als historische Werte übernommen; keine erfundenen Pausenintervalle erzeugen. Gesetzliche, tarifliche und betriebliche Regeln müssen für den konkreten Betrieb geklärt werden.

## Architektur und Datenmodell

Das Terminal zeigt eine eingeschränkte Kiosk-Oberfläche. Ein lokaler Gerätedienst steuert den USB-/NFC-Leser und kommuniziert über HTTPS mit einer eigenen Terminal-API. Gerätegeheimnisse gehören in einen geschützten OS-Speicher oder TPM, nicht in JavaScript, LocalStorage oder URL-Parameter. Ein Tablet kann nur dann ohne Gerätedienst verwendet werden, wenn seine verwaltete Laufzeit eine entsprechend sichere Geräteanmeldung und Leseranbindung bereitstellt.

Vorgeschlagene Ergänzungen, zunächst als versionierte Migrationen:

- `terminals`: Praxis/Standort, Name, Geräteidentität, Freigabestatus, Schlüsselversion, letzte erfolgreiche Verbindung. Gerät pro Standort widerrufbar.
- `employee_credentials`: Beschäftigter, Verfahren, gehashte PIN bzw. pseudonymisierte Kartenkennung, Aktivierungs-/Sperrstatus. Kartenkennungen mit serverseitigem HMAC statt als Klartext oder einfach erratbaren Hash ablegen; PINs mit `password_hash` und Fehlversuchslimit.
- `attendance_events`: unveränderliche Ereignis-ID, Beschäftigter, Typ, Geräte-ID/Quelle, Gerätezeit, Empfangszeit, Offline-Kennzeichnung, Verarbeitungsstatus. Eindeutiger Index auf `(terminal_id, event_id)` zur Wiederholungssicherheit.
- `work_sessions` und `break_intervals`: überprüfte Arbeits- und Pausenintervalle als aus Ereignissen abgeleitete Sicht; bisherige Berichte über einen Kompatibilitätsadapter bedienen, nicht über zwei unabhängig beschriebene Wahrheiten.
- `time_adjustments`: Originalwert, Korrektur, Begründung, Antragsteller, Freigebender und Zeitpunkte. Historie gegen normale Änderungen schützen; Originalereignisse nicht überschreiben.

SQLite genügt voraussichtlich für einen kleinen, zentralen Pilotbetrieb. Transaktionen, Sperrverhalten und Parallelbuchungen müssen getestet werden. Eine spätere PostgreSQL-Migration wird erst bei mehreren Serverinstanzen, erhöhter Last oder Verfügbarkeitsanforderungen nötig; keine Datenbankkopien auf einzelnen Terminals als gleichberechtigte Hauptdatenbanken verwenden.

## Terminal-API und Schutz

Vorgeschlagene Routen: Geräte-Pairing mit kurzlebigem Einmalcode, `POST /terminal/v1/identify`, `POST /terminal/v1/events`, Heartbeat und optionaler Offline-Sync. Pairing startet ausschließlich ein berechtigter Administrator; neue Geräte erhalten keine allgemeinen Benutzer- oder Admin-Tokens.

Geräteauthentifizierung über mTLS oder signierte Anfragen mit einem individuellen Geräteschlüssel. Geräte-ID, Signatur, zulässiger Standort, Schlüsselversion, Zeitfenster und Nonce prüfen. Online-Ereignisse verwenden Serverzeit; die Gerätezeit dient Diagnose und Offline-Abgleich. Jede Buchung erhält zusätzlich eine eindeutige Ereignis-ID: Ein Retry liefert dasselbe Ergebnis, während ein anderer Payload mit gleicher ID abgewiesen wird. Die fachliche Zustandssperre muss auch zwei verschiedene Ereignisse von zwei Terminals gleichzeitig abfangen.

Geräteberechtigung erlaubt nur eingeschränkte Identifikation und Buchung, keinen Zugriff auf Backups, Personallisten oder Berichte. Fehlversuche pro Gerät und Person begrenzen, PIN-Eingaben verdecken, Fehlermeldungen ohne Personenaufzählung gestalten. Verlorene Chips und kompromittierte Geräte sofort sperrbar machen. Gesperrte oder ausgeschiedene Personen dürfen auch mit noch gültigem Chip nicht weiter buchen. Ein Gerät darf keine frei gewählte `user_id` ohne vorherige, kurzlebig gebundene Identifikation verwenden.

Kiosk-Betrieb mit automatischen Updates, gesperrten Systemmenüs, deaktiviertem USB-Boot und kontrolliertem Remote-Support. Kein Zugang zum Praxisverwaltungssystem über das Terminal. Standort und Netzwerksegment so wählen, dass Besucher nicht frei auf Terminal und Verkabelung zugreifen können.

## Ausfälle und Offline-Betrieb

**Pilot:** nur bestätigte Online-Buchungen. Bei Ausfall klar „Nicht gebucht – Verbindung fehlt“ anzeigen. Als organisatorischer Ersatz dient eine Papierliste oder ein späterer Korrekturantrag; keine falsche grüne Bestätigung.

**Optionaler Folgeausbau:** verschlüsselte, signierte lokale Ereigniswarteschlange im Gerätedienst. Anzeige ausdrücklich „Lokal vorgemerkt“, bis der Server bestätigt. Kurze Offline-Gültigkeit der Personenzuordnung, begrenzte Warteschlange und begrenzte Offline-Dauer; kein vollständiger Personalbestand und keine Passwortkopie auf dem Gerät. Nach Wiederverbindung geordnet übertragen, Wiederholungen erkennen und Konflikte (zwischenzeitliche Webbuchung, gesperrte Person/Karte, unplausible Gerätezeit) zur manuellen Prüfung markieren. Keine stillschweigende Änderung bereits bestätigter Arbeitszeiten. Gerätezeit ist offline manipulierbar; signierte Übertragung beweist nicht die Richtigkeit dieser Uhrzeit.

## Verwaltung, Einführung und Datenschutz

Die Praxisverwaltung aktiviert das Modul, benennt Standorte, koppelt Geräte, weist Chips zu und verwaltet Verlust/Ersatz. PIN-Reset nur über berechtigte Verwaltung und nachvollziehbaren Ablauf. Korrekturanträge von Beschäftigten erhalten einen Freigabeprozess; Berechtigungen für Führungskräfte bleiben auf zugeordnete Personen begrenzt.

Vor Einführung Zweck, Zugriffsrechte, Aufbewahrungs-/Löschfristen, Auskunft/Export, Backup-Verfahren und ggf. Beteiligung einer Arbeitnehmervertretung festlegen. Anzeigen am Terminal auf die eigene aktuelle Buchung begrenzen; keine öffentlich sichtbaren Fehlzeiten- oder Anwesenheitslisten. Beschäftigte verständlich informieren. Falls externes Hosting genutzt wird, Zuständigkeiten und erforderliche Vereinbarungen mit dem Anbieter klären. Kein Anspruch auf eine pauschale rechtliche Freigabe durch diese technische Planung.

## Umsetzung in vier Schritten

1. **Grundlage (grob 4–6 Entwicklungstage):** gemeinsamer Buchungsdienst, Zustandsübergänge, Pausenintervalle, Migration/Kompatibilitätsadapter, Tests für Web und API; bestehende Daten sichern und Migration im Test durchführen.
2. **Optionaler Online-Pilot (grob 4–6 Tage):** Feature-Schalter, Geräteverwaltung/Pairing, Kiosk-Oberfläche, PIN-Verfahren, API-Berechtigungen und Praxis-Abnahmetest.
3. **NFC-Hardware (grob 3–5 Tage nach Geräteauswahl):** Gerätedienst, Leser, Chip-Zuordnung, zusätzliche PIN oder kryptografische Kartenprüfung, Verlust-/Ersatzprozess und praktischer Test am gewählten Gerät.
4. **Separater Folgeausbau:** Offline-Sync, mehrere Terminals/Standorte und weitere Auswertungen. Aufwand erst nach Pilot und Ausfallanforderungen schätzen.

Die Bandbreiten sind unverbindliche Planungswerte für einen Entwickler mit Projektkenntnis. Hardwarebeschaffung, vollständige grafische Überarbeitung, Anbieter-/Leser-SDK-Probleme, rechtliche Abstimmung und längerfristiger Praxis-Support sind nicht enthalten. Zunächst zwei Wochen mit einer kleinen Testgruppe parallel zum bisherigen Verfahren pilotieren.

## Abnahmekriterien

- Deaktiviertes Modul akzeptiert keine Terminal-Identifikation oder -Buchung; bestehende Webabläufe und Altberichte funktionieren weiter.
- Kommen, mehrere Pausen, Gehen, Gehen während Pause, Schicht über Mitternacht und Sommerzeitwechsel werden korrekt erfasst.
- Gleiche Ereignis-ID/Retry erzeugt genau eine Buchung; zwei unterschiedliche gleichzeitige Kommen-Ereignisse erzeugen höchstens eine offene Sitzung.
- Ungültiges Gerät, fremder Standort, gesperrter Chip/Benutzer, falsche PIN, Replay und manipulierte Signatur werden abgewehrt und nachvollziehbar protokolliert, ohne PINs oder Rohschlüssel zu loggen.
- Terminal-Anzeige löscht die Identität nach Abschluss/Timeout; keine fremden Personaldaten sind erreichbar.
- Korrekturen erhalten eine Historie, tatsächliche Pausen bleiben von Regelhinweisen unterscheidbar, Exporte stimmen mit der Webansicht überein.
- Strom-/Netzausfall führt zu klarer Rückmeldung; ein späterer Offline-Ausbau besteht Konflikt-, Uhrabweichungs- und Dublettentests vor Aktivierung.
- Migration, Wiederherstellung und Rückkehr zur vorherigen Version sind mit einer Kopie realistisch strukturierter Praxisdaten erprobt.

Vor Umsetzung zu klären: Beschäftigtenzahl/Standorte, vorhandene Server/Netzwerk, gewünschtes Terminal, NFC mit PIN oder kryptografische Karten, Offline-Bedarf und konkrete Pausen-/Korrekturregeln. Diese offenen Punkte verhindern die Planung nicht, bestimmen aber Hardware und Pilotumfang.
