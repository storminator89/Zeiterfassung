# #79 – vorhandene Benutzerliste und Details, ausschließlich lesend

Basis ist die bereits optionale Adminseite mit Pausen-/Abteilungskarten. Die
originale Karte `admin.php#user-management` ergänzt Benutzerliste, vorhandenes
GET-Suchfeld, Zehner-Paginierung und den vorhandenen Benutzerdialog. Klassen,
Spalten, Icons, IDs, ausgewählte Originaltexte DE/EN/ZH und der feste Link auf
`work_models.php` bleiben erhalten. Es gibt keine zweite Adminoberfläche und
keine Konten-, Rollen-, Passwort-, LDAP-, Token- oder Carry-in-Schreibfunktion.

Erstellen/Löschen/Speichern sind sichtbar deaktiviert und erklärt. Die
Detailfelder sind readonly/disabled, das neue Passwort bleibt immer leer und
deaktiviert. Der Dialog enthält **kein** Schreibformular und kein `edit_user`-
Aktionsfeld. Der ursprüngliche mehrteilige Benutzer-Anlegebogen wird nicht als
vermeintlich funktionierende Form versteckt mitgeliefert. Die bestehenden
Pausen-/Abteilungsaktionen bleiben unberührt; User-/gemischte/unbekannte POSTs
bleiben 400, bevor Reads/Writes/Preferences erfolgen. Kein Fake-Save oder Erfolg.

## Minimaler Datensatz statt Wildcard/Model-JSON

Native Quellen: `admin.php:505–540` (Such-/Listen-/Countabfragen), `647–707`
(Suche/Tabelle/Paginierung), `885–958` (Detaildialog), `1060–1083` (frisch laden);
`get_user.php:18–32` (Detailabfrage). Die native Liste verwendet `users.*`; der
native Detailendpunkt entfernt aus `SELECT *` nur `password`. Diese Wildcard-
Grenze wird **nicht** übernommen: insbesondere vorhandenes `token` und
`auth_source` gehören nicht zur hier tatsächlich dargestellten UI. Native Dateien
werden nicht verändert oder eingebunden. Kein `config.php`-/LDAP-/Secret-Bootstrap.

`ChronoReads::adminUsersPage()` projiziert explizit nur die acht bisherigen
Tabellenwerte: `id`, `username`, `email`, `role`, `department_name`,
`supervisor_name`, `regelarbeitszeit`, `ueberstunden`. In demselben bestehenden
DEFERRED-Snapshot liegen native LEFT JOINs, Username-/E-Mail-LIKE, Reihenfolge,
zehn Zeilen, Gesamtzählung und Seitendaten. LIKE-Wildcards `%`/`_`, native skalare
`(int)`-Seitenumwandlung und fehlendes Clamping bleiben bestehen: Null/negative
Seiten werden nicht neu auf 1 normalisiert; eine Seite hinter dem Ende bleibt
leer; null Treffer haben null Seitenbuttons. Kein neuer Owner-/Team-/Auth-Source-
Filter auf den Zielkonten und kein neues Sortierkriterium. Die normalen sichtbaren
Werte werden nicht neu berechnet, gerundet oder als aktuelle Arbeitszeitbilanz
umgedeutet. Das angezeigte `ueberstunden` bleibt der ursprüngliche Carry-in-Wert.

`ChronoReads::adminUserDetail()` lädt auf Öffnen des Dialogs frisch dieselben
UI-Werte plus die bereits vom Originaldialog benutzten `department_id` und
`supervisor_id` – insgesamt maximal zehn explizite Schlüssel. Die frischen
zugehörigen Namen stammen aus denselben LEFT JOINs. Die deaktivierten Auswahlen
benötigen nur den jeweils aktuellen zugehörigen Eintrag, keine zusätzliche
vollständige Supervisor-/Kontenliste im Client. Fehlende Beziehungsnamen bleiben
leer bzw. „kein Vorgesetzter“ wie nativ. Es werden keine Credentials, Tokens,
Auth-Quellen, Eloquentmodelle, unbekannten Tabellenspalten oder LDAP-Werte gelesen
oder serialisiert. Die für Auth erforderliche interne Actor-Rollen-/Quellenprüfung
ist davon getrennt und wird nicht als Benutzerprojektion zurückgegeben.

Beide neuen Readmethoden laden die Actorrolle und Quelle innerhalb des vorhandenen
Snapshots erneut und verlangen aktuellen **lokalen Admin** vor jeder Benutzer-
Listen-/Count-/Detailabfrage. Requestrolle/Subject-ID sind niemals Actoridentität.
Ein lokaler Admin sieht wie in der bisherigen nativen Adminliste auch
LDAP-Zielkonten; das ist keine Zulassung einer LDAP-Anmeldung im Framework.

## Gleicher geschützter Opt-in, vorhandener Detail-GET

