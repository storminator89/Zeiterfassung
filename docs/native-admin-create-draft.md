# #31 — eingegebene Anlagefelder nach bekannter Ablehnung weiterbearbeiten

Lokaler Quellschritt auf Main `d4cadf928f147bdca8ff323b8cb5182e6c7d1ef4`, Baum
`e9db7ad04c34ba2f6433cea5bb5107893bb02078`. Nur das vorhandene native
„Benutzer anlegen“-Formular; kein neuer Validator, Writer oder Runtimezugang.

## Enger bekannter Fehlerpfad

Das bestehende Formular öffnet sich wieder und zeigt vier begrenzte Eingaben
zur Überarbeitung: Benutzername, E-Mail, Regelarbeitszeit und Anfangsüberstunden.
Das geschieht ausschließlich bei der bisherigen Prüfung fehlender Pflichtwerte
mit inaktiver Transaktion oder wenn **der bestehende Assignmentvalidator vor
dem INSERT** seine bekannte `InvalidArgumentException` ausgelöst hat und der
bisherige Transaktionshelfer danach keine aktive Transaktion mehr meldet.
Die gleiche Exception wird unverändert weitergeworfen/angezeigt; CRUD-/SQL-,
Validierungs-, Erfolgs- und Rollbacklogik werden nicht ersetzt.

Erfolg, PDO-/unerwartete Fehler, unbekannter COMMIT-Ausgang, offene/fehlgeschlagene
Rücknahme und andere/mehrere Aktionsmarker erhalten **keinen** Entwurf. Der reine
Helfer akzeptiert nur die bekannten Feldnamen dieses tatsächlichen Formulars
und genau `add_user=1`; weitere oder unbekannte Felder unterdrücken die Übernahme.
Dies ist allein eine Anzeigegrenze, keine zusätzliche Annahme-/Schreibregel.

## Keine Credential- oder Zuordnungsübernahme

Nur die vier genannten Stringfelder werden kopiert: höchstens1000Bytes je Feld,
gültiges UTF-8, keine ASCII-Steuerzeichen/DEL. Array-/Objekt-/Zahlwerte oder
ungültige/zu große Werte werden nicht umgedeutet oder gekürzt. Alle Ausgaben
werden mit dem bestehenden `h()` escaped; unverwendbare/leere Felder muss die
Person erneut eingeben. Insbesondere Zahlenfelder bleiben ihre bisherigen
HTML-Zahlenfelder; dies ist keine neue fachliche Zahlenvalidierung oder Zusage,
dass ein Browser jede syntaktisch ungültige Zahl sichtbar halten kann.

Passwort, gepostetes CSRF, Tokens, LDAPwerte, PINs und andere Felder werden nie
aus dem Request in den Entwurf übernommen. Das Passwortfeld bleibt ohne
`value`; das ursprüngliche frische `csrf_field()` bleibt unverändert. Auch Rolle,
Abteilung und Vorgesetzter werden **nicht** übernommen. Alle drei Auswahlen
stehen im Fehlfall auf einer festen deaktivierten Aufforderung zur ausdrücklichen
Neuauswahl statt auf dem ersten oder einem alten Datensatz. Beim optionalen
Vorgesetzten bleibt die vorhandene ausdrückliche Wahl „Kein Vorgesetzter“ möglich.
Die normalen aktuellen Optionen und serverseitigen Zuordnungsprüfungen bleiben
letzte Autorität; keine neue Identität, Rolle oder Beziehung wird eingeführt.

Keine Session-/Cookie-/Browserablage, kein Log, Redirect, neuer Endpoint,
automatischer POST/Retry, Passwortvorausfüllung oder versteckte Wiederholung.
Der Entwurf lebt nur in dieser fehlgeschlagenen PHP-Antwort. GET und Erfolg
behalten die alten leeren/Defaultwerte und das versteckte Formular. Vorhandener
Toggle, Edit-/Löschmodals, LDAP-, Token-, Pausen-, Abteilungs-, Audit-, Header-,
Auth-/CSRF-/V5-/Runtimecode bleiben erhalten. Es wird kein Legacy-Bootstrap als
schreibfrei oder geheimnisfrei attestiert.

## Grober Quellnachweis und Grenzen

PHP8.5.12 führt den tatsächlichen vorhandenen Add-User-POSTblock und dieselben
`admin_create_user`-/`validateUserAssignment`-Definitionen sowie das tatsächliche
Formfragment gegen eine erfundene anonyme Memorydatenbank aus. Alte/neue Fehler,
Erfolge, sichtbare Userfelder, Änderungszahlen und Transaktionszustände stimmen
überein. Fehlende Pflichtwerte/ungültige Rolle/veraltete Abteilung zeigen nur
den begrenzten Entwurf; Escaping, Credential-Canarys, explizite Neuauswahl und
frisches CSRF sind geprüft. GET, Erfolg, Unique-/PDOfehler, gemischte/zusätzliche
Felder und offene Transaktion zeigen keinen Entwurf.

Ein enger PDO-Fixture überschreibt COMMIT als „tatsächlich committed, danach
synthetischer Fehler“ und ROLLBACK als „synthetisch fehlgeschlagen, Transaktion
noch offen“. Die bisherigen Outcomes/Zustände bleiben gleich und werden nicht
als bekannte Ablehnung für eine Vorausfüllung benutzt. Dies ist **kein** Beweis
für realen Antwortverlust, sämtliche Treiberausfälle oder Betriebsrecovery.
Alle Fixturetransaktionen werden anschließend außerhalb des Appfragments bereinigt.

Notwendige PHP-Syntax, vollständige Quellerhaltung und Inverse werden separat
geprüft. Keine neue Repositorytestsuite oder CI; kein vollständiger App-/Security-/
Header-/LDAP-/Login-/Auth-/CSRF-/HTTP-/Browser-/Modal-/Formsubmit-/Produktivnachweis.
Keine echten Personen, Konten, Credentials, Keys, Dateien mit Betriebsdaten oder
Bereitstellung. #31 bleibt für übrige Datenvalidierung und echte UI-/Betriebsabnahme offen.
