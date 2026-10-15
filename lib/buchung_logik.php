<?php
/**
 * Gemeinsame Funktionen fuer das Buchungssystem.
 *
 * Eine Buchung belegt einen Raum fuer einen Kurs in einem Zeitraum. Sie ist
 * nur gueltig, wenn ALLE fuenf Regeln erfuellt sind (siehe CONTRIBUTE.md,
 * Abschnitt "Buchungsregeln"):
 *   1. Zeit     - Mo-Fr, 07:00-20:00, halbe Stunden, ein Tag, Ende > Start,
 *                 Start nicht in der Vergangenheit
 *   2. Raum     - der Raum ist im Zeitraum nicht anderweitig belegt
 *   3. Kurs     - der Kurs laeuft im Zeitraum nicht schon woanders
 *   4. Software - der Raum hat jedes Softwarepaket, das der Kurs braucht
 *   5. Platz    - der Raum hat genug Arbeitsplaetze fuer den Kurs
 *
 * buchung_pruefen() bricht bewusst NICHT beim ersten Fehler ab, sondern
 * sammelt alle Verstoesse ein. Sonst muesste der Benutzer das Formular
 * mehrmals hintereinander abschicken, um alle Probleme zu finden.
 *
 * Die Rechtepruefung gehoert - genau wie bei den Kursen - IMMER
 * serverseitig hierher, nicht nur in das Ein-/Ausblenden der Buttons.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/kurs_rechte.php';

// Rahmen fuer alle Buchungen. Als Konstanten, damit die Pruefung in
// buchung_zeit_pruefen() und die Dropdowns aus zeitslots() garantiert
// dieselben Grenzen verwenden.
define('BUCHUNG_TAG_START', '07:00');
define('BUCHUNG_TAG_ENDE',  '20:00');

/**
 * Alle waehlbaren Uhrzeiten in Halbstundenschritten, von 07:00 bis 20:00.
 * Wird fuer die Start- und Endzeit-Dropdowns verwendet.
 *
 * @return string[] z. B. array('07:00', '07:30', ..., '20:00')
 */
function zeitslots()
{
    $slots = array();

    $zeit    = DateTime::createFromFormat('H:i', BUCHUNG_TAG_START);
    $ende    = DateTime::createFromFormat('H:i', BUCHUNG_TAG_ENDE);
    $schritt = new DateInterval('PT30M');

    while ($zeit <= $ende) {
        $slots[] = $zeit->format('H:i');
        $zeit->add($schritt);
    }

    return $slots;
}

/**
 * Wandelt Datum + Uhrzeit aus dem Formular in einen DATETIME-String um.
 * Liefert null, wenn die Bestandteile kein gueltiges Datum ergeben
 * (z. B. "2026-02-30" oder ein manipuliertes Feld).
 *
 * @param  string $datum 'YYYY-MM-DD'
 * @param  string $zeit  'HH:MM'
 * @return string|null   'YYYY-MM-DD HH:MM:SS'
 */
function buchung_zeitpunkt($datum, $zeit)
{
    $roh = $datum . ' ' . $zeit . ':00';
    $obj = DateTime::createFromFormat('Y-m-d H:i:s', $roh);

    // Der Rueckvergleich faengt Werte ab, die PHP stillschweigend
    // weiterrechnet (aus dem 30. Februar wuerde sonst der 2. Maerz).
    if (!$obj || $obj->format('Y-m-d H:i:s') !== $roh) {
        return null;
    }

    return $roh;
}

/**
 * Formuliert einen Zeitraum so, wie er in den Fehlermeldungen steht:
 * "am 08.09.2026 von 08:00 bis 12:00".
 *
 * @param  string $start 'YYYY-MM-DD HH:MM:SS'
 * @param  string $ende  'YYYY-MM-DD HH:MM:SS'
 * @return string
 */
