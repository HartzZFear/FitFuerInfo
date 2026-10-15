<?php
/**
 * Front-Controller: einziger Einstiegspunkt der Anwendung.
 *
 * Jede Seite wird als index.php?seite=<name> aufgerufen. Ablauf:
 *   1. Konfiguration und lib/ laden, Session starten
 *   2. Seitennamen gegen die Whitelist ERLAUBTE_SEITEN (config/app.php) pruefen
 *   3. Cache-Header setzen
 *   4. Seite mit Kopf und Fuss ausgeben (seite_ausgeben() in lib/funktionen.php)
 *
 * Die Dateien in pages/ pruefen FFI_START und zeigen bei direktem Aufruf
 * nichts an.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

define('FFI_START', true);

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/funktionen.php';
require_once __DIR__ . '/lib/cache.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/kurs_rechte.php';
require_once __DIR__ . '/lib/raum_rechte.php';
require_once __DIR__ . '/lib/buchung_logik.php';
require_once __DIR__ . '/lib/kalender_logik.php';
require_once __DIR__ . '/lib/benutzer_logik.php';
require_once __DIR__ . '/templates/bausteine.php';

if (!defined('ERLAUBTE_SEITEN')) {
    die(
        'Fehler: ERLAUBTE_SEITEN fehlt. '
        . 'Bitte config/app.php aus dem Repo holen (git pull). '
        . 'Details zeigt tests/test.php.'
    );
}

session_starten();

// Welche Seite? Ohne Angabe: angemeldet -> Belegung, sonst -> Login.
if (isset($_GET['seite'])) {
    $seite = $_GET['seite'];
} elseif (ist_eingeloggt()) {
    $seite = 'belegung';
} else {
    $seite = 'login';
}

// Nur Namen aus der Whitelist einbinden - nie einen Dateinamen ungeprueft
// aus der URL (sonst koennte jemand z. B. ?seite=../config/config laden).
// true = strenger Vergleich, ein Array aus ?seite[]=... passt so nie.
if (!in_array($seite, ERLAUBTE_SEITEN, true)) {
    $seite = 'nicht_gefunden';
}

cache_dynamisch();

// UEBERGANG (Schritt 3): Diese Seiten sind noch nicht auf index.php
// umgestellt und liegen weiter als eigenstaendige Dateien in pages/. Der
// Block wird geloescht, sobald alle Seiten umgestellt sind.
$nochNichtUmgestellt = array(
    'logout', 'passwort_setzen', 'buchung_bearbeiten', 'buchung_loeschen',
    'kurs_bearbeiten', 'kurs_loeschen', 'raum_bearbeiten', 'raum_loeschen',
    'benutzer_bearbeiten',
);
if (in_array($seite, $nochNichtUmgestellt, true)) {
    $uebrige = $_GET;
    unset($uebrige['seite']);
    header('Location: ' . BASE_URL . '/pages/' . $seite . '.php'
        . (empty($uebrige) ? '' : '?' . http_build_query($uebrige, '', '&')));
    exit;
}

seite_ausgeben($seite);
