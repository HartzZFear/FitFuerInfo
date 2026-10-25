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
 * Kuenftige Buchungen eines Kurses (Beginn ab jetzt), mit den Raumdaten, die
 * fuer die Buchungsregeln 4 und 5 gebraucht werden. Gegenstueck zu
 * raum_kuenftige_buchungen() (raum_rechte.php).
 *
 * Vergangene Buchungen bleiben bewusst aussen vor: sie sind Historie und
 * haben schon stattgefunden.
 *
 * @param  int $kursId
 * @return array Zeilen mit id, raum_id, start, ende, raum, arbeitsplaetze
 */
function kurs_kuenftige_buchungen($kursId)
{
    return abfrage(
        'SELECT b.id, b.raum_id, b.start, b.ende, r.name AS raum, r.arbeitsplaetze
         FROM buchung b
         JOIN raum r ON r.id = b.raum_id
         WHERE b.kurs_id = ? AND b.start >= ?
         ORDER BY b.start',
        array((int) $kursId, date('Y-m-d H:i:s'))
    )->fetchAll();
}

/**
 * Prueft, ob der GESPEICHERTE Stand des Kurses noch zu seinen kuenftigen
 * Buchungen passt, und sammelt - wie buchung_pruefen() - alle Verstoesse
 * ein statt beim ersten abzubrechen. Gegenstueck zu
 * raum_konflikte_mit_buchungen() (raum_rechte.php).
 *
 * Geprueft werden die beiden Buchungsregeln, die von den Kursdaten
 * abhaengen:
 *   Regel 4 (Software) ueber buchung_fehlende_software()
 *   Regel 5 (Platz)    ueber kurs.max_teilnehmer gegen raum.arbeitsplaetze
 *
 * WICHTIG: Die Funktion liest den Kurs aus der Datenbank. kurs_speichern()
 * (kurs_logik.php) ruft sie deshalb INNERHALB der Transaktion auf,
 * nachdem die Aenderungen geschrieben wurden - so sieht sie genau den Stand,
 * der gespeichert werden soll. Sind Fehler dabei, macht der Aufrufer ein
 * rollBack() und nichts davon wird wirksam.
 *
 * @param  int $kursId
 * @return array leer = passt, sonst deutsche Fehlermeldungen
 */
function kurs_konflikte_mit_buchungen($kursId)
{
    $kursId = (int) $kursId;
    $fehler = array();

    $kurs = abfrage('SELECT titel, max_teilnehmer FROM kurs WHERE id = ?', array($kursId))->fetch();
    if (!$kurs) {
        return $fehler;
    }

    // Die fehlende Software haengt nur am Raum, nicht an der einzelnen
    // Buchung. Einmal je Raum ermitteln und merken spart bei mehreren
    // Terminen im selben Raum die wiederholte Abfrage.
    $fehlendeJeRaum = array();

    foreach (kurs_kuenftige_buchungen($kursId) as $buchung) {
        $raumId   = (int) $buchung['raum_id'];
        $zeitraum = buchung_zeitraum_text($buchung['start'], $buchung['ende']);

        // --- Regel 5: Platz --------------------------------------------
        if ((int) $kurs['max_teilnehmer'] > (int) $buchung['arbeitsplaetze']) {
            $fehler[] = 'Der Kurs "' . $kurs['titel'] . '" ist ' . $zeitraum . ' in ' . $buchung['raum']
                . ' gebucht - mit ' . (int) $kurs['max_teilnehmer'] . ' Teilnehmern wäre er dort zu groß, '
                . $buchung['raum'] . ' hat nur ' . (int) $buchung['arbeitsplaetze'] . ' Arbeitsplätze.';
        }

        // --- Regel 4: Software -----------------------------------------
        if (!isset($fehlendeJeRaum[$raumId])) {
            $fehlendeJeRaum[$raumId] = buchung_fehlende_software($kursId, $raumId);
        }

        if (!empty($fehlendeJeRaum[$raumId])) {
            $fehler[] = 'Der Kurs "' . $kurs['titel'] . '" ist ' . $zeitraum . ' in ' . $buchung['raum']
                . ' gebucht - dort würde die Software fehlen: '
                . implode(', ', $fehlendeJeRaum[$raumId]) . '.';
        }
    }

    return $fehler;
}
