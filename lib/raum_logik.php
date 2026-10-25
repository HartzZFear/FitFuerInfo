<?php
/**
 * Fachliche Funktionen der Raumverwaltung: Raeume laden, filtern, speichern
 * und loeschen. Hier steht das SQL der Seiten raeume, raum_bearbeiten und
 * raum_loeschen.
 *
 * Wer einen Raum anlegen, bearbeiten oder loeschen darf, steht in
 * raum_rechte.php. Diese Pruefung macht die Seite, bevor sie eine Funktion
 * von hier aufruft.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/raum_rechte.php';

/**
 * Raumliste mit den Filtern der Seite raeume. Alle vier Filter sind frei
 * kombinierbar und landen ausschliesslich als Parameter im Prepared
 * Statement, nie per Stringverkettung im SQL.
 *
 * @param  string $suche      Teil des Raumnamens, '' = alle
 * @param  bool   $nurEigene  nur Raeume, fuer die der Benutzer als Bearbeiter eingetragen ist
 * @param  int    $softwareId 0 = beliebig
 * @param  string $minPlaetze Mindestanzahl Arbeitsplaetze, '' = beliebig
 * @param  int    $benutzerId
 * @return array Zeilen mit id, name, arbeitsplaetze
 */
function raum_liste($suche, $nurEigene, $softwareId, $minPlaetze, $benutzerId)
{
    $bedingungen = array();
    $parameter   = array();

    $sql = 'SELECT DISTINCT r.id, r.name, r.arbeitsplaetze
            FROM raum r
            LEFT JOIN raum_bearbeiter rb ON rb.raum_id = r.id';

    if ($suche !== '') {
        $bedingungen[] = 'r.name LIKE ?';
        $parameter[]   = '%' . $suche . '%';
    }

    if ($nurEigene) {
        $bedingungen[] = 'rb.benutzer_id = ?';
        $parameter[]   = $benutzerId;
    }

    // Software ueber EXISTS statt ueber einen weiteren JOIN: so bleibt die
    // Ergebnismenge eindeutig, auch wenn ein Raum viele Pakete hat.
    if ($softwareId > 0) {
        $bedingungen[] = 'EXISTS (
                SELECT 1 FROM raum_software rs
                WHERE rs.raum_id = r.id AND rs.software_id = ?
            )';
        $parameter[]   = $softwareId;
    }

    if ($minPlaetze !== '') {
        $bedingungen[] = 'r.arbeitsplaetze >= ?';
        $parameter[]   = (int) $minPlaetze;
    }

    if (!empty($bedingungen)) {
        $sql .= ' WHERE ' . implode(' AND ', $bedingungen);
    }

    $sql .= ' ORDER BY r.name';

    return abfrage($sql, $parameter)->fetchAll();
}

/**
 * Softwarenamen fuer mehrere Raeume (fuer die Chips auf den Karten), in
 * einer Abfrage statt einer pro Karte.
 *
 * @param  array $raumIds
 * @return array raum_id => Liste von Softwarenamen, alphabetisch
 */
function raum_software_je_raum($raumIds)
{
    $ergebnis = array();
    if (empty($raumIds)) {
        return $ergebnis;
    }

    $platzhalter = implode(',', array_fill(0, count($raumIds), '?'));
    $zeilen = abfrage(
        'SELECT rs.raum_id, s.name
         FROM raum_software rs
         JOIN software s ON s.id = rs.software_id
         WHERE rs.raum_id IN (' . $platzhalter . ')
         ORDER BY s.name',
        $raumIds
    )->fetchAll();

    foreach ($zeilen as $zeile) {
        $ergebnis[$zeile['raum_id']][] = $zeile['name'];
    }
    return $ergebnis;
}

/**
 * Bearbeiter fuer mehrere Raeume, in einer Abfrage: die IDs fuer die
 * Rechteanzeige, die Namen fuer die Karte.
 *
 * @param  array $raumIds
 * @return array 'ids' => raum_id => [benutzer_id, ...], 'namen' => raum_id => [name, ...]
 */
function raum_bearbeiter_je_raum($raumIds)
{
    $ergebnis = array('ids' => array(), 'namen' => array());
    if (empty($raumIds)) {
        return $ergebnis;
    }

    $platzhalter = implode(',', array_fill(0, count($raumIds), '?'));
    $zeilen = abfrage(
        'SELECT rb.raum_id, rb.benutzer_id, b.name
         FROM raum_bearbeiter rb
         JOIN benutzer b ON b.id = rb.benutzer_id
         WHERE rb.raum_id IN (' . $platzhalter . ')
         ORDER BY b.name',
        $raumIds
    )->fetchAll();

    foreach ($zeilen as $zeile) {
        $ergebnis['ids'][$zeile['raum_id']][]   = (int) $zeile['benutzer_id'];
        $ergebnis['namen'][$zeile['raum_id']][] = $zeile['name'];
    }
    return $ergebnis;
}

