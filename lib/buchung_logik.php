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

/**
 * Eine Buchung laden (alle Spalten).
 *
 * @param  int $buchungId
 * @return array|false Zeile aus buchung oder false, wenn es sie nicht gibt
 */
function buchung_laden($buchungId)
{
    return abfrage('SELECT * FROM buchung WHERE id = ?', array((int) $buchungId))->fetch();
}

/**
 * Eine Buchung mit Raum- und Kursnamen laden, fuer die Sicherheitsabfrage
 * vor dem Loeschen.
 *
 * @param  int $buchungId
 * @return array|false Zeile mit id, benutzer_id, start, ende, raum, kurs
 */
function buchung_mit_namen_laden($buchungId)
{
    return abfrage(
        'SELECT b.id, b.benutzer_id, b.start, b.ende, r.name AS raum, k.titel AS kurs
         FROM buchung b
         JOIN raum r ON r.id = b.raum_id
         JOIN kurs k ON k.id = b.kurs_id
         WHERE b.id = ?',
        array((int) $buchungId)
    )->fetch();
}

/**
 * Buchungsliste mit den Filtern der Seite buchungen. Die Filter landen
 * ausschliesslich als Parameter im Prepared Statement.
 *
 * @param  array $filter     raum_id (0 = alle), kurs_id (0 = alle),
 *                           ab ('YYYY-MM-DD'), nur_meine (bool)
 * @param  int   $benutzerId fuer "nur meine"
 * @return array Zeilen mit id, benutzer_id, start, ende, raum, kurs, gebucht_von
 */
function buchung_liste($filter, $benutzerId)
{
    $bedingungen = array('b.start >= ?');
    $parameter   = array($filter['ab'] . ' 00:00:00');

    if ($filter['raum_id'] > 0) {
        $bedingungen[] = 'b.raum_id = ?';
        $parameter[]   = $filter['raum_id'];
    }
    if ($filter['kurs_id'] > 0) {
        $bedingungen[] = 'b.kurs_id = ?';
        $parameter[]   = $filter['kurs_id'];
    }
    if ($filter['nur_meine']) {
        $bedingungen[] = 'b.benutzer_id = ?';
        $parameter[]   = $benutzerId;
    }

    return abfrage(
        'SELECT b.id, b.benutzer_id, b.start, b.ende,
                r.name AS raum, k.titel AS kurs, u.name AS gebucht_von
         FROM buchung b
         JOIN raum r     ON r.id = b.raum_id
         JOIN kurs k     ON k.id = b.kurs_id
         JOIN benutzer u ON u.id = b.benutzer_id
         WHERE ' . implode(' AND ', $bedingungen) . '
         ORDER BY b.start, r.name',
        $parameter
    )->fetchAll();
}

/**
 * Link "Kalenderansicht" der Buchungsliste: dieselben Filter, der Kalender
 * startet beim "ab"-Datum (Standard: Wochenansicht). Gegenstueck zu
 * kalender_listen_url().
 *
 * @param  array $filter wie bei buchung_liste()
 * @return string url('belegung', ...)
 */
function buchung_kalender_url($filter)
{
    $parameter = array('datum' => $filter['ab']);
    if ($filter['raum_id'] > 0) {
        $parameter['raum_id'] = $filter['raum_id'];
    }
    if ($filter['kurs_id'] > 0) {
        $parameter['kurs_id'] = $filter['kurs_id'];
    }
    if ($filter['nur_meine']) {
        $parameter['nur_meine'] = 1;
    }
    return url('belegung', $parameter);
}

/**
 * Nur fuer die Anzeige der Buttons: gehoert die Buchung dem eingeloggten
 * Benutzer? Die Daten liegen durch den JOIN schon vor, deshalb hier ohne
 * eigene Abfrage. Die massgebliche Pruefung steht serverseitig in
 * buchung_darf_verwalten() und laeuft beim Aufruf von buchung_bearbeiten
 * bzw. buchung_loeschen erneut.
 *
 * @param  array $buchung    Zeile aus buchung_liste()
 * @param  int   $benutzerId
 * @param  bool  $istAdmin
 * @return bool
 */
function buchung_ist_eigene($buchung, $benutzerId, $istAdmin)
{
    if ($istAdmin) {
        return true;
    }
    return (int) $buchung['benutzer_id'] === (int) $benutzerId;
}

/**
 * Vorschlag fuer die Endzeit einer neuen Buchung: eine Stunde nach dem
 * Start, aber nie nach BUCHUNG_TAG_ENDE. Der Benutzer kann sie danach frei
 * aendern.
 *
 * @param  string   $start 'HH:MM'
 * @param  string[] $slots Ergebnis von zeitslots()
 * @return string 'HH:MM'
 */
function buchung_ende_vorschlag($start, $slots)
{
    $startPos = array_search($start, $slots, true);
    if ($startPos !== false && isset($slots[$startPos + 2])) {
        return $slots[$startPos + 2];
    }
    return BUCHUNG_TAG_ENDE;
}

/**
 * Prueft die Eingaben und alle fuenf Buchungsregeln und legt die Buchung an
 * bzw. speichert sie.
 *
 * Ob der Benutzer fuer den Kurs buchen und die Buchung bearbeiten darf,
 * prueft die Seite vorher (kurs_darf_verwalten(), buchung_darf_verwalten()).
 *
 * @param  int|null $buchungId  null = neue Buchung
 * @param  array    $eingabe    kurs_id, raum_id (int), datum ('YYYY-MM-DD'),
 *                              start, ende ('HH:MM')
 * @param  int      $benutzerId wird beim Anlegen als Bucher eingetragen
 * @return true|array true bei Erfolg, sonst alle deutschen Fehlermeldungen
 */
