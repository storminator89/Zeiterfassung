# Native Hinweise zu Kontozugriff korrekt einordnen — Teilumfang #30/#29

Basis Main `9e4cceb6cec56112521411669fd84093efc5155c`, Baum
`3be7e75787be927c4aac4a5572a8f040e926c817`. Enger Copy-/Hilfeslice,
**keine zentrale Kontodeaktivierung und keine Änderung einer Zugangsregel**.

Die native Historien-Löschablehnung empfahl bisher pauschal Passwortänderung
und Terminal-Sperre zum Zugangsentzug. Das ist kein zentraler Sperrprozess:
eine lokale Passwortänderung verhindert keine erneute gültige LDAP-Anmeldung
und sperrt keinen Terminal-Zugang. Die bereits integrierte Laravel-Vorarbeit
enthält dafür die zutreffendere feste 409-Meldung. Genau diese Meldung wird nun
auch nativ verwendet. Prädikat, Exceptionklasse, Status 409 und Behandlung
bleiben gleich; nur der feste Antworttext ändert sich absichtlich.

Ein knapper statischer Hinweis unter der vorhandenen Benutzerverwaltungs-
Überschrift erklärt denselben Sachverhalt, ausdrücklich für diese native
Ansicht. Sein Kernsatz stammt aus der bestehenden Laravel-Hilfe. Kein Tooltip,
neuer Dialog, Button, Link, Formular, JavaScript, Fokus-/Hoverablauf oder
Netzwerkaufruf. Bestehender Seitenrahmen, Klassen, Formulare und Aktionen bleiben.

Der vorhandene Historienhinweis und die sichtbare Löschsperre aus #31 sowie
die Abteilungs-/Vorgesetztenhinweise aus #34 bleiben exakt erhalten. Rollen,
Adminprüfung, CSRF, SQL, Writer-Lock, Audit, Letztadmin, Zuordnungen, LDAP,
Passwörter, Sessions, Tokens, Geräte-/Terminalgrants, Schema, V5-/Runtimegates
und sämtliche Mutationen bleiben unverändert. Die Hilfe beendet keinen Zugang
und bietet keine Ersatzhandlung an, die eine vollständige Sperre verspricht.
Diese native Textänderung stellt keinen zentralen Aktiv-/Austrittsprozess
bereit. Separater V5-Quellfortschritt und sein jeweiliger profilbezogener
Aufnahme-/Betriebsstand bleiben unberührt und gesondert abnahmebedürftig.
Die bestehende Laravel-Hilfe unterscheidet inzwischen einen Lifecyclezweig
vom Legacy-/V4-Fallback; übernommen ist ausschließlich dessen feste
Legacyablehnung und Hilfekernsatz, keine Aussage über das ganze Produkt.

## Begrenzte Nachweise

PHP 8.5.12-Syntax und Diffcheck. Ein grober anonymisierter Memory-Journey führt
nur den tatsächlichen isolierten Audit-Prädikat-/Throwblock aus: vorhandene
Historie behält die Klasse und 409, der feste Text entspricht dem bestehenden
Laravel-Pendant, fehlende Historie wird nicht neu verweigert. Die statische
Hilfe enthält keine interaktiven Elemente; sämtliche Daten und das Schema der
Fixture bleiben unter query_only unverändert. Quellerhaltung entfernt nur den
Hinweis und setzt genau den früheren Literal zurück, um die komplette aktuelle
Admin-Datei bytegenau wiederherzustellen. Vorhandene grobe #31-/#34-Fragment-
nachweise prüfen die unveränderte Zusammensetzung zusätzlich.

Keine echte Datenbank, Personen, Credentials, Appbootstrap, aktuelle Admin-
Authentifizierung, Session/CSRF/HTTP, Browser/Layout, LDAP-/Terminalausführung,
Konkurrenz- oder Widerrufsabnahme. Kein neuer Dienst, Host-/Sicherheitssetting,
Runtime-/V5-Aktivierung, Deployment oder Remoteaktion in diesem lokalen Nachweis.
#30/#29 bleiben offen. PR #239 und dessen offener Merge-Denial bleiben unberührt.
