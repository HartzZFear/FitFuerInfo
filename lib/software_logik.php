<?php
/**
 * Softwarepakete.
 *
 * Kurse brauchen Software (kurs_software), Raeume haben Software
 * (raum_software). Die Liste aller Pakete wird in beiden Bereichen
 * gebraucht und steht deshalb hier einmal.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

require_once __DIR__ . '/db.php';

/**
 * Alle Softwarepakete, alphabetisch - fuer die Checkbox-Listen in den
 * Kurs- und Raumformularen und den Software-Filter der Raumliste.
 *
 * @return array Zeilen mit id, name
 */
function software_alle()
{
    return abfrage('SELECT id, name FROM software ORDER BY name')->fetchAll();
}
