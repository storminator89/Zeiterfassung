# #62 – eigene bisherige Web-/API-Anmeldungen widerrufen

Die bestehende Laravel-Settingsseite enthält für das eigene lokale Konto eine
getrennte, ausdrückliche Aktion zum Abmelden bisheriger Web-/API-Anmeldungen,
ohne ein neues Passwort wählen zu müssen. Vorhandene Karten-/Formularmuster,
DE/EN/ZH, Passwortwechsel und Präferenzformulare bleiben erhalten. Kein neuer
Endpoint, Sessionregister, Schema-/Generation-/Admissionwechsel oder Operator-
Eingriff. Native erhält in diesem Paket keine neue Aktion.

## Enges, tatsächlich implementiertes Konzept

- Nur die vom bestehenden SessionGuard frisch authentifizierte eigene lokale
  Identität und eine vorhandene Rolle; keine Konto-, Rollen-, Quellen-, Hash-,
  Geräte- oder Generationsauswahl aus dem Formular. LDAPkonten erhalten diese
  lokale Aktion nicht. Bestehende Session-/CSRF-/Current-Account-Middleware gilt.
- Separates Formular mit fester `session_action=revoke_web_api`, exakter
  `session_confirmation=1` und aktuellem Passwort. Gemischte Präferenz-/Passwort-
  oder fremde Zielfelder werden verweigert; keine beiläufige Speicherung.
- Dasselbe persistente Konto-/Peer-IP-Passwortbudget wie beim Passwortwechsel,
  vor teurer Prüfung/Erzeugung: der neue Aktionsname gibt keine zusätzliche
  Passwortversuchsquote. Keine eigenen Credentials oder Quotenregeln.
- Das unverändert eingegebene aktuelle Passwort muss stimmen. Keine neue
  Mindestlänge für ein gültiges älteres kurzes Passwort. Über 72 Bytes, NUL,
  leer oder `defaultpassword` wird vor dem frischen Bcrypt-Hash ausdrücklich
  verweigert, **nicht** getrimmt, normalisiert oder gekürzt. Für alte längere
  Passwörter bleibt der getrennte normale Passwortwechsel verfügbar.
- Nur ein bereits gespeicherter Bcrypt-Hash wird für diese Aktion akzeptiert.
  Andere gespeicherte Algorithmen bleiben unverändert; dafür wird der getrennte
  Passwortwechsel angeboten. Der neue Kostenfaktor ist mindestens der bisherige
  und mindestens der aktuell konfigurierte: kein stilles Downgrade für Logout.
  Ein **immer frisch erzeugter** Bcrypt-Hash desselben Passworts rotiert den
  schon vorhandenen Passwortfingerprint. Nicht nur `needsRehash()`: auch ein
  Hash mit aktuellem Kostenfaktor muss neu sein. Normale Anmeldung mit dem
  bisherigen unveränderten Passwort bleibt möglich.
- Der bestehende eigene Writer-CAS bleibt der einzige Schreibpfad: unter seinem
  Lock dieselbe eigene ID/Quelle/Rolle/Passwortversion und bei V5 aktives Konto,
  Generation und lokale Admin-Recovery-Invariante erneut prüfen. Nur der
  Passwort-Hash wird geändert. Die alten öffentlichen Passwortwechselmethoden
  behalten ihre festen Gründe; neue dedizierte Methoden verwenden den festen
  Auditgrund `settings.php: revoke own local Web/API sessions`. Eine private
  gemeinsame Implementierung ersetzt kopierten SQL-/Berechtigungscode; kein
  öffentlich frei wählbarer Auditgrund oder Aktionsschalter.
- Erst bestätigtes COMMIT führt zur vollständigen bestehenden eigenen Abmeldung
  und `303 login.php`. Unbestätigter Ausgang führt zu konservativer eigener
  Abmeldung und festem `503`, ohne Erfolg oder automatische Wiederholung zu
  behaupten. Scheitert nur die Abmeldung nach bestätigtem COMMIT, benennt eine
  eigene feste Meldung genau diese Grenze. Geheimnisse werden nicht geflasht,
  geloggt oder in HTML/Audit/URL zurückgegeben.

## Was „abmelden“ hier nicht bedeutet

Alte Webcookies und JWTs scheitern bei ihrer **nächsten bestehenden geschützten
Passwortversionsprüfung** (`RefreshLocalAccount`/`auth.session`, API-`ver`).
Keine physische Löschung oder Aufzählung sämtlicher Sessiondateien/Tokens und
keine rückwirkliche Beendigung bereits autorisierter laufender Requests.
Wer das Passwort kennt, kann sich wieder neu anmelden. Andere Benutzer bleiben
unverändert. Es ist weder Passwortänderung/Recovery noch Account-Deaktivierung.

V5-`auth_generation` und Kontostatus werden nicht geändert; insbesondere wird
der vorhandene Trigger für echte Status-/Generationsübergänge nicht umgangen
oder aufgeweicht. Separate Terminal-PINs, Geräte und Grants sind kein Ziel
dieser Web-/API-Aktion. Kein LDAP-Sessionwiderruf oder Hardwareabnahmenachweis.

## Grober Nachweis und verbleibendes Issue

Basis ist der tatsächlich gemergte Main
`64776b2fdc67d7634ea3834a0f28f8d1f6dc489b`, Baum
`c0d69d3325182608ec4ad039887a8b460ec1634f`, plus ausschließlich dieses Paket.
PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0. Eine zusammenhängende, neue isolierte
NEW-V5-Installation verwendet normale First-Admin-/Benutzeranlage, mehrere
wirkliche verschlüsselte Cookiejars und normal erzeugte/verifizierte JWTs.
Die API wird nur in diesem Wegwerfbestand über ihre bestehende Adminaktion
bewusst eingeschaltet; kein Listener, echter Betrieb oder Operatorbestand.

Geprüft werden CSRF, gemischtes Formular, falsches aktuelles Passwort und rohe
Bestätigung als sichere Verweigerungen; erfolgreiche eigene Rotation trotz
fremder URL-ID; unverändertes wirksames Passwort, beibehaltener höherer gespeicherter
Bcrypt-Kostenfaktor bei niedrigerem aktuellem Konfigurationswert, Status/Generation und fremdes
Konto; exakt ein geheimnisfreier fester Audit samt seinem Sequenzschritt und
ansonsten unveränderte Datenbanktabellen/Sequenzen. Danach werden beide alten
Cookiejars und das alte JWT verweigert, eine fremde Sitzung bleibt aktiv und
eine normale neue Anmeldung sowie ein neues JWT mit demselben Passwort werden
akzeptiert. Der normale Passwortwechsel verweigert weiterhin dasselbe Passwort.
DE/EN/ZH-Formular, abschließende Integrität und vollständiges Cleanup gehören
zum selben groben Lauf. Keine zusätzliche Repository-Testsuite/manuelle CI.

Keine vollständige Browser-/TLS-/Native-/Directory-/Konkurrenz-/Commitverlust-
oder Betriebsabnahme; keine nichtleeren Terminalgrants oder Hardware geprüft.
Die beschriebenen Fehler-/Lock-/CAS-Grenzen bleiben zusätzlich Quellreview,
keine neu behauptete vollständige Fehler-Injektionssuite.

**#62 bleibt offen** für vollständige geführte Einrichtung/Region-/Regelkonzept,
Einladung/Recovery und tatsächliche Geräte-/Sessionverwaltung. Das neue Feature
liefert einen klaren eigenen Web-/API-Abmeldeweg, nicht diese anderen Funktionen.
Alle ausdrücklich gehaltenen/abgelehnten Pakete sind ausgeschlossen.
