# Design: Quodara Chrono

Ein Arbeitswerkzeug für Zeiterfassung. Bestehende Funktionen und Daten bleiben vollständig erhalten.

## System
- Genre: modern-minimal; app screen, keine Marketingseite. Komponentenstruktur und Abstände folgen dem fertigen Tabler-Template.
- Thema: offizielles Tabler 1.7.1 (MIT), Kolibri und vorhandener violetter Akzent.
- Navigation: horizontale Kopfzeile mit Heute, Übersicht, Urlaub. Weitere Aufgaben unter Mehr, Konto separat.
- Erstansicht: Zeitbuchung und eigene Buchungen. Arbeitsort und Notiz optional, Prüfungen und Exporte nach Bedarf.
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