function buchung_zeitraum_text($start, $ende)
{
    $startObj = new DateTime($start);
    $endeObj  = new DateTime($ende);

    return 'am ' . $startObj->format('d.m.Y')
        . ' von ' . $startObj->format('H:i')
        . ' bis ' . $endeObj->format('H:i');
}

/**
 * Deutscher Wochentagsname zu format('N') (1 = Montag).
 *
 * @param  int $nummer
 * @return string
 */
function buchung_wochentag_name($nummer)
{
    $namen = array(
        1 => 'Montag',
        2 => 'Dienstag',
        3 => 'Mittwoch',
        4 => 'Donnerstag',
        5 => 'Freitag',
        6 => 'Samstag',
        7 => 'Sonntag',
    );

    return isset($namen[$nummer]) ? $namen[$nummer] : '';
}

/**
 * Liegt die Uhrzeit auf einer vollen oder halben Stunde (Sekunden 00)?
 *
 * @param  DateTime $zeitpunkt
 * @return bool
 */
function buchung_ist_halbe_stunde($zeitpunkt)
{
    $minute  = (int) $zeitpunkt->format('i');
    $sekunde = (int) $zeitpunkt->format('s');

    return $sekunde === 0 && ($minute === 0 || $minute === 30);
}

/**
 * Regel 1: Pruefung des Zeitraums allein, ohne Datenbankzugriff.
 *
 * @param  string $start 'YYYY-MM-DD HH:MM:SS'
 * @param  string $ende  'YYYY-MM-DD HH:MM:SS'
 * @return array leer bei OK, sonst deutsche Fehlermeldungen
 */
function buchung_zeit_pruefen($start, $ende)
{
    $fehler = array();

    $startObj = DateTime::createFromFormat('Y-m-d H:i:s', $start);
    $endeObj  = DateTime::createFromFormat('Y-m-d H:i:s', $ende);

    if (!$startObj || $startObj->format('Y-m-d H:i:s') !== $start
        || !$endeObj || $endeObj->format('Y-m-d H:i:s') !== $ende) {
        $fehler[] = 'Datum oder Uhrzeit sind ungültig. Bitte Datum, Start- und Endzeit erneut auswählen.';
        return $fehler;
    }

    // Wochentag: format('N') liefert 1 (Montag) bis 7 (Sonntag).
    if ((int) $startObj->format('N') > 5) {
        $fehler[] = 'Buchungen sind nur von Montag bis Freitag möglich. Der '
            . $startObj->format('d.m.Y') . ' ist ein '
            . buchung_wochentag_name((int) $startObj->format('N')) . '.';
    }

    // Beide Zeiten am selben Tag. Wird vor den Rahmenzeiten geprueft, weil
    // die uebrigen Meldungen sonst irrefuehrend waeren.
    if ($startObj->format('Y-m-d') !== $endeObj->format('Y-m-d')) {
        $fehler[] = 'Start und Ende müssen am selben Tag liegen. '
            . 'Für mehrere Tage bitte mehrere Buchungen anlegen.';
    }

    // Rahmenzeiten. Der Stringvergleich auf 'HH:MM' ist hier zulaessig,
    // weil beide Werte immer zweistellig und gleich lang sind.
    if ($startObj->format('H:i') < BUCHUNG_TAG_START) {
        $fehler[] = 'Die Startzeit darf nicht vor ' . BUCHUNG_TAG_START . ' Uhr liegen.';
    }
    if ($endeObj->format('H:i') > BUCHUNG_TAG_ENDE) {
        $fehler[] = 'Die Endzeit darf nicht nach ' . BUCHUNG_TAG_ENDE . ' Uhr liegen.';
    }

    // Halbe Stunden, keine Sekunden.
    if (!buchung_ist_halbe_stunde($startObj)) {
        $fehler[] = 'Die Startzeit muss auf einer vollen oder halben Stunde liegen (z. B. 08:00 oder 08:30).';
    }
    if (!buchung_ist_halbe_stunde($endeObj)) {
        $fehler[] = 'Die Endzeit muss auf einer vollen oder halben Stunde liegen (z. B. 12:00 oder 12:30).';
    }

    if ($endeObj <= $startObj) {
        $fehler[] = 'Die Endzeit muss nach der Startzeit liegen.';
    }

    $jetzt = new DateTime();
    if ($startObj < $jetzt) {
        $fehler[] = 'Der Buchungsbeginn liegt in der Vergangenheit. Bitte einen Termin in der Zukunft wählen.';
    }

    return $fehler;
}

