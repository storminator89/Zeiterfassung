# FW-08 / #79 — vorhandene eigene Sprache und Darstellung

Basis: CSV-Main `f9177d56be436fc8380eb6a9329f41df5d054d24`.
Ein zusammengehöriger 26-Pfad-Source-Schritt aus zwei disjunkten eingefrorenen
Teilen: A17 für bestehende Sprach-/Kalenderkompatibilität, B9 für die vorhandene
Settings-UI, gewöhnliche Sessionpräferenzen und den expliziten Systemmodus-Fix.
Keine neue Oberfläche, Datenbankspalte, Paketinstallation oder generische
Preferences-/Auth-/Writer-Infrastruktur. **Keine Startseitenpräferenz existiert
im Bestand; eine solche wird nicht erfunden.**

## Tatsächliche Bestandsgrenze

`settings.php:31–55` speichert lediglich `lang` und `theme_mode` in der Session.
`valid_language()` aus `lib/language.php` akzeptiert exakt `de`, `en`, `zh` und
fällt andernfalls auf `de` zurück. Der Themevalidator akzeptiert strikt
`light`, `dark`, `system`, sonst `light`. Fehlende/null Felder werden gemäß
nativem `isset` nicht geschrieben; leere/ungewöhnliche Werte werden nicht durch
Laravel-Trimming in eine gültige Auswahl verwandelt.

Das Formular enthält zusätzlich ein bereits deaktiviertes Regelstundenfeld,
ein eigenes Admin-Vortragsfeld mit einer **anderen Kontoschreibwirkung** sowie
Admin-Snapshot-/Offline-Restorekarten. Diese sind keine gewöhnlichen
Präferenzen und werden hier nicht als angebunden ausgegeben.

## Lesen, Speichern und ehrliche Verwaltungs-Holds

Der neue `LocalWebSettingsController` verwendet den vom unveränderten
`RefreshLocalAccount`/`auth:web`/`auth.session`-Stack bereits frisch geladenen
**eigenen** `LegacyUser` ausschließlich lesend. Nur `regelarbeitszeit` und bei
Admins `ueberstunden` gehen als einzelne escaped Anzeigewerte in `SettingsPage`;
kein vollständiges Modell/Passwort/Token gelangt in die Ansicht. Es gibt keinen
neuen Personenwähler, Readvertrag, Query oder Modelspeicheraufruf. Der vorhandene
eigene `attendanceStatus()` versorgt ausschließlich das Standard-Chrome.

- POST schreibt nur die zwei gewöhnlichen Werte in die bestehende Laravel-
  Session, mit den vorhandenen Validatoren. Kein Account-/DB-/Writer-/Audit-
  oder Schlüsselzugriff, keine Session-ID-/CSRF-Regeneration und kein Logout.
- Wie der native Header wird ein ausdrücklich vorhandenes Query-`lang` nach
  der Formbearbeitung angewendet. Sonst bleibt die gespeicherte Auswahl erhalten.
- Das native Same-page-POST samt Erfolgstext/-dialog bleibt bestehen, kein neuer
  Redirect-/Flashworkflow. Reload darf dieselben idempotenten Sessionwerte erneut
  speichern; es ist keine Stempelbuchung und benötigt keine neue Event-ID.
- Das Admin-Vortragsfeld bleibt an derselben Stelle mit derselben Beschriftung
  und Klassen sichtbar, aber `disabled readonly`, mit erklärendem Text. **Jeder
  eingereichte `ueberstunden`-Schlüssel** wird vor sämtlichen Form- **und Query-**
  Präferenzschreibungen mit sichtbarer 409-Fehlermeldung verweigert. Kein
  Teilspeichern und kein falsches Erfolgssignal für die Kontofunktion.
- Die vorhandenen Restorehinweise bleiben statisch. Der Snapshotknopf behält
  Position/Icon/Beschriftung/Klassen, ist aber deaktiviert und erklärt den
  fehlenden Anschluss. Kein funktionsloser Downloadlink, Restoreformular oder
  versteckter nativer Fallback. Native Settings-/Snapshot-/Importquellen bleiben
  unverändert; deren spätere Migration ist gesonderte Arbeit.

