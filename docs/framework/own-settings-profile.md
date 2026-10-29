# #62/#79 — eigene Zuordnung und gespeicherte Wochenregel in Settings

Quellbasis Main `e7d198315ab15994fd69419d96c9cb8e94dd83de`, Tree
`51acf6b754cd003a90e96105604532446437343e`, 29.10.2026, nach PR #345.

Die bereits zugelassene Laravel-Settingsseite zeigt einen eigenen Lesebereich:
zugeordnete Abteilung, zugeordnete vorgesetzte Person und das für den aktuellen
Berliner Tag gespeicherte Wochenmodell mit Gültigkeitsdatum und sieben
Soll-Dauern. Dies hilft bei der eigenen Einrichtung, statt nur den älteren
Basisstundenwert und einen Navigationslink zu sehen. Keine neue Seite oder
Gestaltungssprache; vorhandene Überschriften-/Divider-/Tabellenmuster und
DE/EN/ZH-Texte werden verwendet. Der Link zu Modellen erscheint nur mit der
bereits vorhandenen Workmodel-Anzeigefähigkeit.

## Eigener autoritativer Read, keine Kontoaktion

Die neue optionale `OwnSettingsProfileReads`-Fähigkeit hat **keinen** Parameter
für Konto, Modell oder Datum. `LegacyChronoReads` verwendet ausschließlich den
vom unveränderten Read-Snapshot frisch geladenen Actor. Bestehende benannte
Connection-, Idle-, Source-/Passwortversions-/Generation-/Rollen- und
Transaktionsgrenzen bleiben erhalten. Ein gebundener Join liest genau dessen
gespeicherte Zuordnungen; derselbe PDO-Snapshot liefert das Modell.

Die bestehende reine `admin_reference_status`-Klassifikation wird für die bereits
autorisierte eigene Zeile wiederverwendet, keine neue Referenz-/FK-/Reparaturregel.
Nur aufgelöste Namen werden gezeigt. NULL bedeutet nicht zugeordnet;
fehlende/ungültige/nicht eindeutig aufgelöste Referenzen werden als Klärungsbedarf
benannt, nicht einem Nachbarn zugeordnet. Keine IDs, E-Mails, fremden Rollen,
Authquellen oder Credentialfelder gehen an die Ansicht. Zugeordnete Namen sind
bewusst eigene Einrichtungsinformation; sie sind **kein** Nachweis gegenwärtiger
Freigaberechte. Selbstfreigabe wird nicht ermöglicht oder versprochen.

Das Modell stammt unverändert aus dem in #345 veröffentlichten
`api_own_work_model_snapshot`: `valid_from DESC,id DESC`, rohe gespeicherte
Ganzzahlminuten mit strikt lexikalischer Validierung, kein Feiertagsabzug oder
Altwirklichkeits-/Saldoalgorithmus. Die View-DTO übernimmt nur Datum, aufgelöste
Referenznamen/-status, Modellgültigkeit und sieben Minutenzahlen. Selbst die
interne Modell-ID und unbekannte Felder bleiben draußen. Namen müssen nichtleer,
UTF-8 und höchstens 1024 Bytes sein; unbrauchbare Daten werden nicht abgeschnitten
oder als zuverlässiges Profil angezeigt.

Ein fehlendes gespeichertes Modell ist ausdrücklich **nicht Nullsoll** und kein
behaupteter Legacy-Fallback. Ein technischer/malformed Read ist ein allgemeiner
Nichtverfügbarkeitszustand, kein leeres/sauberes Profil. Verlust aktueller
Authentifizierung/Autorisierung wird weitergeworfen, nicht in eine alte
Profilansicht umgewandelt. Frühere Settings-Implementierungen ohne optionale
Fähigkeit behalten ihre bisherige Ansicht. Der Profilbereich ist ein eigener
Snapshot, **keine** Behauptung atomarer Gesamtseite mit getrenntem Chrome-/
Passwort-/Übertrags-/Präferenzablauf.

Kein neuer Endpoint, Provider, Cache, Sessionfeld, Asset, Writer, Accountmodell,
Schema, Datenmigration, FK-Schalter oder Runtime-/Host-/Securityänderung.
Passwort-/Carry-in-/Sprach-/Themeformulare und ihre bestehenden Ausgänge bleiben
unverändert; es wird nichts automatisch gespeichert oder wiederholt.

## Grober Komponentenbeleg und Grenzen

PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0: ein zusammenhängender Journey mit
den tatsächlichen Bestandsmigratoren/-triggern, Query-only-Memory-Read,
`LegacyChronoReads`, neuem Helper, Settingscontroller/DTO und tatsächlich
kompiliertem Settingsinhalt samt Partial. Drei Sprachen und eigene User-/Admin-/
LDAP-Supervisor-Ansicht, manipulierte fremde Queryselektoren ohne Wirkung, exakte
große Nachbarreferenzen, Modellgleichstand/Zukunft/initiale Integral-Floats,
Escaping/Dauern, frischer Read nach eigener Umzuordnung, NULL-/fehlende/ungültige
Referenzen, malformed Modell als nicht verfügbar sowie verweigerte Caller-
Transaktion und frischer Autoritätsverlust. Schema-/Zeilen-/Audit-/Changezähler
bleiben während Reads unverändert; keine offene Transaktion. Der reine
Missing-Model-DTOzweig wird zusätzlich ohne erfundenes Nullsoll gerendert.

Auth ist im Harness ein bewusst falscher RequestGuard als Identitydouble, das
gemeinsame Layout inert und die Session ausschließlich ein Arraystore mit festem
Darstellungstoken. **Kein** vollständiger App-/Runtime-/V5-/HTTP-/Login-/CSRF-/
Cookie-/Browser-/Layout-/Konkurrenz-/Performance-/Directory- oder Betriebsnachweis.
Passwortverfügbarkeit bleibt bei diesem Guard absichtlich aus; kein Passwort-/
Übertragswriter, echter Account, Credential-/Hash-/Schlüsselgenerator, Dateidatenbank
oder Server wird verwendet. Keine neue Testsuite/manuelle CI/Approvalreview.

Dies ist eine lesende eigene Kontohilfe, **kein vollständiges Onboarding**:
Region-/Regelentscheidungen, Einladung/Recovery, Geräte-/Sessionverwaltung und
echte UI-/Operationsabnahme bleiben offen. Keine Vollfreigabe von #62/#79 und
keine Arbeit an freigabepflichtiger #50-Rücknahme oder #57-Reconciliation.