/**
 * Prueft ALLE fuenf Buchungsregeln und sammelt jeden Verstoss ein.
 *
 * Beim Bearbeiten muss die eigene Buchungs-ID als $ignorierId uebergeben
 * werden, sonst kollidiert die Buchung bei den Regeln 2 und 3 mit sich
 * selbst.
 *
 * Diese Funktion schreibt nichts. Der Aufrufer muss sie innerhalb der
 * Transaktion aufrufen, in der er anschliessend speichert, und vorher die
 * Zeilen von raum und kurs mit SELECT ... FOR UPDATE sperren - sonst
 * koennen zwei gleichzeitige Buchungen beide die Pruefung bestehen.
 *
 * @param  int      $raumId
 * @param  int      $kursId
 * @param  string   $start      'YYYY-MM-DD HH:MM:SS'
 * @param  string   $ende       'YYYY-MM-DD HH:MM:SS'
 * @param  int|null $ignorierId eigene Buchungs-ID beim Bearbeiten
 * @return array leer = gueltig, sonst alle deutschen Fehlermeldungen
 */
function buchung_pruefen($raumId, $kursId, $start, $ende, $ignorierId = null)
{
    $raumId = (int) $raumId;
    $kursId = (int) $kursId;

    $fehler = array();

    // --- Regel 1: Zeit -------------------------------------------------
    $zeitFehler = buchung_zeit_pruefen($start, $ende);
    foreach ($zeitFehler as $meldung) {
        $fehler[] = $meldung;
    }

    $raum = abfrage('SELECT id, name, arbeitsplaetze FROM raum WHERE id = ?', array($raumId))->fetch();
    $kurs = abfrage('SELECT id, titel, max_teilnehmer FROM kurs WHERE id = ?', array($kursId))->fetch();

    if (!$raum) {
        $fehler[] = 'Der ausgewählte Raum existiert nicht.';
    }
    if (!$kurs) {
        $fehler[] = 'Der ausgewählte Kurs existiert nicht.';
    }

    // Ohne gueltige Zeit oder ohne Raum/Kurs sind die Regeln 2 bis 5 nicht
    // sinnvoll pruefbar - dann wuerden nur Folgefehler entstehen.
    if (!empty($zeitFehler) || !$raum || !$kurs) {
        return $fehler;
    }

    // --- Regel 2: Raum frei --------------------------------------------
    // Zwei Zeitraeume ueberschneiden sich genau dann, wenn jeder von beiden
    // beginnt, bevor der andere endet.
    $sql = 'SELECT b.start, b.ende, k.titel
            FROM buchung b
            JOIN kurs k ON k.id = b.kurs_id
            WHERE b.raum_id = ? AND b.start < ? AND b.ende > ?';
    $parameter = array($raumId, $ende, $start);

    if ($ignorierId !== null) {
        $sql .= ' AND b.id <> ?';
        $parameter[] = (int) $ignorierId;
    }
    $sql .= ' ORDER BY b.start';

    $kollisionen = abfrage($sql, $parameter)->fetchAll();
    foreach ($kollisionen as $zeile) {
        $fehler[] = $raum['name'] . ' ist ' . buchung_zeitraum_text($zeile['start'], $zeile['ende'])
            . ' bereits durch "' . $zeile['titel'] . '" belegt.';
    }

    // --- Regel 3: Kurs frei --------------------------------------------
    // Gleiche Bedingung, aber ueber den Kurs - ein Kurs kann nicht
    // gleichzeitig in zwei Raeumen stattfinden.
    $sql = 'SELECT b.start, b.ende, r.name
            FROM buchung b
            JOIN raum r ON r.id = b.raum_id
            WHERE b.kurs_id = ? AND b.start < ? AND b.ende > ?';
    $parameter = array($kursId, $ende, $start);

    if ($ignorierId !== null) {
        $sql .= ' AND b.id <> ?';
        $parameter[] = (int) $ignorierId;
    }
    $sql .= ' ORDER BY b.start';

    $kollisionen = abfrage($sql, $parameter)->fetchAll();
    foreach ($kollisionen as $zeile) {
        $fehler[] = 'Der Kurs "' . $kurs['titel'] . '" ist '
            . buchung_zeitraum_text($zeile['start'], $zeile['ende'])
            . ' bereits in ' . $zeile['name'] . ' gebucht.';
    }

    // --- Regel 4: Software ---------------------------------------------
    $fehlende = buchung_fehlende_software($kursId, $raumId);
    if (!empty($fehlende)) {
        $fehler[] = 'In ' . $raum['name'] . ' fehlt die Software: ' . implode(', ', $fehlende) . '.';
    }

    // --- Regel 5: Platz ------------------------------------------------
    if ((int) $kurs['max_teilnehmer'] > (int) $raum['arbeitsplaetze']) {
        $fehler[] = 'Der Kurs hat ' . (int) $kurs['max_teilnehmer'] . ' Teilnehmer, '
            . $raum['name'] . ' hat nur ' . (int) $raum['arbeitsplaetze'] . ' Arbeitsplätze.';
    }

    return $fehler;
}

