# #79 — exakte IDs im bestehenden Laravel-Admineditor

Begrenzter Korrekturschritt auf Main
`4805305adf2df85cd61521fb695674ef3cd30bfa`, Baum
`d9c8235a3ed2d8dcdbe2456db7b24c5d206f723e`. Die vorhandene frühere
Exact-ID-Vorarbeit wird additiv wiederverwendet, nicht neu implementiert.
Kein neuer Writer, neue
Validierungsregel, native API-Änderung oder Runtimezugang.

## Tatsächlich isoliert reproduzierter Defekt

Der aktuelle `adminUserDetail`-Reader liefert SQLite-Integer als numerisches
PHP-JSON. Der bestehende Browserclient rundet `9007199254740993` zu
`9007199254740992`. Bei einer großen Nutzer-ID verhindert sein bisheriger
Requestvergleich deshalb das Öffnen. Bei einer Department-/Supervisor-ID wird
der gerundete Wert dagegen in die Auswahl übernommen. Existiert der gerundete
Nachbar als Option, ist genau diese falsche Zuordnung vorausgewählt und der
bestehende Speichernknopf freigegeben. Dies wurde mit tatsächlichem Reader,
PHP-JSON und kompiliertem Detailclient gegen erfundene Memory-/DOMdaten gezeigt;
keine echte Mutation oder Produktionsauswirkung behauptet.

## Additiver privater Webvertrag

Der gleiche feste SELECT ergänzt ausschließlich `id_string`,
`department_id_string`, `supervisor_id_string` durch `CAST(... AS TEXT)` aus
derselben Zeile. NULL bleibt NULL. Alle bisherigen Rohfelder, Werte und Typen,
JOINs, gebundene Identifier, Existenz-/Fehlerfälle, Actor-/Admin-/Local-Scope und
Snapshot bleiben erhalten. Kein `SELECT *`, Passwort, Token oder Authsource im
Ergebnis; keine öffentliche API-/Extension-/Native-/Swagger-Vertragsänderung.
Der bestehende private Controller, dessen JSONstatus/Headers und Writer bleiben
unverändert. Alte Clients sehen dieselben ursprünglichen Felder weiter.

Im bestehenden Detail-JS ist die bestätigte exakte positive Dezimalstring-ID bis
SQLite-Maximum maßgeblich. Sie muss zum angefragten Datensatz passen; vorhandene
numerische Felder dienen nur der Konsistenzprüfung, niemals der Konstruktion
großer IDs. Sichere alte Integer und bereits exakte Strings bleiben unterstützt.
Unsichere numerische Altantworten ohne passende Textmetadaten werden nicht mehr
als neue Referenz vorausgewählt. Fehlende, widersprüchliche oder unbrauchbare
Metadaten lassen den bisherigen Fehlerpfad alle Details leeren und Speichern
sperren; kein Raten/Record0/Default auf einen Nachbarn.

Alle drei Identitätswerte werden bestätigt, bevor ein Formularfeld gefüllt wird.
NULL-Zuordnungen bleiben NULL. Bereits gespeicherte 0-/negative Integer oder
unveränderte Stringreferenzen werden exakt als bestehende Auswahl zur bewussten
Korrektur erhalten, nicht als gültige Beziehungen attestiert oder still repariert.
Nicht bestätigbare Darstellungen werden gesperrt. Die unveränderte serverseitige
positive ID-/Beziehungsvalidierung bleibt letzte Autorität. Keine neue Beziehung
oder Quelle, Rollen-/Zahlenprüfung oder automatische Reparatur wird eingeführt.

Der bisherige Requestzähler, Blank-while-loading, veraltete Antworten, Cancel,
verzögerte Close-Events, Pageshow-/Einmal-Submit-/Passwortserialisierungsregeln
bleiben erhalten. Bestehende feste Fehlermeldung, `textContent`, sichere Option-
Erzeugung und Inputproperties bleiben; kein HTML aus Metadaten, zusätzlicher
Fetch, Retry, Edit-/Delete-/Form-/CSRF-/CSP- oder Asset-/Pin-/Providerdelta.
Insbesondere #31-Entwurf und #34-Namenstatus bleiben bytegleich.

## Grober Nachweis und Grenzen

PHP 8.5.12 / Laravel 13.38 / Node 24.22.1: tatsächlicher aktueller Reader und
stock Snapshot über query-only Memory → tatsächliches PHP-JSON → stock-Blade-
kompilierter Detail-JS mit synthetischem DOM/Transport. Großer Nutzer, beide
großen Referenzen und SQLite-Maximum bleiben exakt. Nach Entfernen nur der drei
Zusatzfelder sind ursprüngliche APIwerte/-typen auch mit voller Integerpräzision
gleich. Sichere Altantwort, NULL und 0/-1 bleiben gleich; unbestätigte/beschädigte
Antworten sperren. Frischer Nichtadmin wird trotz gecachter Adminrolle verweigert;
Quellwerte und geschlossener Snapshot bleiben erhalten. Race/Cancel/verspätetes
Close sowie Passworterhalt und wiederholtes Submit zeigen denselben Zustand.

Syntax, vollständige Reader-/Bladeinverse und alle übrigen Quellbytes/Modi werden
separat belegt. Keine echte App-/Auth-/Session-/HTTP-/Browser-/POST-/Writer-/
Mutations-/V5-/Betriebsabnahme; keine echten Daten, Accounts oder Credentials.
Keine neue Repositorytestsuite, CI-Auslösung, Installation oder Bereitstellung.
#31 ist separat für seine ursprünglichen serverseitigen Kriterien abgeschlossen;
seine Feldvalidierung bleibt vollständig erhalten. Diese UI-/JSON-Korrektur ist
ein begrenzter #79-Folgeschritt. #79 bleibt wegen seiner übrigen ursprünglichen
UI-/Rollen-/Routen-/Operationskriterien offen. Die Browserabnahme übernimmt der
Nutzer; hier wird keine tatsächlich ausgeführte Browserabnahme behauptet.
