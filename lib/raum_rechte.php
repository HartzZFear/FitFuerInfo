<?php
/**
 * Gemeinsame Funktionen fuer die Raumverwaltung.
 *
 * Anders als beim Kurs hat die Tabelle raum KEINE Spalte fuer den Ersteller.
 * Wer einen Raum pflegen darf, steht deshalb ausschliesslich in
 * raum_bearbeiter - dazu kommt der Admin, der immer darf.
 *
 *   Anlegen / Loeschen : nur der Admin
 *   Bearbeiten         : der Admin und jeder Eintrag in raum_bearbeiter
 *
 * Wer einen Raum bearbeiten darf, hat BEWUSST keine Sonderrechte auf
 * Buchungen - das steckt in buchung_darf_verwalten() (buchung_logik.php).
 *
 * Wie bei den Kursen gehoert die Rechtepruefung IMMER serverseitig hierher,
 * nicht nur in das Ein-/Ausblenden der Buttons.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/buchung_logik.php';

/**
 * Liefert die Benutzer-IDs aller Bearbeiter eines Raums.
 *
 * @param  int $raumId
 * @return int[]
 */
function raum_bearbeiter_ids($raumId)
{
    $ids = array();

    $zeilen = abfrage(
        'SELECT benutzer_id FROM raum_bearbeiter WHERE raum_id = ?',
        array((int) $raumId)
    )->fetchAll();

    foreach ($zeilen as $zeile) {
        $ids[] = (int) $zeile['benutzer_id'];
    }

    return $ids;
}

/**
 * Darf der angegebene Benutzer den Raum bearbeiten?
 * Der Admin darf immer, sonst nur ein eingetragener Bearbeiter.
 *
 * @param  int  $raumId
 * @param  int  $benutzerId
 * @param  bool $istAdmin
 * @return bool
 */
function raum_darf_bearbeiten($raumId, $benutzerId, $istAdmin)
{
    if ($istAdmin) {
        return true;
    }

    return in_array((int) $benutzerId, raum_bearbeiter_ids($raumId), true);
}

/**
 * Anlegen und Loeschen von Raeumen bleibt dem Systemverwalter vorbehalten:
 * Raeume sind Betriebsmittel der Schule, kein Eigentum einzelner
 * Mitarbeiter, und raum hat keine Spalte fuer einen Ersteller.
 *
 * @param  bool $istAdmin
 * @return bool
 */
function raum_darf_anlegen($istAdmin)
{
    return (bool) $istAdmin;
}

/**
 * Loeschen: dieselbe Regel wie beim Anlegen. Zusaetzlich verhindert der
 * Fremdschluessel buchung.raum_id (RESTRICT), dass ein Raum mit Buchungen
 * verschwindet - raum_loeschen.php prueft das vorher und zeigt die Anzahl
 * an, statt den Benutzer in einen Datenbankfehler laufen zu lassen.
 *
 * @param  bool $istAdmin
 * @return bool
 */
function raum_darf_loeschen($istAdmin)
{
    return (bool) $istAdmin;
}

/**
 * Kuenftige Buchungen eines Raums (Beginn ab jetzt), mit den Kursdaten, die
 * fuer die Buchungsregeln 4 und 5 gebraucht werden.
 *
 * Vergangene Buchungen bleiben bewusst aussen vor: sie sind Historie und
 * haben schon stattgefunden - genau wie in buchung_darf_bearbeiten().
 *
 * @param  int $raumId
 * @return array Zeilen mit id, kurs_id, start, ende, titel, max_teilnehmer
 */
function raum_kuenftige_buchungen($raumId)
{
    return abfrage(
        'SELECT b.id, b.kurs_id, b.start, b.ende, k.titel, k.max_teilnehmer
         FROM buchung b
         JOIN kurs k ON k.id = b.kurs_id
         WHERE b.raum_id = ? AND b.start >= ?
         ORDER BY b.start',
        array((int) $raumId, date('Y-m-d H:i:s'))
    )->fetchAll();
}

/**
 * Prueft, ob der GESPEICHERTE Stand des Raums noch zu seinen kuenftigen
 * Buchungen passt, und sammelt - wie buchung_pruefen() - alle Verstoesse
 * ein statt beim ersten abzubrechen.
 *
 * Geprueft werden die beiden Buchungsregeln, die von den Raumdaten
 * abhaengen (siehe CONTRIBUTE.md, Abschnitt "Buchungsregeln"):
 *   Regel 4 (Software) ueber buchung_fehlende_software()
 *   Regel 5 (Platz)    ueber raum.arbeitsplaetze gegen kurs.max_teilnehmer
 *
 * WICHTIG: Die Funktion liest den Raum aus der Datenbank. Beim Speichern in
 * raum_bearbeiten.php wird sie deshalb INNERHALB der Transaktion aufgerufen,
 * nachdem die Aenderungen geschrieben wurden - so sieht sie genau den Stand,
 * der gespeichert werden soll. Sind Fehler dabei, macht der Aufrufer ein
 * rollBack() und nichts davon wird wirksam.
 *
 * @param  int $raumId
 * @return array leer = passt, sonst deutsche Fehlermeldungen
 */
function raum_konflikte_mit_buchungen($raumId)
{
    $raumId = (int) $raumId;
    $fehler = array();

    $raum = abfrage('SELECT name, arbeitsplaetze FROM raum WHERE id = ?', array($raumId))->fetch();
    if (!$raum) {
        return $fehler;
    }

    // Die fehlende Software haengt nur am Kurs, nicht an der einzelnen
    // Buchung. Einmal je Kurs ermitteln und merken spart bei mehreren
    // Terminen desselben Kurses die wiederholte Abfrage.
    $fehlendeJeKurs = array();

    foreach (raum_kuenftige_buchungen($raumId) as $buchung) {
        $kursId  = (int) $buchung['kurs_id'];
        $zeitraum = buchung_zeitraum_text($buchung['start'], $buchung['ende']);

        // --- Regel 5: Platz --------------------------------------------
        if ((int) $buchung['max_teilnehmer'] > (int) $raum['arbeitsplaetze']) {
            $fehler[] = 'Der Kurs "' . $buchung['titel'] . '" ist ' . $zeitraum
                . ' gebucht und hat ' . (int) $buchung['max_teilnehmer'] . ' Teilnehmer - '
                . $raum['name'] . ' hätte dann nur noch ' . (int) $raum['arbeitsplaetze']
                . ' Arbeitsplätze.';
        }

        // --- Regel 4: Software -----------------------------------------
        if (!isset($fehlendeJeKurs[$kursId])) {
            $fehlendeJeKurs[$kursId] = buchung_fehlende_software($kursId, $raumId);
        }

        if (!empty($fehlendeJeKurs[$kursId])) {
            $fehler[] = 'Dem Kurs "' . $buchung['titel'] . '", gebucht ' . $zeitraum
                . ', würde in ' . $raum['name'] . ' die Software fehlen: '
                . implode(', ', $fehlendeJeKurs[$kursId]) . '.';
        }
    }

    return $fehler;
}
