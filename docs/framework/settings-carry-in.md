> Aktualisierung: Der ursprüngliche Laravel-Schritt und seine bewusste damalige
> Native-Kompatibilitätsgrenze werden unten historisch dokumentiert. Die separate
> ausdrückliche Native-Anbindung desselben unveränderten Parsers steht in
> [Native Settings-Übertrag](../native-settings-carry-in.md). Keine neue Ledgerfunktion.

# #79 – eigener Admin-Übertrag mit explizitem Laravel-Zahlenvertrag

Ausgangspunkt ist Main `c7169dedb5537e71641564e3fd2489e6c04787a9`. Nur das
vorhandene eigene Admin-Feld `settings.php` wird angeschlossen: gleiche Karte,
Position, Name `ueberstunden`, Klassen, Icon und native Beschriftung. Keine
Personenauswahl, Konten-/Rollen-/Credential-/LDAP-Schreibfunktion, Änderung alter
Daten, neue Abrechnung, Datenspalte oder Migration. Die bestehende optionale
Settings-Route bleibt standardmäßig deaktiviert; keine Registrar-/Layout-/Auth-
Änderung. Snapshot/Restore bleiben sichtbar gesperrt.

Die Freigabe betrifft **Laravel**: ungültige und nicht endliche neue Eingaben
werden explizit abgewiesen, gültige vorzeichenbehaftete Werte nicht fachlich
begrenzt, gekürzt oder auf Zehntelstunden/Minuten gerundet. Deshalb hat nur das
Laravel-Feld `step="any"` und kein `min="0"`; die vorhandene native UI bleibt
unverändert. Der HTML-Feldtyp ersetzt die serverseitige Prüfung nicht.

## Exakter neuer Eingabevertrag

`settings_carry_in_value()` akzeptiert ausschließlich rohe **Formularstrings**:

```regex
\A[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?\z
```

ASCII-Ziffern, optionales Vorzeichen, Dezimalpunkt und optionaler Dezimalexponent.
Kein Trim, Komma-/Locale-Umbau, numerischer Präfix, Hexformat, bool, Array, Objekt,
null oder Leerstring wird still in eine Zahl verwandelt. Auch direkt übergebene
PHP-int/float-Werte sind kein neuer JSON-/Command-Transport; das bestehende
Formular liefert Strings. Bei Syntaxfehlern, nicht endlicher Konvertierung oder
von null verschiedenem Mantissenwert, der im Zahlenformat zu null unterläuft,
folgt ein ausdrücklicher 400-Fehler ohne Übertragsänderung.

Die repräsentative Dezimalschreibweise muss nach rein textueller Normalisierung
(Vorzeichen, führende/nachlaufende Nullen, Exponent) bei nicht ganzzahligem
binary64-Wert der kürzesten zurücklesbaren Schreibweise entsprechen. Ganze
binary64-Werte verwenden stattdessen die vollständig ausgeschriebene exakte
Ganzzahl, nicht eine kürzere Dezimalschreibweise eines anderen Integerwerts:
so kann etwa `1000000000000000100` nicht still zu `1000000000000000128` werden.
Keine feste Stellenzahl-, Betrags-, Tages- oder Payrollgrenze. Exponenten werden
nicht in riesige Dezimalstrings expandiert; integer-overflow der technischen
Normalisierung führt zur Ablehnung statt numerischer Koercion.

Beispiele: `0.1`, `-12.5`, `+.5`, `1.`, `1e3`, `1.2345678901234567` und
`9007199254740994` sowie die exakte Ganzzahl `1267650600228229401496703205376`
(2^100) sind innerhalb dieses Vertrags repräsentierbar. Dagegen werden
`9007199254740993`, `1000000000000000100`, `0.10000000000000001`, `1e309`,
`1e-400`, leere/Whitespace-/Kommawerte und nachlaufender Text abgewiesen. Das ist
ein konservativer Dezimal-Roundtrip-Vertrag, kein beliebig genauer Dezimalrechner.
Die abschließende SQLite-REAL-Rückleseprüfung kann zusätzlich eine Abweichung des
konkret zugelassenen SQLite-Parsers feststellen und rollt dann alles zurück.
Vorzeichenlose und negative null bedeuten denselben numerischen Saldo null.