/**
 * Namen der Softwarepakete, die der Kurs braucht und der Raum nicht hat.
 * Leeres Array = Regel 4 erfuellt.
 *
 * @param  int $kursId
 * @param  int $raumId
 * @return string[]
 */
function buchung_fehlende_software($kursId, $raumId)
{
    $zeilen = abfrage(
        'SELECT s.name
         FROM kurs_software ks
         JOIN software s ON s.id = ks.software_id
         WHERE ks.kurs_id = ?
           AND ks.software_id NOT IN (
             SELECT rs.software_id FROM raum_software rs WHERE rs.raum_id = ?
           )
         ORDER BY s.name',
        array((int) $kursId, (int) $raumId)
    )->fetchAll();

    $namen = array();
    foreach ($zeilen as $zeile) {
        $namen[] = $zeile['name'];
    }

    return $namen;
}

/**
 * Alle Raeume, die Software (Regel 4) und Platz (Regel 5) fuer den Kurs
 * erfuellen - unabhaengig davon, ob sie zu einem bestimmten Zeitpunkt frei
 * sind. Dient nur als Hinweis im Formular.
 *
 * @param  int $kursId
 * @return array Zeilen aus raum (id, name, arbeitsplaetze)
 */
function passende_raeume($kursId)
{
    return abfrage(
        'SELECT r.id, r.name, r.arbeitsplaetze
         FROM raum r
         JOIN kurs k ON k.id = ?
         WHERE r.arbeitsplaetze >= k.max_teilnehmer
           AND NOT EXISTS (
             SELECT 1 FROM kurs_software ks
             WHERE ks.kurs_id = k.id
               AND ks.software_id NOT IN (
                 SELECT rs.software_id FROM raum_software rs WHERE rs.raum_id = r.id
               )
           )
         ORDER BY r.name',
        array((int) $kursId)
    )->fetchAll();
}