Nur innerhalb des bestehenden `includeAdminPauseSettings: false`-Flags wird
zusätzlich der originale `get_user.php`-GET/HEAD registriert. Kein neues Flag,
Defaultbootstrap, Sidebarmenü oder generischer Verwaltungsendpunkt. Die aktuelle
Sessionblockierung sowie Session/CSRF → RefreshLocalAccount → `auth:web` →
`auth.session` bleiben unverändert. Kein POST-Detailendpunkt, keine Erweiterung
des Raw-Form-Ausnahmepfads. Der feste synthetische Router kennt lediglich diesen
zusätzlichen bestehenden Pfad; kein Server wird durch das Paket gestartet.

Die ursprüngliche Fresh-on-open-Interaktion bleibt ein GET an den festen lokalen
Pfad mit `encodeURIComponent(id)`. Antworten werden nur in `.value`/DOM-Optionen
übernommen, niemals als HTML oder JavaScript. Ein altes Modal wird vor dem Laden
geleert/geschlossen; überholte Antworten werden ignoriert. Ein fehlgeschlagener
Read öffnet keine alten Benutzerdetails und zeigt eine vorhandene Alert-artige
Fehlerfläche. Suche, Tabellenwerte und Fehlerausgaben werden escaped; Suchlinks
codieren ausschließlich den Suchwert in den festen relativen Querypfad.

**Explizite Transportgrenzen:** Nichtskalare Such-/Seitenparameter werden mit
400 verweigert statt eine Suche still zu verbreitern; normale native skalare
Seitenwerte bleiben unverändert. Nach frischer Adminprüfung ergeben fehlende/
nichtskalare Detail-IDs 400, nicht gefundene gebundene skalare IDs 404; die
native SQLite-ID-Coercion wird nicht durch einen neuen Positive-ID-Validator
ersetzt. Der Detail-GET bleibt außerhalb der vorhandenen Raw-Form-Ausnahme:
Laravels unveränderte Query-Normalisierung kann leere oder reine Whitespace-IDs
vor dem Reader zu null umwandeln. Diese ergeben deshalb 400 statt des nativen
404 bei einer leer gebundenen skalaren ID; die normalen numerischen IDs der
vorhandenen Zeilenbuttons sind nicht betroffen. Keine Middleware wird dafür
geändert. Nichtadmin ergibt 403, abgelaufene Frameworkanmeldung die bestehende
Nicht-Redirect-401-Art des geschützten Transports. Technische Fehler geben nur
generische 500-Texte zurück, niemals PDO-/Pfad-/Personendiagnosen. Seiten-/Detail-
Antworten sind `no-store`, Detailantworten außerdem `nosniff`. Keine zusätzliche
PII gegenüber den tatsächlich genutzten Originaltabellen-/Dialogfeldern wird dem
Client angeboten; insbesondere kein Übernehmen der nativen Token-Nebenprojektion.

## Nachweis und offene Abnahme

Ein einziger grober Ablauf mit erfundenen In-memory-Zeilen prüft die konkreten
extrahierten Listen-/Count-/Detail-SQL-Projektionen, Gesamtumfang und Escaping der
vorhandenen Inhaltsschablone. Notwendige PHP-/Blade-/JS-Syntax und Quellerhaltungs-
prüfungen ergänzen ihn; keine neue kleinteilige Testmatrix. Keine realen Accounts,
Credentials, Guard-/Sessioninjektion, HTTP-/Browser-/Serverausführung, Installation,
CI-/GitHub-Schreibvorgänge oder Produktivaktivierung. Frische Autorisierung ist
hier quellengeprüft, nicht durch eine künstliche Sitzung bewiesen. Geschützte
Anmelde-/Browser-/Datenschutz-/UI-Abnahme bleibt separat offen.

Der grobe Nachweis bestand unter PHP 8.5.12: 14 frei erfundene In-memory-Konten,
13 passende Ergebnisse (auch nur E-Mail-Treffer und ein LDAP-Zielkonto), Seiten
mit 10/3 Zeilen und deckungsgleichen sichtbaren Werten zur ursprünglichen nativen
Listenabfrage. Die konkreten Framework-SQL-Literale ergaben ausschließlich acht
Listen-/zehn Detailschlüssel; synthetische Passwort-/Token-/zukünftige-Privatspalten
erschienen nicht in der Projektion. Die echte Benutzerautorisierung wurde dabei
nicht mit erfundenem Guard ausgeführt, sondern die unveränderte Snapshot-Hülle
und ihre vorgeschalteten lokalen Adminprüfungen quellengeprüft. DE/EN/ZH-Inhalte
inklusive Suchwert, Namen und Fehlern waren escaped; Benutzeraktionen enthielten
keine schreibenden Formulare. Die vorhandene echte CSRF-Direktive wurde nur
kompiliert/gelintet, nicht ausgewertet; JavaScript wurde nur auf Syntax geprüft.