`%H` mit expliziter signifikanter Präzision liefert die nicht ganzzahlige
Formatierung, `%.0F` die exakte Ganzzahl ohne Locale- oder
`precision`-/`serialize_precision`-INI-Mutation. Der originale
validierte **String** wird mit `PDO::PARAM_STR`, die Guard-ID separat mit
`PDO::PARAM_INT` gebunden, niemals erst ein Float implizit zurück in einen String.
Das vermeidet zusätzliche `precision=14`-Stellenverluste. Anzeige nach Commit
und bestehende endliche GET-Floatwerte verwenden denselben Roundtrip-String,
damit ein erneutes Settings-Save nicht allein durch PHP-Anzeigeformatierung den
Übertrag verändert. Bereits gespeicherte Werte werden dabei nicht bereinigt.
Siehe [PHP-Formatierung](https://www.php.net/manual/en/function.sprintf.php) und
[PDO-Parameter](https://www.php.net/manual/en/pdostatement.execute.php).

## Unveränderte native Kompatibilität, kein geteilter strenger Parser

Nativ bleiben `settings.php:42–47` mit `isset`/Adminrolle, **wortwörtlich
unverändertem `floatval($_POST['ueberstunden'])`**, ursprünglichem execute-Array
und anschließendem `$userInfo`-Override bestehen. Nur die zwei festen SQL-
Vorbereitungszeilen werden nach `settings_carry_in_prepare()` ausgelagert; dessen
Statement wird nativ auf dieselbe Weise ausgeführt. Das neue Definitionsfile
öffnet keine Verbindung, startet keine Transaktion/Session und lädt weder
Bootstrap noch Schlüssel. Der strengere Parser wird vom nativen Handler nicht
aufgerufen. Native schwache Validierung bleibt ein ausdrücklich eigener
Folgepunkt, nicht heimlich mit dieser Laravel-Frage geändert.

## Eine eigene Spalte, eine vorhandene Writer-/Audittransaktion

`LocalWebCarryIn`/`LegacyLocalWebCarryIn` akzeptieren nur den rohen neuen Wert,
keine Ziel-ID/Rolle/Akteur/Begründung aus dem Request. Der aktuelle lokale Admin
mit positiver Guard-ID ist vor Writerauflösung erforderlich. Der benannte
`AttendanceWriter::ownCarryIn()` prüft Rolle/Quelle derselben Guard-ID nochmals
unter seinem vorhandenen IMMEDIATE-Lock und ändert ausschließlich dessen
`users.ueberstunden`. Es gibt kein Eloquent-save, Konto-CRUD, SQL-Callback,
zweites PDO, natives BEGIN, Retry oder Savepoint.

Der bestehende users-AFTER-UPDATE-Trigger schreibt bereits Vorher-/Nachherwerte
von ID, Username, Regelstunden und Übertrag. Der Writer leitet den Auditkontext
vertrauenswürdig als `web:{guardId}` / `settings.php` ab, entsprechend den
nativen Standard-UDF-Werten. Ohne diesen Kontext würden die Framework-UDFs die
Mutation verweigern. UPDATE und Journal laufen in derselben vorhandenen
Transaktion; vor COMMIT muss der gespeicherte numerische endliche REAL-Wert dem
validierten binary64-Wert entsprechen. Fehler rollen UPDATE, Audit und Sequenz
zusammen zurück. Keine neue Begründungspflicht, Auditart oder Triggeränderung.

**Bewusst unverändert:** last-write-wins, kein Expected-old-/Versionsfeld, auch
identische UPDATEs erzeugen vorhandene Auditzeilen. Kein nachträglicher
Saldo-/Schema-/Altwertrepair. Der aktuelle Übertrag fließt wie bisher in die
bestehenden Home-/Supervisorprojektionen ein; keine neue effektive Datierung,
Abrechnungsperiode, Korrekturbuchung oder Formel.

**Keine falsche Dezimalgarantie:** SQLite REAL ist binary64-Näherung; schon
`0.1` ist mathematisch nicht exakt binär speicherbar. Diese bestehende
Approximation bleibt. Der vorhandene SQLite-`json_object`-Auditformatter kann
zusätzlich weniger Dezimalstellen als die REAL-Roundtrip-Darstellung ausgeben.
Beispielsweise kann `1.2345678901234567` im Journal als `1.23456789012346`
erscheinen. Es wird ausdrücklich **kein verlustfreies Dezimaljournal** behauptet.
Keine neue Auditpräzisionsgrenze und **kein OLD-Wert-409-Hold** werden eingeführt;
auch bestehende Werte werden nicht daraufhin validiert oder automatisch geändert.
Siehe [SQLite Gleitkommazahlen](https://www.sqlite.org/floatingpoint.html).

## Native Sprach-zuerst-Reihenfolge und ehrliche Fehler

Die bisherige Reihenfolge bleibt absichtlich: POST-Sprache → Übertrag → Theme →
Same-page-Erfolgsmodal; Query-Sprache des Headers erst danach. Nativ bricht ein
SQL-Fehler ungefangen nach der bereits gesetzten Sprache ab, Theme/Erfolg werden
nicht mehr erreicht. Laravel behält diese Grenze, zeigt neue Validierungsfehler
als 400 und technische Fehler generisch als 500, jeweils ohne Erfolgsmodal und
ohne spätere Query-Sprachüberschreibung. Fehlermeldungen erklären, dass Sprache
bereits übernommen sein kann. Ein unbestätigter Commit wird nicht als sicher
zurückgerollter oder gespeicherter Saldo ausgegeben: neu laden und Stand prüfen.

Session und SQLite sind keine gemeinsame Transaktion. Nach bestätigtem DB-Commit
können spätere Session-/Renderfehler den gespeicherten Übertrag nicht rückgängig
machen. Kein Compensation-Save oder falsches „alles oder nichts“.
Nichtadmins erhalten für das finanzielle Feld 403 vor Präferenzänderungen;
native stille Ignorierung eines solchen Felds wird nicht als Schreibrecht
übernommen. Der vorhandene Refresh-/Auth-/CSRF-/Sessionblockierungsstack bleibt
unverändert, ebenso normale Userpräferenzen ohne Übertragsfeld.

## Begrenzte Belege / offene Abnahme

Ein grober vollständig erfundener Daten-/Audit-/Rollbackablauf und notwendige
Syntax-/Quellerhaltungsprüfungen, keine ausgeweitete Edgecase-Matrix. Inerte
Settings-Inhalte zeigen das ursprüngliche Feld und explizite Grenzen; echte
CSRF-/Chromefunktionen werden nicht mit künstlicher Sitzung ausgewertet.
Keine wirklichen Konten/Überträge, Guard-/Sessioninjektion, HTTP-/Browser-/Server-
Ausführung, neuen Pakete/CI oder Remoteaktionen. Geschützte Betriebs-/UI-/CSRF-
Abnahme und tatsächliche Aktivierung bleiben separat offen.

Der grobe Ablauf bestand unter PHP 8.5.12 mit ausschließlich neuen privaten
bekannten-v2-Fixturekopien und bestehender Offline-Adoption: präziser String
`1.2345678901234567`, anschließender negativer Ersatz trotz vorheriger nativer
Auditdarstellung, vorhandene No-op-Auditzeile, explizite Eingabeverweigerung,
künstlich abweichender Speicherwert mit vollständigem Daten-/Auditrollback,
gewöhnliches `0.1` und inzwischen entzogene Adminrolle. Der historische Formatter
lieferte dabei `1.23456789012346`; das wurde ausdrücklich **nicht** als neues
Präzisionsgate behandelt. Andere Kontospalten/Personen/Fixturetabellen sowie
Original/Backup blieben unverändert; erzeugte private SQLite-Dateien wurden
entfernt. Default-Routen blieben nur `/up`, keine Geschäftsverbindung/Writerbindung
oder künstliche Anmeldung/Session. Inerte DE/EN/ZH-Inhalte und PHP-/Blade-/JS-Syntax
bestanden. Ein anfänglicher Evidence-Helferfehler erwartete die Admin-Snapshotkarte
auch beim Nichtadmin; nur diese Erwartung wurde auf die bestehende Sichtbarkeit
berichtigt, keine Anwendung/Trigger/Validierung wurde abgeschwächt.

### Begrenzte Ganzzahlkorrektur auf Main 993d7e6

Ein reiner gepaarter Nachweis akzeptiert die exakt darstellbare Ganzzahl 2^100
und bestätigt denselben Anzeige-/erneuten Eingabestring; die gerundete Eingabe
`9007199254740993` bleibt abgewiesen. Nur Formatierungshelper und dieses Dokument
ändern sich. Native `floatval`-Zeilen, Schreib-/Auditpfade, Schema und Altwerte
bleiben unverändert; keine zusätzliche Betrags-, OLD-Wert- oder Auditpräzisionsgrenze.
PHP-Syntax und vollständiger Quellerhalt wurden geprüft, keine Datenbank,
Konten, Session, HTTP-/Browser-/Serverausführung oder Aktivierung.
