<?php
/**
 * Gemeinsame Funktionen fuer die Kursverwaltung.
 *
 * Eigentuemer eines Kurses sind der Ersteller (kurs.ersteller_id) UND alle
 * Eintraege in kurs_eigentuemer. Bearbeiten/Loeschen duerfen nur Eigentuemer
 * oder der Admin - diese Pruefung gehoert IMMER serverseitig hierher, nicht
 * nur in die Anzeige der Buttons.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/funktionen.php';

/**
 * Liefert die Benutzer-IDs aller Eigentuemer eines Kurses.
 *
 * @param  int $kursId
 * @return int[]
 */
function kurs_eigentuemer_ids($kursId)
{
    $ids = array();

    $ersteller = abfrage('SELECT ersteller_id FROM kurs WHERE id = ?', array($kursId))->fetchColumn();
    if ($ersteller !== false) {
        $ids[] = (int) $ersteller;
    }

    $zeilen = abfrage('SELECT benutzer_id FROM kurs_eigentuemer WHERE kurs_id = ?', array($kursId))->fetchAll();
    foreach ($zeilen as $zeile) {
        $ids[] = (int) $zeile['benutzer_id'];
    }

    return array_unique($ids);
}

/**
 * Darf der angegebene Benutzer den Kurs bearbeiten oder loeschen?
 * Der Admin darf immer, sonst nur ein Eigentuemer.
 *
 * @param  int  $kursId
 * @param  int  $benutzerId
 * @param  bool $istAdmin
 * @return bool
 */
function kurs_darf_verwalten($kursId, $benutzerId, $istAdmin)
{
    if ($istAdmin) {
        return true;
    }

    return in_array((int) $benutzerId, kurs_eigentuemer_ids($kursId), true);
}

/**
 * Zeigt statt der aktuellen Seite die Hinweisseite "Zugriff verweigert"
 * (pages/zugriff_verweigert.php) mit einer verstaendlichen Fehlermeldung
 * und einem Link zurueck zur passenden Liste, und beendet danach das
 * Skript. Wird verwendet, wenn eine serverseitige Rechtepruefung
 * fehlschlaegt (z. B. Direktaufruf einer fremden Kurs-ID).
 *
 * Die Funktion wird aus allen drei Bereichen aufgerufen. Damit der
 * Zurueck-Link dorthin fuehrt, wo der Benutzer hergekommen ist, geben die
 * Buchungs- und Raumseiten ihr Ziel selbst mit; ohne Angabe bleibt es bei
 * der Kursliste.
 *
 * @param string $nachricht
 * @param string $zurueckSeite Ziel des Zurueck-Links (Seitenname, z. B. 'raeume')
 * @param string $zurueckText  Beschriftung des Zurueck-Links
 */
function zugriff_verweigert_seite($nachricht, $zurueckSeite = 'kurse', $zurueckText = 'Zurück zu den Kursen')
{
    seite_wechseln('zugriff_verweigert', array(
        'nachricht'     => $nachricht,
        'zurueck_seite' => $zurueckSeite,
        'zurueck_text'  => $zurueckText,
    ));
}
