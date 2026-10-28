# Bestehende Sidebar — gezielte Ordnung und Verfeinerung

Basis: Main `01beb3166408e3fe2a4fdd38d3dfdbb76f8333a6`, Tree
`4b6b6bdaf0cbb0fda984f9d71ee17178fbbd8a34`. Der Nutzer hat ausdrücklich eine
logischer angeordnete und schickere Sidebar angefragt. Dies ist nur dieser
UI-Bereich, kein neues UI oder Anwendungsredesign.

## Gleiche Einträge, nachvollziehbare Gruppen

1. **Arbeitszeit:** Home → Dashboard → Korrekturanträge → Arbeitszeitmodelle →
   Änderungshistorie.
2. **Team:** vorhandene Vorgesetztenübersicht, ausschließlich Supervisor/Admin.
3. **Verwaltung:** Admin → Praxis-Terminals, ausschließlich Admin.
4. **Konto & Hilfe:** Einstellungen → Über → Logout; Abmelden steht zuletzt.

Die gesamten nicht berechtigten Gruppen fehlen, einschließlich ihrer Überschrift.
Damit haben normale Benutzer zwei, Supervisor drei und Admins vier Gruppen.
Links, bisherige Eintragsbeschriftungen, Icons, Rollenbedingungen und Ziele sind
unverändert. Auch die Home-Aliase `index.php`/`attendance.php`, sonstige Active-
Bedingungen, nativer Logout-POST/CSRF und Laravels bisheriger disabled-Schalter
bleiben erhalten. `aria-current=page` verwendet genau dieselbe Bedingung wie die
Active-Klasse; keine pauschale aktive Gruppe oder neue Seitenberechtigung.

Nur neue Abschnitts-/Navigations-/Schließen-Texte kommen in die sechs vorhandenen
DE-/EN-/ZH-Wörterbücher. Die vorhandenen hartcodierten deutschen Einträge für
Korrekturen, Historie, Modelle und Terminals werden nicht beiläufig übersetzt;
dies ist keine Behauptung einer nun vollständig lokalisierten Navigation.

## Ruhiger, weiterhin eindeutig Chrono

Das vorhandene Kolibri-Bild und Produktname stehen kompakt am Panelanfang. Das
Bild ist neben dem gesprochenen Produktnamen dekorativ markiert. Source Sans,
bestehende Farben/Icons, 250px Breite, Off-canvas-Position und übrige Anwendung
bleiben gleich. Größere Trennung erfolgt zwischen Gruppen, nicht zwischen jedem
verwandten Arbeitseintrag. Konto/Hilfe folgt im normalen flexiblen Dokumentfluss
unten; kein absoluter/sticky Footer überdeckt Einträge auf kurzen Bildschirmen.

Die ausschließlich unter `.sidebar.chrono-sidebar` wirkenden Styles verwenden
die vorhandenen DaisyUI-Tokens `hsl(var(--b1))`, `--bc`, `--p`, `--pc`. Sie haben
gezielt mehr Spezifität als die späten unveränderten Inline-`.sidebar`-Regeln in
beiden Layouts. Der bisherige sichtbare Desktop-Befund war eine Active-Klasse
mit transparentem Hintergrund: `--primary` war leer, `--p` dagegen vorhanden.
Die aktive Seite erhält nun eine klare Primärfläche, stärkere Schrift und
Markierung; sichtbarer Tastaturfokus bleibt auch ohne Hover vorhanden. Kein
Austausch des Themes, neue Assetquelle oder globale Farb-/Typografieänderung.

Zeilen haben mindestens 44px Höhe, identische box-sizing-/Breiten-/Marginregeln
für Links und Buttons, feste Iconbreite und umbrechende statt abgeschnittene
Beschriftungen. Das ganze Panel bleibt scrollbar, mit `100vh`-Fallback vor
`100dvh` und Safe-area-Abstand unten. Reduzierte Bewegung wird im Sidebarbereich
respektiert. Keine neue ARIA-Menürolle, Akkordeonlogik oder Fokusfalle.

## Mobile Schließen ohne versteckten Hamburger

Bis einschließlich der vorhandenen 768px-Grenze erscheint im geöffneten Panel
ein beschrifteter „Menü schließen“-Button. Er ruft nur die bereits vorhandene
`setSidebarCollapsed(true)` auf und gibt den Fokus nur an einen sichtbaren
Hamburger zurück. Der neue Button ist oberhalb dieser Grenze per CSS verborgen.
Die bestehenden Zustände `aria-expanded`, `aria-hidden`, `inert`, Escape,
Außerhalb-Tippen, Resize und `sidebarCollapsed`-Präferenz bleiben unverändert.
Das vorhandene Über-Modal und dessen Funktion werden nicht neu implementiert.

## Enger Dateiumfang und Grenzen

Nur `header.php`, gemeinsames `assets/css/main.css`, die vorhandene Laravel-
Layoutvorlage, je drei native/Framework-Sprachdateien und dieses Dokument.
Keine neue Route, Guard, API, Sitzung, JS-Bibliothek, Buildpipeline, externe
Ressource, Datenabfrage oder Fach-/Buchungsfunktion. Die vorhandene feste lokale
CSS-Auslieferung im Laravel-Fixturrouter reicht aus; keine Asset-/Host-/CSP-
Umstellung oder neue Runtimefreigabe durch diesen Quellstand.

Ein grober erfundener Sidebar-Markupnachweis vergleicht native und Blade-
Projektionen für die drei Rollen, Active/Home-Alias, gleiche Ziele/Beschriftungen,
leere Gruppen und Logout-Gating. Er betrachtet die feste mobile Close-/Fokus-
Verdrahtung und Styles im Quelltext; dazu PHP-/kompilierte Blade-/Inline-JS-
Syntax sowie vollständige Quellerhaltung. Der grobe Nachweis und Syntax
bestanden am 28.10.2026 mit PHP 8.5.12 und Node 24.22.1. Kein Bootstrap, Guard, echter CSRF-
Token, Konten-/Datenbank-/HTTP-/Server-/Browserablauf in diesem Nachweis.

Die echte native Vorher-Aufnahme stammt vom weiterhin separaten sauberen e33-
Laufzeitstand; Header und main.css sind dort identisch zur Basis dieses Pakets.
Sie beweist nicht die neue Darstellung. Nach unabhängiger Quellenprüfung bleibt
aktuelle visuelle Desktop-/Mobil-/Theme-Abnahme auf einer ausdrücklich kontrolliert
aktualisierten lokalen Vorschau getrennt nötig. Nicht unbemerkt die neue Main-
Auditmigration gegen die alte Vorschau aktivieren: eine spätere e33-plus-nativer-
Sidebar-Deltavorschau kann Config/DB/Session/Migrationscode unverändert lassen.
Keine behauptete geschützte Laravel-, Admin-, Produktiv- oder Geräteabnahme.
