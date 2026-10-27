# #79 – vorhandene Admin-Pausenregelkarte, begrenzt opt-in

Nur die vorhandene Karte `admin.php#pause-settings`: aktuelle Regeln lesen,
Zeilen hinzufügen/entfernen und die vollständige Liste speichern. Bestehendes
Layout, Karten-/Tabellenklassen, Feldnamen, IDs, Icons und ausgewählte native
Beschriftungen bleiben erhalten. Keine zweite Admin-Oberfläche. Die Ansicht
kennzeichnet Benutzer-/Abteilungs-/LDAP-/Credential-/Tokenverwaltung ausdrücklich
als nicht verbunden; auch gemischte oder unbekannte POSTs werden abgelehnt.

## Ein fester Lese-/Schreibpfad

`ChronoReads::adminPauseSettings()` lädt im bestehenden autorisierten
DEFERRED-Snapshot die aktuelle Rolle/Quelle erneut und verlangt einen lokalen
Admin, bevor `SELECT * FROM pause_settings ORDER BY hours_threshold ASC` läuft.
Keine Benutzerliste, Passwörter, Tokens oder LDAP-Konfiguration werden gelesen.
`admin.php`, `config.php`, `header.php` und deren ausführbarer Bootstrap werden
nicht eingebunden; keine Schemapflege, Defaultregeln oder Geheimnisse geladen.

`LocalWebPauseSettings`/`LegacyLocalWebPauseSettings` akzeptieren ausschließlich
den festgelegten Pausenbefehl. Der bestehende lokale Guard liefert die Identität,
nicht ein Formularfeld; Nichtadmins werden vor Writerauflösung abgewiesen.
Der einzige dedizierte `AttendanceWriter::pauseSettings()` wiederverwendet
**unverändert** `validatePauseSettings()` aus `lib/pause_settings.php`: vollständige,
nichtleere, gleich lange Listen; nichtnegative Ganzzahlen nach vorhandener
Normalisierung; keine doppelten normalisierten Stundengrenzen. Die komplette
Validierung läuft vor der Transaktion und vor jedem DELETE/INSERT.

Innerhalb des bestehenden Writer-Locks werden aktuelle Rolle und `auth_source`
für die Guard-ID nochmals gelesen; nur ein weiterhin lokaler Admin darf die
Liste ersetzen. Die feste SQL-Operation löscht und ersetzt sämtliche Regeln in
einem Commit. Ein Fehler nach DELETE oder einem Teil-INSERT rollt Regeln und
Sequenz vollständig zurück. Kein öffentliches SQL-Callback/PDO, kein zweiter
Writer, kein Retry und keine verschachtelte native Transaktion.

**Expliziter Transaktionsunterschied:** Der native Handler verwendet PDO
DEFERRED. Dieser freigegebene Frameworkpfad nutzt die vorhandene dedizierte
IMMEDIATE-/5000-ms-/Single-attempt-Schreibhülle. Native Handler/Validator bleiben
bytegleich. Die neue Prüfung unter Lock verhindert, dass eine inzwischen
entzogene Adminrolle durch eine alte Seite weiter schreiben kann. Die Tabelle
hat keinen bestehenden Audittrigger; dieses Paket behauptet oder erfindet kein
Auditprotokoll für Pauseneinstellungen.

Die Regeln bleiben nur konfigurierte Mindestpausen für den bestehenden Hinweis
beim Gehen. Keine automatische Pausenkürzung, neue Zeitberechnung, rückwirkende
Umbuchung oder Änderung von Arbeit, Gutschriften, Departments oder Konten.

## Geschützter Transport und Grenze

Nur `LocalWebRoutes::register(..., includeAdminPauseSettings: true)` registriert
getrennte GET/HEAD→`index` und POST→`store` für das bestehende `admin.php`-Ziel.
Der Default ist `false`, der normale Bootstrap bleibt `/up`-only. Pflicht bleiben
persistente Sessionblockierung und Session/CSRF → `RefreshLocalAccount` →
`auth:web` → `auth.session`. Keine Abschaltung oder Umgehung von CSRF/Auth.
Für diesen ausdrücklich zugelassenen Pfad bleiben native Raw-Formwerte von
Trim-/Leerstringnormalisierung ausgenommen. GET oder Method Override zu GET
ruft keinen Writer auf. Abgelaufene Anmeldung: GET-Loginredirect, POST-401.

POST erlaubt neben CSRF ausschließlich `update_pause_settings`,
`hours_threshold`, `minimum_pause`. Jedes andere oder gemischte Feld – etwa
Benutzer-/Abteilungsaktionen, Rollen, Passwörter, LDAP oder Tokenbefehle – ergibt
400 vor einem Read/Write/Preference-Save, niemals einen nativen Fallback.
Erfolg folgt wie nativ auf Commit mit Redirect, hier auf den vorhandenen
Kartenanker; keine erfundene Flash-Erfolgsmeldung. Native Validierungsfehler
werden escaped angezeigt; technische Fehler bleiben generisch und enthalten
keine SQL-, Pfad- oder Personendaten.

Der bestehende Framework-Webguard ist weiterhin **nur lokal**. Die native
Anwendung kann auch LDAP-Admins besitzen; eine LDAP-Admin-Abnahme oder ein
vollständiger Admin-/Auth-Cutover wird hier ausdrücklich nicht behauptet.

## Begrenzter Nachweis

Ein grober neuer synthetischer Atomic-/Rollenablauf und notwendige Syntaxprüfung,
keine breite Testmatrix. Nachweise werden im Paket-Handoff dokumentiert. Keine
reale Geschäftskonfiguration, Zugangsdaten, Authsitzung, Browser-/HTTP- oder
Produktivaktivierung. Geschützte Browserabnahme und tatsächlicher Betrieb
bleiben offen; native Referenzseiten beweisen keine Laravel-Abnahme.

Der aktuelle grobe synthetische Nachweis bestand unter PHP 8.5.12: vollständiger
Ersatz; ungültige vollständige Eingabe unverändert abgewiesen; unter Lock
inzwischen entzogene Adminrolle verweigert; künstlicher Fehler nach DELETE und
einem INSERT stellt Regeln sowie Sequenzen vollständig wieder her. Fremde
Fixturetabellen bleiben vom Pausenbefehl unverändert, Original/Backup bleiben
pristin; alle neu erzeugten privaten Fixturedateien wurden anschließend entfernt.
Der Ablauf bootete ausschließlich die CLI zur bestehenden synthetischen Offline-
Adoption: Default-Routen nur `/up`, keine benannte Geschäftsverbindung, keine
Guard-/Login-/Credential-/Sessioninjektion, kein HTTP/Browser/Server. Die zwei
veränderten Bladevorlagen wurden nur für Syntax kompiliert, nicht gerendert.
Vorherige Fehler des Evidence-Helfers sind separat dokumentiert; es wurden keine
Anwendungsfehler durch abgeschaltete Trigger oder schwächere Assertions verdeckt.
