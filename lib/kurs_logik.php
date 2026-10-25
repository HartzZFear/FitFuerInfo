<?php
/**
 * Fachliche Funktionen der Kursverwaltung: Kurse laden, filtern, speichern
 * und loeschen. Hier steht das SQL der Seiten kurse, kurs_bearbeiten und
 * kurs_loeschen.
 *
 * Wer einen Kurs bearbeiten darf, steht in kurs_rechte.php. Diese Pruefung
 * macht die Seite, bevor sie eine Funktion von hier aufruft.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

require_once __DIR__ . '/db.php';

/**
 * Kursliste mit den Filtern der Seite kurse. Beide Filter sind frei
 * kombinierbar und landen ausschliesslich als Parameter im Prepared
 * Statement.
 *
 * @param  string $suche      Teil des Kurstitels, '' = alle
 * @param  bool   $nurEigene  nur Kurse, deren Ersteller oder Eigentuemer der Benutzer ist
 * @param  int    $benutzerId
 * @return array Zeilen mit id, titel, beschreibung, max_teilnehmer, ersteller_id
 */
function kurs_liste($suche, $nurEigene, $benutzerId)
{
    $bedingungen = array();
    $parameter   = array();

    $sql = 'SELECT DISTINCT k.id, k.titel, k.beschreibung, k.max_teilnehmer, k.ersteller_id
            FROM kurs k
            LEFT JOIN kurs_eigentuemer ke ON ke.kurs_id = k.id';

    if ($suche !== '') {
        $bedingungen[] = 'k.titel LIKE ?';
        $parameter[]   = '%' . $suche . '%';
    }

    if ($nurEigene) {
        $bedingungen[] = '(k.ersteller_id = ? OR ke.benutzer_id = ?)';
        $parameter[]   = $benutzerId;
        $parameter[]   = $benutzerId;
    }

    if (!empty($bedingungen)) {
        $sql .= ' WHERE ' . implode(' AND ', $bedingungen);
    }

    $sql .= ' ORDER BY k.titel';

    return abfrage($sql, $parameter)->fetchAll();
}

/**
 * Eintraege in kurs_eigentuemer fuer mehrere Kurse - eine Abfrage fuer alle
 * Karten statt einer pro Karte.
 *
 * @param  array $kursIds
 * @return array kurs_id => Liste von benutzer_id (int)
 */
function kurs_eigentuemer_je_kurs($kursIds)
{
    $ergebnis = array();
    if (empty($kursIds)) {
        return $ergebnis;
    }

    $platzhalter = implode(',', array_fill(0, count($kursIds), '?'));
    $zeilen = abfrage(
        'SELECT kurs_id, benutzer_id FROM kurs_eigentuemer WHERE kurs_id IN (' . $platzhalter . ')',
        $kursIds
    )->fetchAll();

    foreach ($zeilen as $zeile) {
        $ergebnis[$zeile['kurs_id']][] = (int) $zeile['benutzer_id'];
    }
    return $ergebnis;
}

/**
 * Softwarenamen fuer mehrere Kurse (fuer die Chips auf den Karten), in
 * einer Abfrage.
 *
 * @param  array $kursIds
 * @return array kurs_id => Liste von Softwarenamen, alphabetisch
 */
function kurs_software_je_kurs($kursIds)
{
    $ergebnis = array();
    if (empty($kursIds)) {
        return $ergebnis;
    }

    $platzhalter = implode(',', array_fill(0, count($kursIds), '?'));
    $zeilen = abfrage(
        'SELECT ks.kurs_id, s.name
         FROM kurs_software ks
         JOIN software s ON s.id = ks.software_id
         WHERE ks.kurs_id IN (' . $platzhalter . ')
         ORDER BY s.name',
        $kursIds
    )->fetchAll();

    foreach ($zeilen as $zeile) {
        $ergebnis[$zeile['kurs_id']][] = $zeile['name'];
    }
    return $ergebnis;
}

