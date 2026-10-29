# Native Kalenderabhängigkeit — enger Teilschritt #67

Basis: Remote-Main `5f826c246baf28cb899961178ae03082c600fa1e`, Baum
`532aa2a34023d9429fcb5677f60c58c2451f8730`. Nur die zwei vorhandenen
TOAST-URLpfade in `dashboard.php` wechseln von `latest` zu `v2.1.3`;
keine neue Bibliothek, kein Versionsupgrade und keine neue Gestaltungswelt.

## Warum diese genaue Version?

Die vorhandene native Kalenderprojektion benutzt bereits die TOAST-2.1.3-
Felder und Semantik (PRs #123/#130). Die [offizielle Veröffentlichung
`calendar@2.1.3`](https://github.com/nhn/tui.calendar/releases/tag/calendar%402.1.3)
und die tatsächlich abgerufenen Originaldateien identifizieren Version 2.1.3.

Erneut am 29.10.2026, 00:26 UTC, antworteten alle vier Original-URLs mit HTTP
200, ohne Weiterleitung. Vollständige Antwortbytes, nicht nur Banner, wurden
jeweils verglichen:

| Datei | Bytes | SHA-256 von `latest` und `v2.1.3` |
| --- | ---: | --- |
| `toastui-calendar.js` | 609122 | `b2f76d39d1f3a312cb72ce8aa7576279fb8394478e32c81a87560a1344e4885c` |
| `toastui-calendar.min.css` | 26740 | `9b0a80233edd3152ed06281416f8876fdfeb3f8c9ab511589f4436caa413d4fd` |

Beide Paare sind bytegleich und stimmen außerdem exakt mit dem
bereits am 28.10.2026 um 23:06–23:07 UTC unabhängig geprüften Paket überein. Die endgültigen Versions-URLs und Hashes stehen
in [`native-calendar-assets.json`](native-calendar-assets.json). Das Manifest
ist ein Herkunfts-/Reproduktionsnachweis der Beobachtung, kein automatisch
ausgeführter Lockfile- oder Integritätsvalidator.

## Grenzen

- JavaScript bleibt nicht minifiziert, CSS bleibt minifiziert; Ladefolge,
  Host, Protokoll und alle übrigen HTML-/PHP-/Kalenderbytes bleiben erhalten.
- Keine Daten-/Auth-/Writer-/Berechnungs-/Schema-/CSP-/SRI-/Hoständerung, kein
  lokales Bündeln und keine neue externe Quelle oder Abhängigkeit.
- Gepinnte Pfade reduzieren unbeabsichtigte Versionssprünge, garantieren aber
  weder unveränderliche CDNbytes noch Verfügbarkeit, Sicherheitswartung oder
  eine funktionierende Kalenderdarstellung. Andere offene CDNabhängigkeiten
  (u. a. Tailwind und gemeinsame Bibliotheken) bleiben ausdrücklich ungelöst.
  Die offizielle Upstreamseite weist Stand 29.10.2026 eine Archivierung aus;
  dieser Pin ist keine Zusage laufender Wartung.
- Nur tatsächliche Asset-GETs/Bytevergleich, JavaScript-/PHP-Syntax und reine
  Quellerhaltung geprüft. Keine Bibliotheksausführung, echte Browser-/HTTP-
  App-/Layout-/CDN-CSP-Abnahme, keine neue CI, kein Remote-Schreiben und kein
  Deployment. #67 bleibt offen.
