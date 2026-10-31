# Design: Quodara Chrono

Ein Arbeitswerkzeug für Zeiterfassung. Bestehende Funktionen und Daten bleiben vollständig erhalten.

## System
- Genre: modern-minimal; app screen, keine Marketingseite. Komponentenstruktur und Abstände folgen dem fertigen Tabler-Template.
- Thema: offizielles Tabler 1.7.1 (MIT), Kolibri und vorhandener violetter Akzent.
- Navigation: horizontale Kopfzeile mit Heute, Übersicht, Urlaub. Weitere Aufgaben unter Mehr, Konto separat.
- Erstansicht: eine gemeinsame Buchungsfläche mit integriertem Zeitkonto; Tagesbuchungen direkt sichtbar. Arbeitsort und Notiz stehen beim Kommen ohne Aufklappen bereit. Abwesenheit und Berichte öffnen als native, fokussierte Arbeitsdialoge mit normalen URL-Fallbacks. Filter und Export bilden eine kompakte Werkzeugleiste.
- Jede Seite führt mit der Aufgabe. Erweiterte Formulare öffnen sich bei ausgewähltem Vorgang, Fehler oder Entwurf.
- Ein gemeinsamer linker Rand, eine Inhaltsbreite. Keine mehrfachen Logos, statistischen Icon-Kacheln oder verschachtelten Karten.

## Tokens
`framework/public/assets/css/tokens.css` ist die Farb-, Schrift- und Abstandsbasis.
- Papier: oklch(99% 0.002 285), neutrale Nebenfläche.
- Text: oklch(26% 0.015 285), gedämpfte Metadaten mit ausreichendem Kontrast.
- Akzent: hsl(258 90% 56%), für Hauptaktion, Fokus und aktuelle Auswahl.
- Schrift: system-ui, Zahlen mit tabellarischen Ziffern.
- Dunkles Design: eigene semantische Tokens, keine Gradienten.

## Bedienung
- Felder und Buttons 40 px, auf Touch-Geräten mindestens 44 px.
- Ein gefüllter Hauptbutton pro Aufgabe; weitere Aktionen ruhig.
- Tabellen mit lesbaren Uhrzeiten und kurzen Überschriften.
- Tastaturfokus sichtbar; keine dekorative Animation.
- Unter 960 px wird die Navigation zum Menü. Formulare stapeln sich auf Mobilgeräten.
- Status, Fehler, Datenkonflikte und aktive Filter bleiben auffindbar und verständlich.

## Exports
Gemeinsame Styles: `framework/public/assets/css/app_ui.css`, `tokens.css`, `base.css`.
Vorlagen: `framework/resources/views/chrono`.


## Zeitkonto und Identität
Das neue App-Zeichen verbindet ein Q mit einem Uhrenring auf einer violetten Kachel. Es ersetzt den Vogel in Navigation, Anmeldung, Favicons und App-Icons. Vorlagen unter `assets/brand`, öffentliche Laufzeitkopien unter `framework/public/assets/brand`; Herkunft und Größenexporte sind dokumentiert. Zustandsgrafiken bleiben klein und zeigen jeweils nur Arbeiten, Pause oder Feierabend. Der Live-Zähler steht optisch vor den sekundären Kontowerten; das Zeitkonto bündelt Saldo, Tagesziel und den Zugang zur Auswertung.

## Urlaub und Freigabe
Die Urlaubsseite verwendet dieselben ruhigen Flächen wie Heute: ein kompaktes
Antragsformular und eine Liste mit Zeitraum und Status. Freigaben führen von der
Stundenvorschau zu einem einzelnen Entscheidungsdialog. Der Verlauf bleibt auf
Wunsch zugänglich; technische Kennungen stehen nur in kontextueller Hilfe.
Terminalverwaltung teilt Geräte, Zugänge und Standorte in drei Aufgabenansichten.
Feiertage werden serverseitig geladen und nach einem lesbaren Abgleich übernommen.