/**
 * Darf der Benutzer diese Buchung bearbeiten oder loeschen?
 *
 * Nur wer die Buchung angelegt hat, und der Admin. Wer den Raum verwalten
 * darf (raum_bearbeiter), hat hier BEWUSST keine Sonderrechte - sonst
 * koennte er fremde Kurstermine umbuchen.
 *
 * Die zusaetzlichen Regeln fuer vergangene Buchungen stehen in
 * buchung_darf_bearbeiten() und buchung_darf_loeschen().
 *
 * @param  int  $buchungId
 * @param  int  $benutzerId
 * @param  bool $istAdmin
 * @return bool
 */
function buchung_darf_verwalten($buchungId, $benutzerId, $istAdmin)
{
    if ($istAdmin) {
        return true;
    }

    $besitzer = abfrage(
        'SELECT benutzer_id FROM buchung WHERE id = ?',
        array((int) $buchungId)
    )->fetchColumn();

    if ($besitzer === false) {
        return false;
    }

    return (int) $besitzer === (int) $benutzerId;
}

/**
 * Liegt der Beginn der Buchung in der Vergangenheit?
 *
 * @param  string $start 'YYYY-MM-DD HH:MM:SS'
 * @return bool
 */
function buchung_ist_vergangen($start)
{
    $startObj = new DateTime($start);
    $jetzt    = new DateTime();

    return $startObj < $jetzt;
}

/**
 * Bearbeiten: Rechte am Datensatz UND der Termin darf nicht vorbei sein.
 * Vergangene Buchungen gehoeren zur Historie und bleiben unveraendert -
 * auch fuer den Admin.
 *
 * @param  array $buchung Zeile aus buchung (mindestens id und start)
 * @param  int   $benutzerId
 * @param  bool  $istAdmin
 * @return bool
 */
function buchung_darf_bearbeiten($buchung, $benutzerId, $istAdmin)
{
    if (buchung_ist_vergangen($buchung['start'])) {
        return false;
    }

    return buchung_darf_verwalten($buchung['id'], $benutzerId, $istAdmin);
}

/**
 * Loeschen: Rechte am Datensatz, bei bereits begonnenen Buchungen
 * zusaetzlich Adminrecht (zum Aufraeumen der Historie).
 *
 * @param  array $buchung Zeile aus buchung (mindestens id und start)
 * @param  int   $benutzerId
 * @param  bool  $istAdmin
 * @return bool
 */
function buchung_darf_loeschen($buchung, $benutzerId, $istAdmin)
{
    if (buchung_ist_vergangen($buchung['start']) && !$istAdmin) {
        return false;
    }

    return buchung_darf_verwalten($buchung['id'], $benutzerId, $istAdmin);
}

/**
 * Kurse, fuer die der Benutzer Buchungen anlegen darf: der Admin alle, ein
 * Mitarbeiter nur seine eigenen (Ersteller oder kurs_eigentuemer).
 *
 * Die Bedingung entspricht kurs_darf_verwalten(), ist hier aber als eine
 * einzige Abfrage formuliert, damit die Liste nicht pro Kurs nachfragen
 * muss. Beim Speichern wird zusaetzlich kurs_darf_verwalten() aufgerufen -
 * das ist die massgebliche Pruefung.
 *
 * @param  int  $benutzerId
 * @param  bool $istAdmin
 * @return array Zeilen aus kurs (id, titel, max_teilnehmer)
 */
function buchbare_kurse($benutzerId, $istAdmin)
{
    if ($istAdmin) {
        return abfrage('SELECT id, titel, max_teilnehmer FROM kurs ORDER BY titel')->fetchAll();
    }

    return abfrage(
        'SELECT DISTINCT k.id, k.titel, k.max_teilnehmer
         FROM kurs k
         LEFT JOIN kurs_eigentuemer ke ON ke.kurs_id = k.id
         WHERE k.ersteller_id = ? OR ke.benutzer_id = ?
         ORDER BY k.titel',
        array((int) $benutzerId, (int) $benutzerId)
    )->fetchAll();
}
