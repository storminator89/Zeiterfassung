# Native Rücknahme eigener offener Anträge — Teilumfang #50

## Separater, unabhängig freigegebener Umfang

Quellbasis Main `7c6da285ae81366a113b5767ff1c5fe456098229`, Tree
`4a61e14fdecb2a2664a607d0b042d36e5b3c0534`, 29.10.2026 nach PR #347/#348/#349.
Die separate Fertigstellung und der eigene Merge nach Prüfung wurden ausdrücklich
freigegeben. Der zurückgehaltene PR #239 bleibt unverändert und ist **keine**
Abhängigkeit: kein Zeithelper-Script, Fieldset, Formular-ID, Asset oder Blade-
Refactor daraus wird übernommen. Der alte auf #239 beruhende lokale Kandidat
wird nicht veröffentlicht und dessen historische Release-Gates nicht aufgehoben.

Die bereits vorhandene native Korrekturseite erhält ausschließlich eine bewusst
aufzuklappende Rücknahme für eigene noch offene Anträge. Dies umfasst die alten
Zeit-/Stornierungs-/Nachtragsanträge **und** die inzwischen vorhandenen Ort-/Notiz-
Anträge: Alle sind Anträge derselben Tabelle und werden durch denselben bereits
veröffentlichten Rücknahmekern beendet. Der Hinweis benennt ausdrücklich, dass
Buchung, Zeiten, Ort und Notiz unverändert bleiben. Ein neuer Antrag ist möglich;
der zurückgenommene Antrag wird nicht wieder geöffnet.

## Bestehende Autorität und Transaktion, keine zweite Fachregel

Der definitions-only Adapter ruft `correction_withdraw_locked` unverändert mit
seinem bisherigen Default `local` auf. Keine Ausweitung auf LDAP, keine neue
Schema-/Trigger-/Auth-/Runtime-/Hostkonfiguration und kein neuer Endpunkt.

- Die unveränderte native `config.php`-/Securitykette authentifiziert vor dem
  Seitenzweig und prüft POST-CSRF. Das Formular verwendet `csrf_field()` wie die
  vorhandenen Request-/Reviewformulare. Das Aufklappen sendet nichts.
- Identität, Rolle und Passwortfingerprint stammen nur aus der serverseitigen
  Sitzung. Die Aktion akzeptiert ausschließlich `csrf_token`, `action`, `id`
  und `withdraw_reason`. Zusätzliche Actor-/Rollen-/Quellen-/Status-/Auditfelder
  werden verworfen durch Verweigerung, nicht ausgewertet. Der Fingerprint wird
  nie gerendert.
- Der native Caller besitzt denselben `BEGIN IMMEDIATE`-/COMMIT-/ROLLBACK-
  Lebenszyklus. Der vorhandene Kern lädt das Konto unter diesem Lock erneut,
  prüft aktuelle Quelle, Rolle und Passwortfingerprint und schränkt Lesen und
  UPDATE identisch auf Owner/Pending/leere Entscheidungsmetadaten ein. Admin-
  und Teamsicht berechtigen nicht zur Rücknahme fremder Anträge.
- Die zusätzliche gebundene Quellenabfrage liegt im bereits vorhandenen Queue-
  Lesesnapshot. Sie steuert nur die Sichtbarkeit, niemals Schreibautorität.
- Nur Status, Abschlusszeit und Rücknahmegrund des Antrags werden geändert.
  Reviewer-ID, Antrag, Originalsnapshot, Vorschlag und Begründung bleiben exakt
  erhalten. Bestehende Trigger verhindern Wiederöffnung und Löschung.
- Der vorhandene Audit-UPDATE-Trigger erfasst vollständiges Vorher/Nachher in
  derselben Transaktion, mit festem `web:ID` und begründetem Rücknahmetext.
  Keine neue Auditregel und kein Parser.

Aktuelle Request-/Review-/Metadaten-Drafts, Filter und Seitenauswahl, Metadaten-
Karten und Home-Stand-Vorschau bleiben erhalten. Der bereits vorhandene Hinweis
bei unbestätigtem Abschluss eines Metadatenbefehls gilt nun auch für Rücknahme:
neu laden und gespeicherten Stand prüfen, **nicht** automatisch wiederholen.
Dies ist kein neuer allgemeiner COMMIT-/ROLLBACK-Fehlermechanismus.

## Grober Komponentenbeleg und Grenzen

PHP 8.5.12 / SQLite 3.46.1: ein zusammenhängender synthetischer `sqlite::memory:`-
Journey mit tatsächlicher Bestandsfixture, Migratoren und Triggern. Zeit- und
Ort-/Notizanträge entstehen durch den aktuellen Requestwriter und werden durch
den tatsächlichen Adapter/Kern zurückgenommen. Geprüft: Own/Pending/Local,
aktuelle Rolle und Fingerprint, Input-/Fremd-/Admin-/Wiederholungsverweigerung,
Selbstfreigabeverbot, beide seriellen Abschlussreihenfolgen für beide Antragstypen,
vollständiges Audit und explizites Rollback. Buchungen einschließlich Metadaten,
Konten, Sitzungen, Pausen, Ereignisse, Modelle und Schema bleiben unverändert.

Die tatsächliche unveränderte CSRF-Entscheidung aus `security.php` wird isoliert
mit einer reinen Arraysitzung ausgeführt: fehlender, falscher und nichttextueller
POST-Token verweigert; fester korrekter Token akzeptiert. Das tatsächliche neue
Formularfragment wird für beide Antragstypen mit echtem `csrf_field()` gerendert;
Fremdformular bleibt verborgen. Dies ist **kein** Login-/HTTP-/Cookie-/Session-
Middleware-/Browser-/Layoutnachweis. Keine Secrets, nutzbaren Passwort-Hashes,
Schlüssel, echten Konten, Dateidatenbank oder Server. Die seriellen Abläufe sind
kein Mehrprozess-Konkurrenztest; explizites Rollback ist kein COMMITverlusttest.
Keine neue Testsuite oder manuell gestartete/erneuerte GitHub-CI.

#50 bleibt partiell: vollständige Vertragszeitkonten, Vertretung/Eskalation und
reale Betriebs-/Browser-/Fehlerabnahme werden damit nicht geliefert.
