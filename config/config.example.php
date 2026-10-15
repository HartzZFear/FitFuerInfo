<?php
/**
 * Vorlage fuer die lokale Konfiguration.
 *
 * ANLEITUNG:
 *   Diese Datei kopieren und als config.php im selben Ordner (config/) speichern.
 *   config.php steht in der .gitignore und wird NICHT committet,
 *   weil sie lokale Zugangsdaten enthaelt.
 *
 * Die Standardwerte unten passen zu einer frischen XAMPP-Installation
 * (Benutzer "root", kein Passwort). Wer sein MySQL abgesichert hat,
 * traegt hier seine eigenen Daten ein.
 */

// --- Datenbank ---------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'fitfuerinfo');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// --- Anwendung ---------------------------------------------------------

// Basispfad im Browser. Bei C:\xampp\htdocs\fitfuerinfo ist das
// "/fitfuerinfo". Liegt das Projekt direkt in htdocs, hier "" eintragen.
define('BASE_URL', '/fitfuerinfo');

// Auf true lassen, solange entwickelt wird: zeigt Fehlermeldungen an.
// WICHTIG: Fuer Abgabe und Praesentation MUSS hier false stehen - sonst
// sieht jeder Besucher bei einem Fehler PHP-Meldungen mit Dateipfaden und
// Datenbankdetails.
define('DEBUG', true);

// Versionsnummer fuer CSS- und JavaScript-Dateien. Sie haengt als ?v=...
// an jedem Link (asset_url() in lib/funktionen.php). Nach einer Aenderung an
// einer Datei in assets/ hier um eins erhoehen - dann laedt jeder Browser
// die neue Fassung, statt die alte aus seinem Cache zu nehmen.
define('ASSET_VERSION', '1');

// Mindestanforderung an Passwoerter laut Aufgabenstellung:
// mindestens vier Zeichen, darunter ein Kleinbuchstabe und eine Zahl.
define('PW_MIN_LAENGE', 4);
