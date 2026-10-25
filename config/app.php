<?php
/**
 * Projektweite Konstanten - gelten auf jedem Rechner gleich.
 *
 * Anders als config/config.php steht diese Datei im Repo: Kommt eine Seite
 * dazu oder aendert sich CSS/JS, bekommen alle die neue Fassung ueber Git,
 * statt ihre lokale config.php von Hand nachzuziehen.
 *
 * Geladen von lib/db.php direkt nach config/config.php.
 *
 * Bewusst PHP-5.6-Syntax: const statt define(), weil define() erst ab PHP 7
 * ein Array speichern kann. const geht dafuer nur hier oben auf Dateiebene,
 * nicht innerhalb von if oder Funktionen.
 */

// Seiten, die ueber index.php?seite=<name> aufgerufen werden duerfen.
// index.php bindet nur Dateien aus pages/ ein, deren Name hier steht -
// alles andere fuehrt zur 404-Seite. Neue Seite = hier eintragen.
const ERLAUBTE_SEITEN = array(
    'login', 'logout', 'passwort_setzen',
    'belegung', 'buchungen', 'buchung_bearbeiten', 'buchung_loeschen',
    'kurse', 'kurs_bearbeiten', 'kurs_loeschen',
    'raeume', 'raum_bearbeiten', 'raum_loeschen',
    'benutzer', 'benutzer_bearbeiten',
    'profil', 'hilfe',
);

// Versionsnummer fuer CSS- und JavaScript-Dateien. Sie haengt als ?v=...
// an jedem Link (asset_url() in lib/funktionen.php). Nach einer Aenderung an
// einer Datei in assets/ hier um eins erhoehen - dann laedt jeder Browser
// die neue Fassung, statt die alte aus seinem Cache zu nehmen.
const ASSET_VERSION = '5';

// Mindestanforderung an Passwoerter laut Aufgabenstellung:
// mindestens vier Zeichen, darunter ein Kleinbuchstabe und eine Zahl.
const PW_MIN_LAENGE = 4;