/**
 * Anzahl der kommenden Buchungen (Beginn ab jetzt) fuer mehrere Raeume.
 *
 * @param  array $raumIds
 * @return array raum_id => Anzahl (Raeume ohne Buchung fehlen)
 */
function raum_kommende_buchungen_je_raum($raumIds)
{
    $ergebnis = array();
    if (empty($raumIds)) {
        return $ergebnis;
    }

    $platzhalter = implode(',', array_fill(0, count($raumIds), '?'));
    $zeilen = abfrage(
        'SELECT raum_id, COUNT(*) AS anzahl
         FROM buchung
         WHERE start >= ? AND raum_id IN (' . $platzhalter . ')
         GROUP BY raum_id',
        array_merge(array(date('Y-m-d H:i:s')), $raumIds)
    )->fetchAll();

    foreach ($zeilen as $zeile) {
        $ergebnis[$zeile['raum_id']] = (int) $zeile['anzahl'];
    }
    return $ergebnis;
}

/**
 * Nur fuer die Anzeige der Buttons: ist der eingeloggte Benutzer fuer diesen
 * Raum eingetragen? Die Daten liegen durch raum_bearbeiter_je_raum() schon
 * vor, deshalb hier ohne eigene Abfrage. Die massgebliche Pruefung steht in
 * raum_darf_bearbeiten() (raum_rechte.php).
 *
 * @param  array $raum             Zeile aus raum_liste()
 * @param  array $bearbeiterJeRaum raum_id => [benutzer_id, ...]
 * @param  int   $benutzerId
 * @param  bool  $istAdmin
 * @return bool
 */
function raum_ist_meiner($raum, $bearbeiterJeRaum, $benutzerId, $istAdmin)
{
    if ($istAdmin) {
        return true;
    }
    return isset($bearbeiterJeRaum[$raum['id']])
        && in_array((int) $benutzerId, $bearbeiterJeRaum[$raum['id']], true);
}

/**
 * Einen Raum laden.
 *
 * @param  int $raumId
 * @return array|false Zeile aus raum oder false, wenn es ihn nicht gibt
 */
function raum_laden($raumId)
{
    return abfrage('SELECT * FROM raum WHERE id = ?', array((int) $raumId))->fetch();
}

/**
 * IDs der Software, die im Raum installiert ist (fuer die Checkboxen).
 *
 * @param  int $raumId
 * @return int[]
 */
function raum_software_ids($raumId)
{
    $ids = array();
    $zeilen = abfrage('SELECT software_id FROM raum_software WHERE raum_id = ?', array((int) $raumId))->fetchAll();
    foreach ($zeilen as $zeile) {
        $ids[] = (int) $zeile['software_id'];
    }
    return $ids;
}

/**
 * Wandelt die Eingabe "Arbeitsplaetze" in eine Zahl um. Erlaubt sind nur
 * ganze Zahlen ab 1.
 *
 * @param  string $text Eingabe aus dem Formular
 * @return int|null null, wenn die Eingabe ungueltig ist
 */
function raum_arbeitsplaetze($text)
{
    if ($text === '' || !ctype_digit($text) || (int) $text < 1) {
        return null;
    }
    return (int) $text;
}

/**
 * Prueft die Eingaben und legt den Raum an bzw. speichert ihn, samt
 * Software und - nur beim Admin - Bearbeitern.
 *
 * Wer Arbeitsplaetze reduziert oder Software entfernt, kann damit bereits
 * bestehende Buchungen ungueltig machen (Buchungsregeln 4 und 5). Deshalb
 * wird der neue Stand innerhalb der Transaktion mit
 * raum_konflikte_mit_buchungen() geprueft und bei Konflikten verworfen.
 *
 * @param  int|null $raumId   null = neuer Raum
 * @param  array    $eingabe  name, arbeitsplaetze (Text aus dem Formular),
 *                            software (int[]), bearbeiter (int[], nur beim Admin ausgewertet)
 * @param  bool     $istAdmin nur der Admin aendert die Bearbeiterliste
 * @return true|array true bei Erfolg, sonst
 *                    array('fehler' => deutsche Fehlermeldungen,
 *                          'konflikte' => true, wenn kuenftige Buchungen nicht mehr passen wuerden)
 */
