# Laravel-Migration: verbindliche Bestandsverträge

Stand: 27.10.2026. Dieses Paket bearbeitet [FW-01/#71](https://github.com/storminator89/Zeiterfassung/issues/71) und die **nichtschreibende Schemaaufnahme** von [FW-03/#74](https://github.com/storminator89/Zeiterfassung/issues/74), unter [Epic #59](https://github.com/storminator89/Zeiterfassung/issues/59).

- [ADR 0001](ADR-0001-incremental-laravel.md): Entscheidungen, Zuständigkeiten, Gates und grobe Abnahme.
- [Kompatibilitätsmatrix](compatibility.md): vorhandene Einstiege und geplante Laravel-Zuordnung; **keine bereits implementierten Laravel-Routen**.
- [Schemaspezifikation](schema.md): Bestand, Übernahme-/Rollbackverfahren und verbleibende FW-03-Arbeit.
- [Deklaratives Schema-Mapping](schema-map.json): sämtliche 21 im Quellcode angelegten Anwendungstabellen, Spaltendeklarationen und spätere Ergänzungen, 3 explizite Indizes, 34 Trigger sowie Schlüsselpfade. Kein DB-Dump, keine ausführbare Migration.
- [Erster ausführbarer FW-03-Schritt](adoption-rehearsal.md): strikt speicherinterne Quellfixture, echte Laravel-PDO/UDF-Lebenszyklen und kontrollierte V2-Vorprüfung/Trackingregistrierung. Keine private Datei-/Produktivübernahme.
- [Expliziter bekannter V2-Offlinepfad](offline-v2-adoption.md): read-only Datei-Vorprüfung, verifiziertes separates Backup, additive Laraveladoption und ausschließlich eigene Ledger-Rücknahme. Ausführung hier nur auf synthetischen gestoppten Kopien; keine Runtimeumschaltung.
- [FW-04 SQLite-/Audit-/Idempotenz-PoC](sqlite-write-proof.md): opt-in Ein-PDO-Domänenadapter mit Standard-Laravel-IMMEDIATE-Transaktion und grobem persistiertem synthetischem Konkurrenz-/Rollbackbeweis; keine Route oder produktive Aktivierung.
- [Inaktiver FW-05-Authkern](local-auth-foundation.md): Laravel-Sessionguard/Provider, aktuelle Rechte, CSRF-/Drosseladapter und eine grobe speicherinterne Abnahme. Keine Route, produktive Verbindung oder UI-/Loginumschaltung; #76 bleibt offen.
- [Inaktive FW-07-Lesedienste](domain-read-adapters.md): ein Containervertrag für eigene Anwesenheit, Berichtsprojektion, Korrektur-/Auditlisten und historische Modelle; bestehende Fachfunktionen/Rechte unverändert. Keine Schreibmigration oder Route; #78 bleibt offen.
- [Inaktiver lokaler FW-07-Web-Stempeldienst](local-web-attendance-service.md): typisierte lokale Webidentität und bestehender `web:{id}`-Namensraum auf dem einzigen #75-Writer; keine Route, PDOfactory oder Änderung der Fach-/Replayregeln.
- [Gemeinsamer FW-07-Korrekturkern](correction-core.md): mechanisch aus der bestehenden Korrekturseite extrahierter Antrag-/Entscheidungs-/Stornopfad im unveränderten nativen Writer-Lock; getrennte Laraveladaption unten, weiterhin keine neue Route.
- [Inaktiver lokaler FW-07-Korrekturdienst](local-web-corrections-service.md): vertrauenswürdiger lokaler Guard und scoped Auditrunner auf demselben Korrekturkern und einzigen #75-Writer; keine Route oder produktive Einbindung.
- [Gemeinsamer FW-07-Arbeitsmodellkern und inaktiver Webdienst](local-web-work-models-service.md): unveränderte native Modellvalidierung/append-only Versionen, vertrauenswürdige Adminidentität und scoped Audit auf demselben #75-Writer; keine Route, Neubewertung gespeicherter Gutschriften oder Runtimeumschaltung.
- [Gemeinsamer FW-07-Abwesenheitskern und inaktiver Webdienst](local-web-absences-service.md): unveränderte native Gutschrift-/Tagesüberlappungsregeln, eigene lokale Guardidentität und scoped Audit auf demselben #75-Writer; keine Route, neue Urlaubspolitik oder Runtimeumschaltung.
- [Inaktiver FW-06-Bearer-/API-Lesekern](api-read-compatibility.md): unveränderte Tokenverifikation und eigene Read-/Wireadapter; keine Anmeldung, Schreibmigration oder öffentliche Route, #77 bleibt offen.
- [Gepflegtes JWT-Paket im inaktiven API-Guard](maintained-api-tokens.md): offizielles Composerpaket, festes HS256-/Issuerprofil und aktueller Passwortwiderruf; Altissuer/-verifier, Schlüssel und produktive Routen bleiben unverändert.
- [Erste inaktive FW-08-Bestandsansichten](existing-ui-views.md): vorhandenes Login-/Header-/Anwesenheitsmarkup als Blade mit expliziten Anzeigedaten; keine neue Oberfläche, Route oder Schreibaktivierung. Synthetischer Ganzseitenvergleich bestanden, aktive Abläufe weiterhin offen; #79 bleibt offen.
- [Explizite lokale FW-08-Webintegration](local-web-ui-integration.md): wiederverwendbare Laravel-Controller und opt-in Route-Registrar für Login/Anwesenheit/Logout; ein echter isolierter synthetischer Browserjourney bestanden, keine Default-/Produktivaktivierung oder vollständige #79-Freigabe.
- [Bestehende FW-08-Korrekturseite](corrections-ui.md): vorhandene Formulare/Antragskarten als Blade und dünner Controller auf denselben Read-/Korrekturdiensten; ein isolierter Zwei-Personen-Browserjourney bestanden, ausschließlich opt-in und kein vollständiger #79-Cutover.
- [Inaktiver API-Login-/Stempelkandidat](candidate-api-actions.md): stock Credentialprüfung, gepflegte Tokenausgabe und direkter APIpfad auf dem einzigen Writer; persistentes gemeinsames Loginbudget und Gesamtcutover bleiben gesperrt.
- [Inaktive bestehende APIaliase](candidate-api-aliases.md): Start-/Endalias auf demselben APIwriter, aktuelle eigene record_id und unveränderte Fehler/Koerzierungen; keine optimistischen Clientzeiten oder neue Antwortform.

## Geprüfte Quellen, nicht verwechseln

| Quelle | Tatsächliche Aussage |
|---|---|
| Assessment-Basis [`33c53edf`](https://github.com/storminator89/Zeiterfassung/tree/33c53edf53024ef91c6887fc642b92d608940c7b) | Ausgangspunkt der Inventare `zeiterfassung-analysis/core-inventory.md` und `admin-integrations-inventory.md`; Beobachtungen daraus sind keine neue Laufzeitabnahme. Die Inventare liegen außerhalb des Repositorys und werden nicht vorausgesetzt, um diese Dokumentation zu lesen. |
| Arbeits-/Vertragsbasis [`6c5f635506949168b6afb0a568c71fe46ae915f7`](https://github.com/storminator89/Zeiterfassung/tree/6c5f635506949168b6afb0a568c71fe46ae915f7) | Genau dieser Git-Baum wurde statisch gelesen; Router, Handler, Bibliotheken, Erweiterungsclients und Schemaquellen. |
| [Draft-PR #7](https://github.com/storminator89/Zeiterfassung/pull/7), beim Abruf offen, `draft=true`, `merged=false`, Head `6c5f635…` | Bestehende UI-Wiederherstellung ist in der Vertragsbasis enthalten, **nicht als gemergter oder bereitgestellter Produktivstand behauptet**. Frühere Prüfberichte des PR sind nicht die Prüfung dieses Dokumentationspakets. |
| Parallel: [PHP 8.5/#58](https://github.com/storminator89/Zeiterfassung/issues/58), [Foundation/#73](https://github.com/storminator89/Zeiterfassung/issues/73) | Andere Eigentümer; deren spätere Commits sind nicht stillschweigend Teil dieser Quellaufnahme. Foundation bleibt ausschließlich unter `framework/`. |

## Tatsächlich geprüft – ursprüngliches Dokumentationspaket `9aa478a`

Reine Quellaufnahme und grobe strukturelle Konsistenz: JSON parsebar, Tabellennamen/Spalten/Triggeranzahl mit den Migrationserzeugern abgeglichen, Einstiegsliste mit `router.php` und `api.php` verglichen, `git diff --check`. Keine App-Datei ausgeführt oder includiert, keine Datenbank geöffnet/erstellt/kopiert, keine Schlüssel gelesen oder erzeugt. Kein HTTP-/Browserlauf, keine Mikrotests und kein neuer CI-Lauf. Die lokale laufende App, Port 18083 und ihre Fixtures bleiben unangetastet.

**Abnahmegrenze:** FW-01 liefert einen überprüfbaren Vertrag, keine Funktionsfreigabe. FW-03 ist mit Schemaaufnahme allein **nicht fertig**. Geliefert sind nun [Memory-Rehearsalkern](adoption-rehearsal.md) und [bekannter V2-Offline-Dateipfad](offline-v2-adoption.md), letzterer nur auf synthetischen Kopien abgenommen. Keine reale Datenübernahme, Neuinstallation/vor-v2-Upgrades oder vollständige #74/#75/#82-Abnahme. Hosting-/Routing-/Produktivdatenumschaltung bleiben außerhalb dieser Pakete.