Die vorhandenen Formular-/Karten-/Select-/Dialogstrukturen, Kolibri, DE/EN/ZH-
Optionen sowie `Hell/Dunkel/System` bleiben erhalten. Native harte deutsche
Hinweis-/Dialogtexte werden nicht in einem neuen Übersetzungsprojekt umgebaut.

## Zusammenhängende Chinese-Kompatibilität statt nur eines Selecteintrags

Der Bestand enthält die wesentlichen traditionellen chinesischen Texte bereits
in `languages/zh.php`. Nur die bisher auf DE/EN begrenzten Framework-Komponenten
haben sie noch nicht zugelassen:

- `Copy`, `SupervisorPage` und die sieben geschützten Seitencontroller erlauben
  nun auch den vorhandenen manuellen/gespeicherten Wert `zh`; bestehende Aktionen,
  Fachfelder, Queries, Rechte, CSV-Anschluss und andere Locale-Werte bleiben
  unberührt.
- Die Login-Copy verwendet ausschließlich die bestehenden reinen
  `initial_language()`-/`valid_language()`-Definitionen. Manuelles/gespeichertes
  `zh` bleibt möglich, aber **Chinesisch wird nicht neu automatisch aus
  Accept-Language erkannt**. Die vorhandene automatische Browserwahl bleibt
  DE/EN mit DE-Fallback; keine neue globale App-Locale oder Authregel.
- Alle drei inerten Framework-`chrono.php`-Dictionaries umfassen die bestehenden
  86 Keys plus 14 exakt aus den nativen Dateien übernommene Settings-Keys.
  Chinesisch übernimmt davon 98 vorhandene Werte. Nur zwei fehlende notwendige
  Framework-Keys erhalten die eng begrenzten Kompatibilitätstexte
  `BUTTON_EXPORT_CSV = 匯出 CSV`, `TABLE_HEADER_TIME_RECORDS = 工時紀錄`.
  Das chinesische Supervisor-Dictionary übernimmt 15 vorhandene Werte und
  ergänzt nur `TABLE_HEADER_TOTAL_BREAK = 總休息時間` sowie
  `TABLE_HEADER_TOTAL_DURATION = 總工時`.
- Der Kalender erhält ausschließlich die bereits im geprüften offiziellen
  FullCalendar-7.1.3-Archiv vorhandene `locales/zh-tw/global.js` (593 Bytes,
  SHA-256 `eed62343d77e5e072ac26ab84713eeebdd376b4c711c3d18c5e131acc11f07fd`).
  Nur sein Mountargument bildet App-`zh` auf `zh-tw` ab; Session-/HTMLsprache
  bleibt `zh`. Ein bloß geladenes `zh-tw`-Asset würde den Wert `zh` nicht
  automatisch auflösen. Vorhandene Kalenderlizenz, unveränderte Paketversion,
  Asset-/Provenancepins und fester Servingpfad bleiben verbindlich; kein neuer
  CDN-/NPM-/Renderer-/Datumsadapter. `chrono-calendar.js`, Berliner Zeitbasis,
  Montagbeginn, Nur-Lesen und Ereignisdaten bleiben bytegleich.

Das ist keine behauptete vollständige Übersetzung aller harten deutschen
Bestandstexte. Es verhindert die neue Auswahl eines Werts, der beim nächsten
Seitenwechsel still auf DE zurückgesetzt oder von einem DTO verworfen würde.

## Expliziter enger Systemmodus-Bugfix, gleiche Browserpräferenz-Priorität

Nativ und im bisherigen Framework setzt das gemeinsame Theme-Script
`localStorage.theme` vor der Betriebssystemwahl durch. Settings previewt eine
Selectänderung bereits **vor** Save in diesem selben Browserkey. Die Session
speichert die Formularauswahl, überschreibt aber nicht heimlich einen bereits
vorhandenen Browserwert. Diese Priorität/Previewsemantik bleibt erhalten.

Der vorhandene Systemmodus war widersprüchlich: `system` wurde direkt als
DaisyUI-Theme gesetzt; zudem wurde die anfangs aufgelöste OS-Farbe sofort
persistiert, wodurch der nur bei fehlendem Browserwert aktive OS-Listener
praktisch nicht mehr reagierte. Das wird **ausdrücklich**, nicht als angebliche
native Bytegleichheit, in genau demselben Standardhandler berichtigt:

1. Raw Choice in `localStorage.theme`: `light`, `dark` oder `system`; kein neuer Key.
2. Nur das HTML erhält ein renderbares `light`/`dark`; bei `system` aus
   `matchMedia('(prefers-color-scheme: dark)')`. Ohne Browserwert bleibt die
   bisherige OS-Priorität erhalten, nun als dauerhaft rohe Wahl `system`.
3. Der OS-Listener reagiert nur bei fehlender/roher Systemwahl, nie bei einer
   expliziten Hell-/Dunkelwahl. Der bestehende Topbar-Toggle wählt weiterhin
   ausdrücklich Hell/Dunkel; der Checkboxzustand folgt der aufgelösten Farbe.
4. Das vorhandene Settings-Script delegiert an **denselben** `setTheme()`-
   Handler. Es entsteht kein zweiter Themeengine-/Speichervertrag.

Der Standard-`AttendancePage`-Themeenum bleibt `light`/`dark`; die rohe
Sessionauswahl gehört nur in den Settings-Anzeigewert, nicht als ungültiges
DaisyUI-Theme in das Chrome. Die bisherige Browser-/Session-Auswahlpriorität
wird nicht in eine neue geräteübergreifende Persistenzpolitik verwandelt.
Tatsächliche OS-/Browser-/Kalenderfarben und visuelle Details bleiben eine
separate grobe Browserabnahme, kein hier behaupteter Runtimeerfolg.

## Pending-/Auth-/Aktivierungsgrenze

`chrono-attendance-pending`, dessen Konto-/CSRF-Bindung, Event-ID, Retry-/
Antwortverlustpfad und sämtliche Buchungsclients bleiben unverändert.
Präferenzspeichern erzeugt keine Buchung, löscht oder benennt keinen Pendingkey
um und rotiert weder CSRF noch Sessionidentität. Der bestehende Session-Lock
bleibt für das gesamte explizite Webstack verpflichtend. Die Präferenzen sind
weiterhin Sessiondaten; Logout darf wie bisher diese Sessionwahl verlieren.

`includeSettings = false` wird nur an den bestehenden Registrar angehängt.
Expliziter Opt-in registriert GET/HEAD sowie POST unter dem unveränderten
Schutzstack. GET-Authfehler redirecten zu `login.php`, POST-Authfehler behalten
die vorhandene Webform-401-Textform. Nur der **exakte Settingspfad bei diesem
Opt-in** erhält die Native-Form-Ausnahme für Trim/EmptyString-Konverter; andere
Pfade/Validatoren/Middleware werden nicht erweitert. Der vorhandene Settings-
Sidebarlink erhält lediglich seinen Aktivzustand. Nur die vorhandene explizite
synthetische Composition und ihr fester Router bekommen Settings/den einen
zusätzlichen Kalender-Assetpfad; sie werden für diese Arbeit nicht gestartet.

Defaultbootstrap und `/up`, Runtimeadmission, andere Locale-/Export-/Web-/API-/
Terminalrouten, Sicherheits-/Host-/Session-/Schlüsselkonfiguration, native Dateien
und Datenschreibpfade bleiben erhalten. Kein Auth-/Browserworkaround, neue
Produktionsaktivierung oder Veröffentlichung.

## Begrenzter grober Nachweis

Ein zusammenhängender Source-/Localeabdeckungs-/Drei-Locale-Inhaltsrender-
Nachweis plus statische JavaScript-/Blade-/PHP-Syntax und Hash-/Reverse-Tree-
Bewahrung. Keine Feinunitmatrix, native Bootstrapausführung, Guard-/Session-
Injektion, echte DB, Anwendungs-/Controller-/HTTP-/Server-/Browserausführung,
kein NPM-/Packagecode-/JavaScriptlauf, keine neue CI oder Remoteaktion.
Geschützte Preferences-/CSRF-/Pending-/OS-/Reload-/Modal-/Calendar- und visuelle
Abnahme bleiben offen. Sourcefertig ist kein vollständiger #79/#76-/Cutoverpass.