function buchung_speichern($buchungId, $eingabe, $benutzerId)
{
    $fehler = array();
    $kursId = (int) $eingabe['kurs_id'];
    $raumId = (int) $eingabe['raum_id'];

    if ($kursId < 1) {
        $fehler[] = 'Bitte einen Kurs auswählen.';
    }
    if ($raumId < 1) {
        $fehler[] = 'Bitte einen Raum auswählen.';
    }

    $start = buchung_zeitpunkt($eingabe['datum'], $eingabe['start']);
    $ende  = buchung_zeitpunkt($eingabe['datum'], $eingabe['ende']);

    if ($start === null || $ende === null) {
        $fehler[] = 'Datum oder Uhrzeit sind ungültig. Bitte Datum, Start- und Endzeit erneut auswählen.';
    }

    if (!empty($fehler)) {
        return $fehler;
    }

    // Transaktion: verhindert Doppelbuchungen, wenn zwei Benutzer
    // gleichzeitig denselben Raum (oder denselben Kurs) zur selben Zeit
    // buchen. Ablauf: sperren -> pruefen -> schreiben.
    $db = db();
    try {
        $db->beginTransaction();

        // Erst raum, dann kurs sperren - immer in dieser Reihenfolge, damit
        // sich zwei gleichzeitige Buchungen nicht gegenseitig blockieren
        // (Deadlock). Die Sperre haelt bis zum COMMIT: eine zweite Anfrage
        // fuer denselben Raum wartet hier, bis die erste fertig ist, und
        // sieht deren Buchung dann bei der Pruefung.
        abfrage('SELECT id FROM raum WHERE id = ? FOR UPDATE', array($raumId));
        abfrage('SELECT id FROM kurs WHERE id = ? FOR UPDATE', array($kursId));

        // Beim Bearbeiten die eigene ID ausschliessen, sonst kollidiert die
        // Buchung bei den Regeln 2 und 3 mit sich selbst.
        $fehler = buchung_pruefen($raumId, $kursId, $start, $ende, $buchungId);

        if (!empty($fehler)) {
            $db->rollBack();
            return $fehler;
        }

        if ($buchungId !== null) {
            // benutzer_id bleibt unveraendert: die Buchung gehoert weiterhin
            // dem, der sie angelegt hat.
            abfrage(
                'UPDATE buchung SET raum_id = ?, kurs_id = ?, start = ?, ende = ? WHERE id = ?',
                array($raumId, $kursId, $start, $ende, $buchungId)
            );
        } else {
            abfrage(
                'INSERT INTO buchung (raum_id, kurs_id, benutzer_id, start, ende) VALUES (?, ?, ?, ?, ?)',
                array($raumId, $kursId, $benutzerId, $start, $ende)
            );
        }

        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return array('Die Buchung konnte nicht gespeichert werden. Bitte erneut versuchen.');
    }

    return true;
}

/**
 * Nach einer abgelehnten Buchung: Waren Software (Regel 4) oder Platz
 * (Regel 5) das Problem, ist ein anderer Raum die Loesung - dann liefert
 * die Funktion die Raeume, die zum Kurs passen. Sonst ein leeres Array.
 *
 * @param  int $kursId
 * @param  int $raumId
 * @return array Zeilen aus passende_raeume() oder leer
 */
function buchung_raum_vorschlaege($kursId, $raumId)
{
    if ($kursId < 1 || $raumId < 1) {
        return array();
    }

    $raum = abfrage('SELECT arbeitsplaetze FROM raum WHERE id = ?', array((int) $raumId))->fetch();
    $kurs = abfrage('SELECT max_teilnehmer FROM kurs WHERE id = ?', array((int) $kursId))->fetch();

    if (!$raum || !$kurs) {
        return array();
    }

    $softwareVerletzt = count(buchung_fehlende_software($kursId, $raumId)) > 0;
    $platzVerletzt    = (int) $kurs['max_teilnehmer'] > (int) $raum['arbeitsplaetze'];

    if ($softwareVerletzt || $platzVerletzt) {
        return passende_raeume($kursId);
    }
    return array();
}

/**
 * Loescht eine Buchung (ein einzelner Befehl, keine Transaktion noetig).
 *
 * @param  int $buchungId
 * @return true|string true bei Erfolg, sonst eine deutsche Fehlermeldung
 */
function buchung_loeschen($buchungId)
{
    try {
        abfrage('DELETE FROM buchung WHERE id = ?', array((int) $buchungId));
    } catch (PDOException $e) {
        return 'Die Buchung konnte nicht gelöscht werden. Bitte erneut versuchen.';
    }
    return true;
}

/**
 * Text fuer das title-Attribut eines Buchungsblocks im Kalender.
 *
 * @param  array $buchung Zeile aus kalender_buchungen_laden()
 * @return string z. B. "Linux-Grundlagen · 08:00–12:00 · Raum A · gebucht von lena"
 */
function buchung_tooltip($buchung)
{
    return $buchung['kurs'] . ' · ' . substr($buchung['start'], 11, 5) . '–' . substr($buchung['ende'], 11, 5)
        . ' · ' . $buchung['raum'] . ' · gebucht von ' . $buchung['gebucht_von'];
}