function raum_speichern($raumId, $eingabe, $istAdmin)
{
    $fehler = array();

    if ($eingabe['name'] === '') {
        $fehler[] = 'Bitte einen Raumnamen eingeben.';
    } elseif (strlen($eingabe['name']) > 50) {
        $fehler[] = 'Der Raumname darf höchstens 50 Zeichen lang sein.';
    }

    $arbeitsplaetze = raum_arbeitsplaetze($eingabe['arbeitsplaetze']);
    if ($arbeitsplaetze === null) {
        $fehler[] = 'Die Anzahl der Arbeitsplätze muss eine ganze Zahl von mindestens 1 sein.';
    }

    if (!empty($fehler)) {
        return array('fehler' => $fehler, 'konflikte' => false);
    }

    $konflikte = array();

    // Transaktion aus zwei Gruenden:
    // 1. Raum, Software und Bearbeiter sind mehrere Schreibbefehle, die
    //    zusammengehoeren - bei einem Fehler soll nichts halb gespeichert sein.
    // 2. Erst wird geschrieben, dann geprueft, ob der neue Stand noch zu den
    //    kuenftigen Buchungen passt; wenn nicht, macht rollBack() alles
    //    rueckgaengig.
    $db = db();
    try {
        $db->beginTransaction();

        // Die Zeile des Raums sperren, bevor geprueft und geschrieben wird.
        // Dieselbe Sperre nimmt buchung_speichern() vor dem Speichern einer
        // Buchung - dadurch kann niemand einen Termin in den Raum legen,
        // waehrend hier gerade seine Plaetze oder seine Software geaendert
        // werden. Ein neuer Raum hat noch keine Buchungen, also keine Sperre.
        if ($raumId !== null) {
            abfrage('SELECT id FROM raum WHERE id = ? FOR UPDATE', array($raumId));
        }

        // Der Raumname ist in der Datenbank eindeutig (uk_raum_name).
        // Vorher pruefen, damit der Benutzer eine verstaendliche Meldung
        // bekommt statt eines Datenbankfehlers.
        $sql       = 'SELECT id FROM raum WHERE name = ?';
        $parameter = array($eingabe['name']);
        if ($raumId !== null) {
            $sql .= ' AND id <> ?';
            $parameter[] = $raumId;
        }

        if (abfrage($sql, $parameter)->fetch()) {
            $fehler[] = 'Es gibt bereits einen Raum mit dem Namen "' . $eingabe['name'] . '".';
        }

        if (empty($fehler)) {
            if ($raumId !== null) {
                abfrage(
                    'UPDATE raum SET name = ?, arbeitsplaetze = ? WHERE id = ?',
                    array($eingabe['name'], $arbeitsplaetze, $raumId)
                );
            } else {
                abfrage(
                    'INSERT INTO raum (name, arbeitsplaetze) VALUES (?, ?)',
                    array($eingabe['name'], $arbeitsplaetze)
                );
                $raumId = (int) $db->lastInsertId();
            }

            abfrage('DELETE FROM raum_software WHERE raum_id = ?', array($raumId));
            foreach ($eingabe['software'] as $softwareId) {
                abfrage('INSERT INTO raum_software (raum_id, software_id) VALUES (?, ?)', array($raumId, $softwareId));
            }

            if ($istAdmin) {
                abfrage('DELETE FROM raum_bearbeiter WHERE raum_id = ?', array($raumId));
                foreach ($eingabe['bearbeiter'] as $bearbeiterId) {
                    abfrage('INSERT INTO raum_bearbeiter (raum_id, benutzer_id) VALUES (?, ?)', array($raumId, $bearbeiterId));
                }
            }

            // Jetzt steht der neue Stand in der Transaktion - genau so
            // sieht ihn die Pruefung. Passt er nicht zu den kuenftigen
            // Buchungen, macht das rollBack() unten alles rueckgaengig.
            $konflikte = raum_konflikte_mit_buchungen($raumId);
            foreach ($konflikte as $meldung) {
                $fehler[] = $meldung;
            }
        }

        if (empty($fehler)) {
            $db->commit();
            return true;
        }

        $db->rollBack();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $fehler[] = 'Der Raum konnte nicht gespeichert werden. Bitte erneut versuchen.';
    }

    return array('fehler' => $fehler, 'konflikte' => !empty($konflikte));
}

/**
 * Anzahl aller Buchungen eines Raums. Auch vergangene zaehlen mit: der
 * Fremdschluessel unterscheidet nicht, und die Belegung der letzten Wochen
 * soll nachvollziehbar bleiben.
 *
 * @param  int $raumId
 * @return int
 */
function raum_anzahl_buchungen($raumId)
{
    return (int) abfrage('SELECT COUNT(*) FROM buchung WHERE raum_id = ?', array((int) $raumId))->fetchColumn();
}

/**
 * Loescht einen Raum. raum_software und raum_bearbeiter haengen per
 * ON DELETE CASCADE am Raum und verschwinden im selben Befehl mit. Haengen
 * noch Buchungen am Raum, lehnt die Datenbank das Loeschen ab (RESTRICT).
 *
 * @param  int $raumId
 * @return true|string true bei Erfolg, sonst eine deutsche Fehlermeldung
 */
function raum_loeschen($raumId)
{
    try {
        abfrage('DELETE FROM raum WHERE id = ?', array((int) $raumId));
    } catch (PDOException $e) {
        return 'Der Raum kann nicht gelöscht werden, solange noch Buchungen dafür bestehen.';
    }
    return true;
}
