# Eigene ungültige Einträge direkt auffinden — Teilumfang #16

Lokaler Slice auf Remote-Main `3103ca85d8b625f56c45fdbeb1a1b9b9e8955637`,
Baum `ea620446e1433cd9aef71d26abc63b8de4025986`. Keine Abhängigkeit vom
offenen PR #239, kein Release/Deployment.

Der vorhandene native Header warnt bereits vor ungültigen eigenen Zeiten,
verlinkt aber nur den allgemeinen Korrekturweg. Gerade unlesbare Startzeiten
sortieren in der vorhandenen Liste hinten. Im bestehenden Hinweis ist nun eine
standardmäßig geschlossene Liste mit höchstens zehn direkten Korrekturlinks.
Jeder öffnet angekündigt einen neuen Tab mit `noopener noreferrer`, sodass
ungespeicherte Eingaben der aktuellen Seite nicht verlassen werden.

## Eigentum und Datenminimierung

`functions.php` liest ausschließlich Zeilen der aktuellen Session-Person;
`report_days` erzeugt daraus die vorhandenen Diagnose-IDs. Diese beiden
unveränderten Quellen werden nicht neu abgefragt oder erneut geparst. Der neue
definitions-only Helfer prüft die maximal zehn positiven Diagnose-IDs zusätzlich
gegen `id` **und** `user_id` der bereits geladenen Zeilen. Fremde, fehlende,
ungültige oder nicht bestätigte IDs ergeben keinen Direktlink. Kein
`record_id=0`-/„fehlende Buchung“-Fallback. Ohne bestätigte ID erscheint nur
ein knapper Klärungshinweis.

Die neue Ausgabe enthält ausschließlich bestätigte eigene IDs und feste Texte:
keine rohen Fehlernachrichten, Zeitwerte, Notizen, Abwesenheitsarten, Namen oder
sonstigen Personen-/Credentialdaten. Der vorhandene Gesamtwarnhinweis und seine
Zählung bleiben erhalten. Eine Begrenzung auf zehn Links ist ausdrücklich
kenntlich; sie ist kein vollständiger Diagnose-/Großbestandsbericht.

## Keine neue Reparatur oder Schreibberechtigung

Links verwenden den vorhandenen eigenen Auswahlweg aus #14, der auch ältere
eigene Datensätze außerhalb der 200 Optionen eigentümergescoped nachlädt.
Dieses Paket ändert ihn nicht und bestätigt keine tatsächliche Browserabnahme.
Beim späteren Aufruf gelten weiterhin aktuelle Authentifizierung und Ownscope;
gelöschte oder nicht mehr verfügbare Zeilen müssen dort neu geklärt werden.
Ein Link ist weder Antrag noch Freigabe. Die bestehende serverseitige
Validierung, ausdrückliche begründete Einreichung und Vier-Augen-Regel bleiben
maßgeblich; keine automatische Datenänderung oder vorgeschlagene Ersatzzeit.

Kein neuer SQL-, Runtimeparser-, Konflikt-, Berechnungs-, Auth-, CSRF-, Schema-,
Writer-, JavaScript- oder CDNpfad. Die bereits vorhandene vollständige
Historienladung bleibt unverändert; nur die Ausgabe ist begrenzt, nicht die
gesamte Scanarbeit. Eine zusätzliche In-Memory-Eigentumsprüfung ist keine
Performanceoptimierung und hier nicht für Großbestände vermessen.

## Nachweise und Grenzen

Nur ein grober synthetischer Quellen-/tatsächlicher Helfer-/Headerfragment-
Journey mit gemischten Ownerzeilen, fehlenden/ungültigen IDs, Begrenzung und
Datenminimierung; PHP8.5.12-Syntax und exakte Quellerhaltung. Keine echte DB,
Konto-/Login-/Session-/CSRF-/HTTP-/Browser-/Layout-/Mehrtab-/Freigabe-/
Großbestandabnahme. Kein Appbootstrap, Schlüsselzugriff, Server, Remoteaktion,
neue CI, Host-/Runtimeaktivierung oder Deployment. #16 bleibt partiell; weder
Bestandszeiten noch vollständige Datenqualität werden dadurch repariert.
