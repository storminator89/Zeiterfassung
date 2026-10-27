# Laravel-Migration: verbindliche Bestandsverträge

Stand: 27.10.2026. Dieses Paket bearbeitet [FW-01/#71](https://github.com/storminator89/Zeiterfassung/issues/71) und die **nichtschreibende Schemaaufnahme** von [FW-03/#74](https://github.com/storminator89/Zeiterfassung/issues/74), unter [Epic #59](https://github.com/storminator89/Zeiterfassung/issues/59).

- [ADR 0001](ADR-0001-incremental-laravel.md): Entscheidungen, Zuständigkeiten, Gates und grobe Abnahme.
- [Kompatibilitätsmatrix](compatibility.md): vorhandene Einstiege und geplante Laravel-Zuordnung; **keine bereits implementierten Laravel-Routen**.
- [Schemaspezifikation](schema.md): Bestand, Übernahme-/Rollbackverfahren und verbleibende FW-03-Arbeit.
- [Deklaratives Schema-Mapping](schema-map.json): sämtliche 21 im Quellcode angelegten Anwendungstabellen, Spaltendeklarationen und spätere Ergänzungen, 3 explizite Indizes, 34 Trigger sowie Schlüsselpfade. Kein DB-Dump, keine ausführbare Migration.

## Geprüfte Quellen, nicht verwechseln

| Quelle | Tatsächliche Aussage |
|---|---|
| Assessment-Basis [`33c53edf`](https://github.com/storminator89/Zeiterfassung/tree/33c53edf53024ef91c6887fc642b92d608940c7b) | Ausgangspunkt der Inventare `zeiterfassung-analysis/core-inventory.md` und `admin-integrations-inventory.md`; Beobachtungen daraus sind keine neue Laufzeitabnahme. Die Inventare liegen außerhalb des Repositorys und werden nicht vorausgesetzt, um diese Dokumentation zu lesen. |
| Arbeits-/Vertragsbasis [`6c5f635506949168b6afb0a568c71fe46ae915f7`](https://github.com/storminator89/Zeiterfassung/tree/6c5f635506949168b6afb0a568c71fe46ae915f7) | Genau dieser Git-Baum wurde statisch gelesen; Router, Handler, Bibliotheken, Erweiterungsclients und Schemaquellen. |
| [Draft-PR #7](https://github.com/storminator89/Zeiterfassung/pull/7), beim Abruf offen, `draft=true`, `merged=false`, Head `6c5f635…` | Bestehende UI-Wiederherstellung ist in der Vertragsbasis enthalten, **nicht als gemergter oder bereitgestellter Produktivstand behauptet**. Frühere Prüfberichte des PR sind nicht die Prüfung dieses Dokumentationspakets. |
| Parallel: [PHP 8.5/#58](https://github.com/storminator89/Zeiterfassung/issues/58), [Foundation/#73](https://github.com/storminator89/Zeiterfassung/issues/73) | Andere Eigentümer; deren spätere Commits sind nicht stillschweigend Teil dieser Quellaufnahme. Foundation bleibt ausschließlich unter `framework/`. |

## Tatsächlich geprüft

Reine Quellaufnahme und grobe strukturelle Konsistenz: JSON parsebar, Tabellennamen/Spalten/Triggeranzahl mit den Migrationserzeugern abgeglichen, Einstiegsliste mit `router.php` und `api.php` verglichen, `git diff --check`. Keine App-Datei ausgeführt oder includiert, keine Datenbank geöffnet/erstellt/kopiert, keine Schlüssel gelesen oder erzeugt. Kein HTTP-/Browserlauf, keine Mikrotests und kein neuer CI-Lauf. Die lokale laufende App, Port 18083 und ihre Fixtures bleiben unangetastet.

**Abnahmegrenze:** FW-01 liefert einen überprüfbaren Vertrag, keine Funktionsfreigabe. FW-03 ist mit Schemaaufnahme allein **nicht fertig**: ausführbare Übernahme, Connector, sichere Referenzfixtures und grobe Datenverlust-/Integritätsnachweise stehen aus. Veröffentlichung, Merge, Hosting-/Routing-/Datenumschaltung sind nicht Bestandteil dieses Pakets.
