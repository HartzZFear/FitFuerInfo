<?php
/**
 * Vorlage fuer die lokale Konfiguration.
 *
 * ANLEITUNG:
 *   Diese Datei kopieren und als config.php im selben Ordner speichern.
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
define('BASE_URL', '/fitfuerinfo/FitFuerInfo');

// Auf true lassen, solange entwickelt wird: zeigt Fehlermeldungen an.
// Fuer die Abgabe auf false setzen.
define('DEBUG', true);

// Mindestanforderung an Passwoerter laut Aufgabenstellung:
// mindestens vier Zeichen, darunter ein Kleinbuchstabe und eine Zahl.
define('PW_MIN_LAENGE', 4);
