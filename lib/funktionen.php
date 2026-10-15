<?php
/**
 * Allgemeine Helfer, die nicht zu einem Fachbereich gehoeren.
 *
 * Voraussetzung: config/config.php ist geladen (passiert ueber lib/db.php),
 * weil die Funktionen BASE_URL und ASSET_VERSION verwenden.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

/**
 * Baut die URL einer Datei in assets/ mit angehaengter Versionsnummer.
 *
 * Der Browser darf CSS und JS zwischenspeichern. Wird ASSET_VERSION in der
 * config.php erhoeht, aendert sich die URL - der Browser laedt die Datei
 * dann neu, statt eine veraltete Fassung aus dem Cache zu nehmen.
 *
 * @param  string $pfad Pfad unterhalb von assets/, z. B. 'css/style.css'
 * @return string       BASE_URL . '/assets/css/style.css?v=1'
 */
function asset_url($pfad)
{
    return BASE_URL . '/assets/' . $pfad . '?v=' . ASSET_VERSION;
}
