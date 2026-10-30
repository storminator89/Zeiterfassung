<?php

// Fixed own-local-password UI labels only; no credential input or model.
return [
    'title' => 'Eigenes lokales Passwort ändern',
    'contract' => 'Nur Ihr lokales Konto. Das neue Passwort muss 12–72 Bytes lang sein, ohne Steuerzeichen; es wird nicht gekürzt. Nach bestätigter Änderung werden Sie abgemeldet und müssen sich neu anmelden. Bestehende Websitzungen und API-Token werden bei ihrer nächsten Passwortversionsprüfung ungültig. Keine LDAP-/Terminal-PIN-Änderung oder Kontowiederherstellung. Andere Einstellungen werden mit diesem Formular nicht gespeichert.',
    'current' => 'Aktuelles Passwort',
    'new' => 'Neues Passwort (12–72 Bytes)',
    'confirm' => 'Neues Passwort bestätigen',
    'submit' => 'Passwort ändern und abmelden',
    'sessions_title' => 'Bisherige Web-/API-Anmeldungen abmelden',
    'sessions_contract' => 'Nur Ihr lokales Konto mit vorhandenem Bcrypt-Passwort: Nach bestätigter Aktion werden bisherige Websitzungen und API-Token bei ihrer nächsten geschützten Prüfung ungültig, auch diese Sitzung. Ihr Passwort bleibt gleich; eine neue Anmeldung ist damit weiter möglich. Bereits laufende Anfragen werden nicht rückwirkend abgebrochen. Keine Löschung einer Geräteliste, kein LDAP-/Terminal-PIN- oder Terminalgrant-Widerruf. Andere Einstellungen werden nicht gespeichert. Aktuelles Passwort höchstens 72 Bytes, ohne NUL; längere ältere Passwörter zuerst über den getrennten Passwortwechsel ändern.',
    'sessions_confirm' => 'Ich möchte alle bisherigen Web-/API-Anmeldungen meines lokalen Kontos widerrufen und mich danach neu anmelden.',
    'sessions_submit' => 'Anmeldungen widerrufen und abmelden',
];
