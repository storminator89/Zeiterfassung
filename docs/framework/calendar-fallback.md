# #18 / #79 — Kalenderfallback in der vorhandenen Laravel-Ansicht

Begrenzter inaktiver UI-Schritt auf Main
`f2d1cf91229613bb8b094b9370b0bcdc9f47485a`, Baum
`380da72a3fef248c56c1f72991cc027255f193b9`. Native besitzt diese vier Texte
und den festen eigenen Ausweichlink bereits; die Laravel-Ansicht hatte bisher
nur den Kalendercontainer und einen ungeschützten Mountaufruf.

## Enge Änderung

Ein dezenter Status mit festem `attendance.php`-Link ist schon servergerendert
vorhanden. Die vorhandenen DE-/EN-/ZH-Texte werden wortgleich als additive
Laravel-Labels übernommen, ohne bisherige Sprachbytes zu ersetzen. NoScript
erklärt den bestehenden eigenen Ausweichpfad; sichtbare Texte bleiben Blade-
escaped. Es gibt keine neue Abhängigkeit, URL, Query oder Eingabefläche.

Der ursprüngliche `ChronoCalendar.mount`-Aufruf einschließlich aller Optionen
bleibt unverändert. Nur eine enge lokale Guardhülle erkennt den fehlenden
Adapter oder eine geworfene Initialisierung: Der Status bleibt sichtbar, ein
fester sprachabhängiger Text wird ausschließlich mit `textContent` gesetzt,
teilweise Kalender-DOM wird best-effort geleert. Weder Exceptiontext noch
Benutzerdaten werden als Fehlermeldung ausgegeben. Nach erfolgreichem Mount
wird ausschließlich der zusätzliche Hinweis versteckt; bestehender Kalender,
Modal, Listener und Daten bleiben unverändert. Kein automatischer Retry, keine
neue Navigation, Bearbeitung, Buchung oder Datenänderung.

Keine CSP-/Nonce-/Scriptfreigabe wird verändert: derselbe bestehende Inline-
Bootstrap und dieselben lokalen Script-/Style-Assets bleiben vorhanden. Wenn
JavaScript aus oder der Bootstrap durch eine bestehende CSP blockiert ist,
bleibt der bereits serverseitige Hinweis mit Ausweichlink bestehen. Dies ist
keine tatsächliche CSP-Fehlererkennung oder Browserabnahme. Die NoScript-Zeile
ersetzt keine CSP-Diagnose. DOMleeren beweist keine vollständige Ressourcen- oder
Listenerfreigabe eines bereits teilweise gestarteten Renderers; erneutes Laden
beziehungsweise eigener Buchungspfad bleiben die ausdrücklich genannten Wege.

Reader, Statistiken, Summen, native Projektion, Events/Offsets, Navigation,
Kalenderoptionen, JSadapter, öffentliche Bytes/Pins, Controller, DTO, Routen,
Provider, Auth/CSRF und FreshWeb-/Runtimefreigaben bleiben unberührt.

## Grober Nachweis und Grenzen

PHP 8.5.12 / Laravel 13.38.0 kompiliert und rendert den tatsächlichen alten/neuen
Blade-Bootstrap mit den tatsächlichen Copy-/Dashboard-/CalendarEvents-DTOs.
Node 24.22.1 führt ihn mit dem unveränderten tatsächlichen ChronoCalendar-
Adapter gegen synthetisches DOM und einen FullCalendar-Stub aus. In DE/EN/ZH
ist der erfolgreiche Konstruktor-/Optionen-/Listener-/Rendertrace exakt gleich.
Fehlender Adapter, fehlende Bibliothek, falsche gepinnte Version und geworfenes
Rendern ergeben denselben sicheren festen Ausweichhinweis und DOMleeren.
Ein erfundener Eventtitel mit HTML-/Scriptcanary bleibt sicher serialisiert.

Sprachunion, ursprünglicher vollständiger Mount-/View-Inhalt und alle übrigen
Quellbytes/Modi sowie vollständige Inverse werden separat geprüft. Notwendige
PHP-/kompilierte Blade-/Bootstrap-Syntax bleibt Teil des Nachweises.
Keine neue Repositorytestsuite, CI-Auslösung, Installation oder Bereitstellung.
Kein vollständiger App-/FullCalendar-/HTTP-/Browser-/Layout-/NoScript-/CSP-/
Auth-/Session-/CSRF-/Produktivnachweis, kein realer Datensatz oder Runtimezugang.
#18/#79 bleiben für echte UI-/Rollen-/Betriebsabnahme und Cutover offen.