/**
 * Nur fuer die Kartenanzeige: ist der eingeloggte Benutzer Ersteller oder
 * in kurs_eigentuemer eingetragen? Die Daten liegen durch
 * kurs_eigentuemer_je_kurs() schon vor, deshalb ohne eigene Abfrage. Die
 * massgebliche, serverseitige Pruefung fuer Bearbeiten/Loeschen selbst
 * steht in kurs_darf_verwalten() (lib/kurs_rechte.php).
 *
 * @param  array $kurs              Zeile aus kurs_liste()
 * @param  array $eigentuemerJeKurs Ergebnis von kurs_eigentuemer_je_kurs()
 * @param  int   $benutzerId
 * @return bool
 */
function kurs_ist_eigene_karte($kurs, $eigentuemerJeKurs, $benutzerId)
{
    if ((int) $kurs['ersteller_id'] === (int) $benutzerId) {
        return true;
    }
    return isset($eigentuemerJeKurs[$kurs['id']])
        && in_array((int) $benutzerId, $eigentuemerJeKurs[$kurs['id']], true);
}

/**
 * Alle Kurse, alphabetisch - fuer die Kurs-Dropdowns in Belegung und
 * Buchungsliste.
 *
 * @return array Zeilen mit id, titel
 */
function kurs_alle()
{
    return abfrage('SELECT id, titel FROM kurs ORDER BY titel')->fetchAll();
}

/**
 * Einen Kurs laden.
 *
 * @param  int $kursId
 * @return array|false Zeile aus kurs oder false, wenn es ihn nicht gibt
 */
function kurs_laden($kursId)
{
    return abfrage('SELECT * FROM kurs WHERE id = ?', array((int) $kursId))->fetch();
}

/**
 * IDs der Software, die der Kurs braucht (fuer die Checkboxen).
 *
 * @param  int $kursId
 * @return int[]
 */
function kurs_software_ids($kursId)
{
    $ids = array();
    $zeilen = abfrage('SELECT software_id FROM kurs_software WHERE kurs_id = ?', array((int) $kursId))->fetchAll();
    foreach ($zeilen as $zeile) {
        $ids[] = (int) $zeile['software_id'];
    }
    return $ids;
}

/**
 * IDs der in kurs_eigentuemer eingetragenen Benutzer (fuer die Checkboxen
 * des Admins). Anders als kurs_eigentuemer_ids() ohne den Ersteller - der
 * steht in kurs.ersteller_id und ist im Formular nicht waehlbar.
 *
 * @param  int $kursId
 * @return int[]
 */
function kurs_eigentuemer_eintraege($kursId)
{
    $ids = array();
    $zeilen = abfrage('SELECT benutzer_id FROM kurs_eigentuemer WHERE kurs_id = ?', array((int) $kursId))->fetchAll();
    foreach ($zeilen as $zeile) {
        $ids[] = (int) $zeile['benutzer_id'];
    }
    return $ids;
}

/**
 * Wandelt die Eingabe "maximale Teilnehmerzahl" in eine Zahl um.
 * Erlaubt sind nur ganze Zahlen ab 1.
 *
 * @param  string $text Eingabe aus dem Formular
 * @return int|null null, wenn die Eingabe ungueltig ist
 */
function kurs_teilnehmerzahl($text)
{
    if ($text === '' || !ctype_digit($text) || (int) $text < 1) {
        return null;
    }
    return (int) $text;
}

/**
 * Prueft die Eingaben und legt den Kurs an bzw. speichert ihn, samt
 * Software und - nur beim Admin - Eigentuemern.
 *
 * @param  int|null $kursId     null = neuer Kurs
 * @param  array    $eingabe    titel, beschreibung, max_teilnehmer (Text aus dem Formular),
 *                              software (int[]), eigentuemer (int[], nur beim Admin ausgewertet)
 * @param  int      $benutzerId wird beim Anlegen Ersteller
 * @param  bool     $istAdmin   nur der Admin aendert die Eigentuemer
 * @return true|array true bei Erfolg, sonst deutsche Fehlermeldungen
 */
