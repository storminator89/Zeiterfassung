<?php
return [
 'title'=>'API verwalten', 'contract'=>'Im Standardstart ist die API anfangs aus. Lokale Administration kann sie hier auch nach der Ersteinrichtung einrichten und schalten – ohne Environmentdatei oder Neustart.',
 'enabled'=>'An', 'disabled'=>'Aus', 'state'=>'Betriebsfreigabe', 'revision'=>'Revision', 'key'=>'Signierschlüssel',
 'key_missing'=>'Noch nicht vorbereitet', 'key_ready'=>'Metadaten vorbereitet; Schlüssel wird beim Einschalten und bei Nutzung geprüft', 'key_operator'=>'Vorhandenes zugelassenes Operatorprofil; Schlüssel wird beim Einschalten geprüft',
 'key_blocked'=>'Privater Zustand nicht bestätigt. Keine automatische Reparatur, Ersetzung oder Wiederholung.',
 'boundary'=>'An eröffnet die vorhandene API für gültig authentifizierte Clients. Aus sperrt API-Zugriffe, löscht oder rotiert aber keine Schlüssel oder Tokens. Beim Wiedereinschalten können noch nicht abgelaufene Tokens wieder gelten. Kontosperren und Passwortwiderruf bleiben getrennt wirksam.',
 'password'=>'Aktuelles eigenes Passwort', 'confirm_enable'=>'Ich bestätige das Einschalten beziehungsweise Prüfen der API-Freigabe.', 'confirm_disable'=>'Ich bestätige das Ausschalten der API.',
 'confirm_key'=>'Ich bestätige ausdrücklich die Neuanlage eines unabhängigen privaten API-Schlüssels. Bestehende Schlüssel werden nicht ersetzt.',
 'enable'=>'API prüfen und einschalten', 'enable_new'=>'API-Schlüssel anlegen und einschalten', 'disable'=>'API ausschalten',
 'key_secret'=>'Der Signierschlüssel wird niemals angezeigt. Dies erstellt keinen persönlichen Bearer-Token; Clients melden sich über die vorhandene API an.',
 'notice_enabled'=>'API-Freigabe bestätigt: An.', 'notice_disabled'=>'API-Freigabe bestätigt: Aus.', 'notice_unchanged'=>'API-Stand bestätigt.',
];
