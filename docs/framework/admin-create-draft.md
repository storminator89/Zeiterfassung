# #31 / #79 — Eingabenerhalt bei sicherer Anlageablehnung

Begrenzter inaktiver Laravel-UI-Schritt auf Main
`a022ff460970ba753062e717dc418a41d006253f`, Baum
`35bcee885fda511f09b99b4f496e1448a1a56cf6`. Native besitzt bereits den
begrenzten Entwurf. Laravel leert und versteckt das Formular bisher auch nach
der ausdrücklich dokumentierten Pflichtfeldablehnung.

## Nur der sicher belegte FALSE-Pfad

Der unveränderte `LocalWebAdminUsers`-Vertrag definiert `false` ausschließlich
als bisherige Required-field-Ablehnung. `LegacyLocalWebAdminUsers` delegiert
weiter an denselben `AttendanceWriter::createLocalUser`: dessen einziges
`return false` steht bei fehlenden Pflichtwerten **vor** `executeLocked`, Hashing
und INSERT. Die eigentliche Transaktionscallback gibt ausschließlich `true`
zurück; Fehler/Commitunsicherheit werden weiter geworfen. Der Writer und sein
Zulassungs-/Intake-/Auth-/V5-Vertrag werden nicht geändert oder umgangen.
Dies ist kein generelles Nichtschreibattest für Bootstrap, Session oder Admission.

Nur an genau dieser unveränderten booleschen Rückgabe wird der bereits gemergte
reine `lib/admin_create_user_draft.php`-Helfer aufgerufen. Vorher werden wie beim
bestehenden Dienst beide CSRF-Felder aus dem Anzeigeargument entfernt. Der Helfer
verlangt weiterhin exakt `add_user=1`, bekannte Feldnamen und begrenzte einzelne
UTF-8-Strings ohne ASCII-Steuerzeichen/DEL. Nur Benutzername, E-Mail und die zwei
bestehenden Zahlenfelder werden übernommen; ungültige oder zu große Werte sind
`null`, keine Umdeutung/Kürzung oder neue fachliche Zahlenvalidierung.

Passwort, gepostetes CSRF, Rollen, Beziehungs-IDs, LDAP-/Tokenwerte werden nicht
in den Entwurf kopiert. Passwortfeld und frisches stock `@csrf` bleiben
unverändert. Der bekannte Fehler öffnet das Formular; alle vier Werte werden
Blade-escaped ausgegeben, alle drei Auswahlen stehen auf deaktivierten festen
Prompts. Rolle, Abteilung und Vorgesetzter müssen ausdrücklich neu gewählt
werden; die bestehende ausdrückliche Wahl „Kein Vorgesetzter“ bleibt erhalten.
Ein ausgelassener Vorgesetzter wird vom unveränderten Servervalidator abgelehnt,
nicht still zu keiner Beziehung. Die bisherige Fieldset-/Intake-Sperre bleibt.

GET, Erfolg, alle Exceptions einschließlich `InvalidArgumentException`,
Auth-/Policyverweigerung, PDO-/unbekannter Ausgang und andere/zusätzliche
Aktionsfelder zeigen keinen Entwurf. Insbesondere wird **nicht** aus einem
beliebigen Validierungsfehler ein sicherer Vor-Schreibfehler abgeleitet. Dies
portiert bewusst nur den belegten FALSE-Teil des Native-Verhaltens, nicht dessen
separat markierten Assignmentexception-Pfad. Bestehende Status-/Fehler-/Erfolgs-
und Rereadsemantik bleiben; kein Redirect, Retry, automatischer Save, versteckter
POST, Session-/Flash-/Browserentwurf, neue Auswahlpolicy oder neue Übersetzung.

## Grober Nachweis und Grenzen

PHP 8.5.12 / Laravel 13.38.0 führt den tatsächlichen alten/neuen Controller,
DTO und vollständiges stock Blade gegen feste dokumentierte Servicegrenzen aus.
Required-field FALSE, GET, Erfolg, Invalid-/Domain-/PDO-/unbekannter Fehler,
Auth-/Rechteverweigerung, gemischte/zusätzliche Felder, nichtkanonischer Marker
und unbrauchbare Anzeigewerte sind verglichen: gleiche Status/Fehler/Erfolge,
Serviceargumente und Readaufrufe. Nur FALSE übernimmt vier bounded Strings;
Escaping, leeres Passwort, frisches synthetisches CSRF, explizite Neuauswahl und
keine geposteten Werte/Entwürfe in der Session sind geprüft. Normale GET-/Erfolgs-
Felddefaults und alle bisherigen Auswahl-/Sperrregeln bleiben erhalten.

Kein tatsächlicher Service/Writer, keine Datenbank oder Admission wird im Fixture
konstruiert oder per Reflection umgangen. Die Nichtschreibgrenze wird separat
am unveränderten Interface-/Adapter-/Writerquellvertrag geprüft, nicht als
tatsächlicher Datenbank-/V5-/Commitverlustnachweis ausgegeben. Ein entfernter
alter Vendorhostpfad wurde durch einen vorhandenen gleichversionigen Host
ersetzt; aktuelle Appklassen werden ausdrücklich auf diesen Kandidaten gemappt.

Syntax/kompiliertes Blade, vollständige Quellerhaltung und Inverse werden separat
belegt. Keine neue Repositorytestsuite, CI-Auslösung, Installation, Runtime-
aktivierung oder Bereitstellung. Kein vollständiger App-/Service-/Writer-/Auth-/
Session-/CSRF-/Middleware-/HTTP-/Browser-/Form-/RealDB-/V5-/Operationsnachweis.
#31/#79 bleiben für breitere Validierung, Ausnahmefälle und echte Abnahme offen.