function kurs_speichern($kursId, $eingabe, $benutzerId, $istAdmin)
{
    $fehler = array();

    if ($eingabe['titel'] === '') {
        $fehler[] = 'Bitte einen Kurstitel eingeben.';
    }

    $maxTeilnehmer = kurs_teilnehmerzahl($eingabe['max_teilnehmer']);
    if ($maxTeilnehmer === null) {
        $fehler[] = 'Die maximale Teilnehmerzahl muss eine ganze Zahl von mindestens 1 sein.';
    }

    if (!empty($fehler)) {
        return $fehler;
    }

    $beschreibung = $eingabe['beschreibung'] !== '' ? $eingabe['beschreibung'] : null;

    // Transaktion: Kurs, Software und Eigentuemer sind mehrere Schreibbefehle,
    // die zusammengehoeren. Scheitert einer (z. B. eine manipulierte
    // Software-ID verletzt den Fremdschluessel), wird nichts gespeichert
    // statt eines halb geaenderten Kurses.
    $db = db();
    try {
        $db->beginTransaction();

        if ($kursId !== null) {
            abfrage(
                'UPDATE kurs SET titel = ?, beschreibung = ?, max_teilnehmer = ? WHERE id = ?',
                array($eingabe['titel'], $beschreibung, $maxTeilnehmer, $kursId)
            );
        } else {
            abfrage(
                'INSERT INTO kurs (titel, beschreibung, max_teilnehmer, ersteller_id) VALUES (?, ?, ?, ?)',
                array($eingabe['titel'], $beschreibung, $maxTeilnehmer, $benutzerId)
            );
            $kursId = (int) $db->lastInsertId();
        }

        abfrage('DELETE FROM kurs_software WHERE kurs_id = ?', array($kursId));
        foreach ($eingabe['software'] as $softwareId) {
            abfrage('INSERT INTO kurs_software (kurs_id, software_id) VALUES (?, ?)', array($kursId, $softwareId));
        }

        if ($istAdmin) {
            abfrage('DELETE FROM kurs_eigentuemer WHERE kurs_id = ?', array($kursId));
            foreach ($eingabe['eigentuemer'] as $eigentuemerId) {
                abfrage('INSERT INTO kurs_eigentuemer (kurs_id, benutzer_id) VALUES (?, ?)', array($kursId, $eigentuemerId));
            }
        }

        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return array('Der Kurs konnte nicht gespeichert werden. Bitte erneut versuchen.');
    }

    return true;
}

/**
 * Anzahl aller Buchungen eines Kurses (auch vergangene - der Fremdschluessel
 * buchung.kurs_id unterscheidet nicht).
 *
 * @param  int $kursId
 * @return int
 */
function kurs_anzahl_buchungen($kursId)
{
    return (int) abfrage('SELECT COUNT(*) FROM buchung WHERE kurs_id = ?', array((int) $kursId))->fetchColumn();
}

/**
 * Loescht einen Kurs. kurs_software und kurs_eigentuemer verschwinden per
 * ON DELETE CASCADE im selben Befehl mit. Haengen noch Buchungen am Kurs,
 * lehnt die Datenbank das Loeschen ab (RESTRICT) - auch dann, wenn die
 * Buchung erst nach der Pruefung in kurs_loeschen entstanden ist.
 *
 * @param  int $kursId
 * @return true|string true bei Erfolg, sonst eine deutsche Fehlermeldung
 */
function kurs_loeschen($kursId)
{
    try {
        abfrage('DELETE FROM kurs WHERE id = ?', array((int) $kursId));
    } catch (PDOException $e) {
        return 'Der Kurs kann nicht gelöscht werden, solange noch Buchungen dafür bestehen.';
    }
    return true;
}
