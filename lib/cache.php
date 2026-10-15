<?php
/**
 * Cache-Header.
 *
 * Wird nur von index.php aufgerufen, damit die Header an genau einer Stelle
 * gesetzt werden.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

/**
 * Setzt die Header fuer Seiten, die bei jedem Aufruf neu erzeugt werden
 * (alle Seiten dieses Projekts): Der Browser soll sie weder speichern noch
 * ungefragt wiederverwenden. Sonst koennte z. B. nach dem Abmelden ueber
 * "Zurueck" eine alte Seite mit fremden Daten oder ein Freischaltcode
 * wieder auftauchen.
 *
 * session_start() sendet schon aehnliche Header. header() ersetzt einen
 * gleichnamigen Header, deshalb steht jeder danach genau einmal da.
 */
function cache_dynamisch()
{
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
}
