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

// Fehlermeldungen mit Details anzeigen? Fuer Abgabe und Praesentation false
// lassen: Bei einem Fehler erscheint dann nur die Fehlerseite ohne Dateipfade
// und SQL. Zum Entwickeln in der eigenen config.php auf true setzen - dann
// stehen PHP-Meldungen und die Fehlerdetails direkt auf der Seite.
define('DEBUG', false);
