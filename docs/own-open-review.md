# #60: eigene offene gespeicherte Einträge sichtbar machen

Isolierter Quellschritt auf Main `e2675fa3e1c3cae802b973e4e8a64f4e8d2d3a7d`,
Baum `319c34380724be563e19fc8abc1b5197bede7a94` nach #31/#251. Die bestehende
native Anwesenheitsseite erhält eine reine zusätzliche Karte. Alte offene Zeilen
außerhalb der bisherigen letzten 30 Einträge können damit gefunden und gezielt
im bestehenden eigenen Korrektur-/Stornopfad angesehen werden. Keine neue
fachliche Regel oder automatische Erinnerung/Benachrichtigung wird eingeführt.

## Tatsachen statt vermuteter Fehler

Die Karte zeigt ausschließlich eigene gespeicherte Zeilen ohne Ende (`NULL`
oder leerer Endmarker), deren gültiger Start vor der heutigen Berliner lokalen
Mitternacht liegt oder deren Start nicht streng lesbar ist. Dies ist keine
24-Stunden-Grenze und kein gesetzlicher/betrieblicher Arbeitszeit-, Ruhezeit-
oder Kernzeitschwellenwert. Heutige/zukünftige gültige Starts und Zeilen mit
gespeichertem Ende gehören nicht zu dieser engen Auswahl. Eine unlesbare
Endzeit ist deshalb hier kein neuer vollständiger Datenqualitätsbericht.

**Eine reguläre Nachtschicht kann erscheinen.** Der Hinweis sagt ausdrücklich,
dass dies weder vergessene Arbeit noch eine Regelverletzung beweist. Laufende
Arbeit soll regulär beendet werden; nur bei tatsächlichem Klärungsbedarf ist ein
begründeter Antrag mit Entscheidung einer anderen berechtigten Person vorgesehen.
Kein Vorschlag zur Massenkorrektur, keine fiktive Endzeit/Pause, kein automatisches
Schließen, Mutationsformular oder neuer Freigabepfad. Der Link enthält nur die
vorhandene `record_id`; Auswahl-/Eigentums-/Antrags-/Vier-Augenprüfungen bleiben
vollständig im bestehenden Korrekturbackend.

Die Liste ist ein datierter Sachstand beim Seitenabruf, kein Livebestand. Nach
einer Buchung kann der feste GET-Link `attendance.php` den Sachstand neu laden;
er wiederholt keine Mutation. Keine neuen JavaScript-, Timer-, Pending-, Replay-,
API-/Status- oder POST-Felder. Heutige Zeit und Tagesgrenze stammen allein vom
Server in Europe/Berlin, einschließlich 23-/25-Stunden-Tagen und Offsetdaten.

## Eigener frischer Snapshot und begrenzte Darstellung

Die neue definitions-only native Readgrenze verlangt einen idle PDO und besitzt
genau eine Lesetransaktion. Darin werden die aktuelle native Kennwortversion und
eine bestehende Rolle geprüft. Count und Treffer verwenden anschließend dieselbe
feste Own-/Open-/Datumsbedingung. Auch Admin und Supervisor sehen hier niemals
fremde Personen; keine Rolle oder Personenauswahl stammt aus einem Requestfeld.
Fremde Callertransaktionen werden weder übernommen noch zurückgerollt. Fehler
rollen die eigene Transaktion zurück und liefern keine Teilprojektion.

Eine gültige Tagesgrenze wird über lokale Mitternacht gebildet, nicht durch
Abziehen von 86400 Sekunden oder durch Rohtext-/UTC-Präfixe. Der unveränderte
strenge `report_stored_epoch`-Parser klassifiziert unlesbare Starts als unbekannt,
nie als heutiges Datum. Count und Sortierung/Filter liegen vor `LIMIT 5`:
unlesbare Starts zuerst, danach die ältesten echten Startinstants und stabile ID.
Eine höhere Gesamtzahl bekommt einen expliziten Begrenzungshinweis. Die Grenze
beschränkt zurückgegebene/materialisierte Zeilen, nicht den SQLite-Scan oder
Parseraufwand des Counts; kein Index-/Schema-/Ganzseitenperformanceversprechen.

Die DTO enthält nur Abrufzeit, betrachteten Tagesanfang, Anzahl, Begrenzung und
maximal fünf eigene String-IDs mit gültig formatiertem Beginn oder festem
Unlesbar-Marker. Notiz, Arbeitsort, Abwesenheitsart, Personenname, Kennwort/
Fingerprint und unlesbarer Rohtext werden nicht ausgegeben. Dynamische Werte
sind escaped; ein Link muss keine JavaScript-Integerkonvertierung durchlaufen.
Keine Treffer erzeugen keine „alles in Ordnung“-Aussage. Ein Lesefehler ist
ausdrücklich „nicht verfügbar“, niemals eine leere geprüfte Liste.

Die neue Karte folgt dem unveränderten nativen Bootstrap und bleibt innerhalb
des bestehenden eigenen HTMLabrufs und `no-store`-Transports. Kein neuer Login,
Logout, Rollenwechsel, Credential-/Profil-/Datenbankzugang oder Native-V5-Gate.
Das ist keine Behebung des separaten breiten Native-/V5-Kompatibilitätsgates.
Status-JSON, Buchungs-POST, sämtliche bisherigen HTML-/Form-/JS-Hooks und Writer
bleiben quellseitig unverändert. Die ganze Seite ist dadurch kein gemeinsamer
atomarer Snapshot ihrer schon vorhandenen anderen Reads.

## Grober Nachweis und offene Abnahme

PHP 8.5.12 / SQLite 3.46.1: ein anonymer Memory-/tatsächlicherPHP-Partial-Journey
mit erfundenen Zeilen prüft Ownscope für User/Admin/Supervisor, alte Treffer
außerhalb neuer 30er-Zeilen, gleiche Count-/Fünferauswahl, heutige/zukünftige/
geschlossene Zeilen, unlesbare/relative Starts ohne Rohtextausgabe, große exakte
String-ID, Berliner DST-/Offset-Tagesgrenzen, aktuelle Version/Rolle und neue
Operation, Transaktions-/SQLverweigerung/Rollback sowie tatsächliche escaped
Karte, neutrale Nachtschichttexte und reine Korrektur-/GET-Links ohne Massenknopf.
PHP-Syntax, null Sprachoutput in DE/EN/zh und vollständige Quellerhaltung sind
getrennt geprüft. Keine neue kleinteilige Repo-Testsuite oder neue CI.

Kein App-/Login-/Session-/CSRF-/HTTP-/Browser-/Layout-/Prozesskonkurrenz- oder
tatsächlicher Daten-/Betriebsnachweis; keine echten Konten/Credentials gelesen,
keine Remoteaktion, Host-/Security-/Runtimeänderung, V5-Aktivierung oder Release.
#60 bleibt offen: aktive Erinnerungen und betriebliche Regeln samt Zuständigkeit,
Ausnahmen und realer UI-/Betriebsabnahme erfordern weiter explizite Entscheidungen.
