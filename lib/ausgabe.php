<?php
/**
 * Helfer fuer die HTML-Ausgabe - ohne Abhaengigkeit von config.php oder
 * der Datenbank.
 *
 * Eingebunden von lib/db.php (damit gibt es h() ueberall in der Anwendung)
 * und direkt von einrichtung.php und tests/test.php: Die beiden laufen auch,
 * wenn config/config.php noch fehlt, und duerfen lib/db.php deshalb nicht
 * einbinden (lib/db.php bricht dann ab).
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

/**
 * Text sicher fuer die Ausgabe in HTML aufbereiten (Schutz vor XSS).
 * Jede Ausgabe von Daten aus der Datenbank durch diese Funktion schicken.
 *
 * @param  string $text
 * @return string
 */
function h($text)
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
