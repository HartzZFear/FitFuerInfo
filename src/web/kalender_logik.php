<?php
/**
 * Gemeinsame Funktionen fuer den Belegungskalender (pages/belegung.php).
 *
 * Der Kalender kennt drei Ansichten:
 *   monat - Raster Mo-Fr, pro Tag eine kompakte Liste der Buchungen
 *   woche - ein Raum, Spalten Mo-Fr, Zeilen in Halbstunden-Slots
 *   tag   - ein Tag, Spalten = Raeume, Zeilen in Halbstunden-Slots
 *
 * Hier steht nur die Logik: Parameter pruefen, Zeitraum berechnen, Buchungen
 * laden und auf die Slots verteilen, Blaettern. Die Seite selbst gibt nur
 * noch aus.
 *
 * Alle Buchungen des sichtbaren Zeitraums kommen aus EINER Abfrage
 * (kalender_buchungen_laden()), nicht aus einer Abfrage pro Tag oder Slot.
 *
 * Die Filter Kurs und "nur meine" wirken bewusst NICHT in der Abfrage,
 * sondern erst in kalender_sichtbar(): Ein Slot, der durch eine
 * ausgeblendete Buchung belegt ist, ist trotzdem nicht frei und darf keinen
 * "Buchen"-Link bekommen - sonst fuehrt der Klick garantiert in einen
 * Regel-2-Fehler.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/buchung_logik.php';

// Laenge eines Slots in Minuten. Passt zu den halben Stunden aus Regel 1.
define('KALENDER_SLOT_MINUTEN', 30);

// Ab so vielen Eintraegen pro Tag kuerzt die Monatsansicht auf
// (KALENDER_MONAT_MAX - 1) Eintraege plus "+N weitere".
define('KALENDER_MONAT_MAX', 4);

// Anzahl der Kursfarben. Die Farben selbst stehen als CSS-Klassen
// .farbe-0 bis .farbe-7 in belegung.php.
define('KALENDER_FARBEN', 8);

/**
 * Die erlaubten Ansichten. Der erste Eintrag ist NICHT der Standard - der
 * steht in kalender_parameter().
 *
 * @return array Schluessel = GET-Wert, Wert = Beschriftung
 */
function kalender_ansichten()
{
    return array(
        'monat' => 'Monat',
        'woche' => 'Woche',
        'tag'   => 'Tag',
    );
}

/**
 * Deutscher Monatsname zu format('n') (1 = Januar).
 *
 * @param  int $nummer
 * @return string
 */
function kalender_monat_name($nummer)
{
    $namen = array(
        1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
        5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
    );

    return isset($namen[$nummer]) ? $namen[$nummer] : '';
}

/**
 * Kurzer Wochentagsname zu format('N') (1 = Montag).
 *
 * @param  int $nummer
 * @return string
 */
function kalender_wochentag_kurz($nummer)
{
    $namen = array(1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So');

    return isset($namen[$nummer]) ? $namen[$nummer] : '';
}

/**
 * Faellt das Datum auf ein Wochenende, wird es auf den folgenden Montag
 * geschoben. Werktage bleiben unveraendert.
 *
 * @param  DateTime $datum
 * @return DateTime neue Instanz
 */
function kalender_naechster_werktag($datum)
{
    $ergebnis = clone $datum;

    while ((int) $ergebnis->format('N') > 5) {
        $ergebnis->modify('+1 day');
    }

    return $ergebnis;
}

/**
 * Der Tag, auf den "Heute" springt: heute, am Wochenende der naechste Montag.
 *
 * @return DateTime
 */
function kalender_heute()
{
    return kalender_naechster_werktag(new DateTime('today'));
}

/**
 * Montag der Woche, in der das Datum liegt (ISO-Woche, Montag = Tag 1).
 *
 * @param  DateTime $datum
 * @return DateTime neue Instanz, 00:00 Uhr
 */
function kalender_wochenanfang($datum)
{
    $montag = clone $datum;
    $montag->setTime(0, 0, 0);
    $montag->modify('-' . ((int) $montag->format('N') - 1) . ' days');

    return $montag;
}

/**
 * Liest und prueft die GET-Parameter. Ungueltige Werte fallen STILL auf den
 * Standard zurueck - eine manipulierte URL soll keine Fehlermeldung
 * erzeugen, sondern einfach die Standardansicht zeigen.
 *
 * raum_id und kurs_id werden gegen die tatsaechlich vorhandenen IDs
 * geprueft, damit z. B. raum_id=999 nicht als "Filter auf einen leeren
 * Raum" durchrutscht.
 *
 * @param  array $get      in der Regel $_GET
 * @param  int[] $raumIds  alle vorhandenen Raum-IDs
 * @param  int[] $kursIds  alle vorhandenen Kurs-IDs
 * @return array ansicht, datum (DateTime), raum_id, kurs_id, nur_meine
 */
function kalender_parameter($get, $raumIds, $kursIds)
{
    $ansicht = isset($get['ansicht']) && is_string($get['ansicht']) ? $get['ansicht'] : '';
    if (!array_key_exists($ansicht, kalender_ansichten())) {
        $ansicht = 'woche';
    }

    // Datum: nur echtes YYYY-MM-DD. Der Rueckvergleich faengt Werte ab, die
    // PHP stillschweigend weiterrechnet (2026-02-30 -> 02.03.).
    $datum = null;
    if (isset($get['datum']) && is_string($get['datum'])) {
        $obj = DateTime::createFromFormat('!Y-m-d', $get['datum']);
        if ($obj && $obj->format('Y-m-d') === $get['datum']) {
            $datum = $obj;
        }
    }
    if ($datum === null) {
        $datum = kalender_heute();
    }

    // Die Tagesansicht zeigt nur Werktage - an einem Wochenende gibt es
    // keine Buchungen (Regel 1).
    if ($ansicht === 'tag') {
        $datum = kalender_naechster_werktag($datum);
    }

    $raumId = isset($get['raum_id']) && is_string($get['raum_id']) ? (int) $get['raum_id'] : 0;
    if (!in_array($raumId, $raumIds, true)) {
        $raumId = 0;
    }

    $kursId = isset($get['kurs_id']) && is_string($get['kurs_id']) ? (int) $get['kurs_id'] : 0;
    if (!in_array($kursId, $kursIds, true)) {
        $kursId = 0;
    }

    $nurMeine = isset($get['nur_meine']) && $get['nur_meine'] === '1';

    return array(
        'ansicht'   => $ansicht,
        'datum'     => $datum,
        'raum_id'   => $raumId,
        'kurs_id'   => $kursId,
        'nur_meine' => $nurMeine,
    );
}

/**
 * Baut die URL fuer belegung.php aus den aktuellen Parametern und den
 * gewuenschten Aenderungen. So bleiben die Filter beim Blaettern und beim
 * Umschalten der Ansicht automatisch erhalten.
 *
 * Leere Filter (0 / false) werden weggelassen, damit die URL kurz bleibt.
 *
 * @param  array $param       Ergebnis von kalender_parameter()
 * @param  array $aenderungen z. B. array('ansicht' => 'tag')
 * @return string z. B. 'belegung.php?ansicht=tag&datum=2026-09-07'
 */
function kalender_url($param, $aenderungen = array())
{
    foreach ($aenderungen as $schluessel => $wert) {
        $param[$schluessel] = $wert;
    }

    $query = array(
        'ansicht' => $param['ansicht'],
        'datum'   => $param['datum'] instanceof DateTime ? $param['datum']->format('Y-m-d') : $param['datum'],
    );
    if ((int) $param['raum_id'] > 0) {
        $query['raum_id'] = (int) $param['raum_id'];
    }
    if ((int) $param['kurs_id'] > 0) {
        $query['kurs_id'] = (int) $param['kurs_id'];
    }
    if ($param['nur_meine']) {
        $query['nur_meine'] = 1;
    }

    return 'belegung.php?' . http_build_query($query);
}

/**
 * Das Datum, auf das "zurueck" (-1) bzw. "weiter" (+1) springt.
 *
 *   monat - erster Tag des Vor-/Folgemonats
 *   woche - Montag der Vor-/Folgewoche
 *   tag   - voriger/naechster Werktag (Wochenenden werden uebersprungen)
 *
 * @param  string   $ansicht
 * @param  DateTime $datum
 * @param  int      $richtung -1 oder +1
 * @return DateTime
 */
function kalender_blaettern($ansicht, $datum, $richtung)
{
    $vorzeichen = $richtung < 0 ? '-' : '+';

    if ($ansicht === 'monat') {
        // Erst auf den 1. setzen, dann rechnen: aus dem 31.01. + 1 Monat
        // wuerde sonst der 03.03. statt Februar.
        $ziel = DateTime::createFromFormat('!Y-m-d', $datum->format('Y-m-01'));
        $ziel->modify($vorzeichen . '1 month');
        return $ziel;
    }

    if ($ansicht === 'woche') {
        $ziel = kalender_wochenanfang($datum);
        $ziel->modify($vorzeichen . '7 days');
        return $ziel;
    }

    // Tag: so lange weiterschieben, bis ein Werktag erreicht ist.
    $ziel = clone $datum;
    do {
        $ziel->modify($vorzeichen . '1 day');
    } while ((int) $ziel->format('N') > 5);

    return $ziel;
}

/**
 * Der sichtbare Zeitraum einer Ansicht.
 *
 *   tage - alle angezeigten Werktage als 'YYYY-MM-DD', in Reihenfolge
 *   von  - Beginn fuer die Abfrage, 'YYYY-MM-DD 00:00:00'
 *   bis  - Ende fuer die Abfrage (exklusiv), 'YYYY-MM-DD 00:00:00'
 *
 * Die Monatsansicht beginnt mit der ersten Woche, die einen Werktag des
 * Monats enthaelt, und endet mit der letzten. Tage aus dem Vor- und
 * Folgemonat in diesen Wochen werden mit angezeigt (gedimmt).
 *
 * @param  string   $ansicht
 * @param  DateTime $datum
 * @return array
 */
function kalender_zeitraum($ansicht, $datum)
{
    if ($ansicht === 'tag') {
        $erster = clone $datum;
        $letzter = clone $datum;
    } elseif ($ansicht === 'woche') {
        $erster = kalender_wochenanfang($datum);
        $letzter = clone $erster;
        $letzter->modify('+4 days');
    } else {
        $monatErster  = DateTime::createFromFormat('!Y-m-d', $datum->format('Y-m-01'));
        $monatLetzter = DateTime::createFromFormat('!Y-m-d', $datum->format('Y-m-t'));

        // Faellt der 1. auf ein Wochenende, gehoert die erste Rasterzeile
        // schon zur Folgewoche; faellt der letzte Tag auf ein Wochenende,
        // endet das Raster mit dem Freitag davor.
        $monatErster = kalender_naechster_werktag($monatErster);
        while ((int) $monatLetzter->format('N') > 5) {
            $monatLetzter->modify('-1 day');
        }

        $erster = kalender_wochenanfang($monatErster);
        $letzter = kalender_wochenanfang($monatLetzter);
        $letzter->modify('+4 days');
    }

    $tage = array();
    $tag = clone $erster;
    while ($tag <= $letzter) {
        if ((int) $tag->format('N') <= 5) {
            $tage[] = $tag->format('Y-m-d');
        }
        $tag->modify('+1 day');
    }

    $bis = clone $letzter;
    $bis->modify('+1 day');

    return array(
        'tage' => $tage,
        'von'  => $erster->format('Y-m-d') . ' 00:00:00',
        'bis'  => $bis->format('Y-m-d') . ' 00:00:00',
    );
}

/**
 * Ueberschrift ueber dem Kalender, z. B.
 *   monat - "September 2026"
 *   woche - "KW 37 / 2026 · 07.09.–11.09.2026"
 *   tag   - "Montag, 07.09.2026"
 *
 * Die Kalenderwoche kommt aus format('W') zusammen mit dem ISO-Jahr
 * format('o') - am Jahreswechsel gehoert z. B. der 31.12.2026 schon zur
 * KW 53 / 2026, der 01.01.2027 ebenfalls.
 *
 * @param  string   $ansicht
 * @param  DateTime $datum
 * @return string
 */
function kalender_titel($ansicht, $datum)
{
    if ($ansicht === 'monat') {
        return kalender_monat_name((int) $datum->format('n')) . ' ' . $datum->format('Y');
    }

    if ($ansicht === 'woche') {
        $montag = kalender_wochenanfang($datum);
        $freitag = clone $montag;
        $freitag->modify('+4 days');

        return 'KW ' . $montag->format('W') . ' / ' . $montag->format('o')
            . ' · ' . $montag->format('d.m.') . '–' . $freitag->format('d.m.Y');
    }

    return buchung_wochentag_name((int) $datum->format('N')) . ', ' . $datum->format('d.m.Y');
}

/**
 * Laedt ALLE Buchungen, die den Zeitraum beruehren, in einer Abfrage mit
 * Kurs, Raum und Bucher.
 *
 * Gefiltert wird hier nur nach Raum (wenn gesetzt). Kurs und "nur meine"
 * werden erst in kalender_sichtbar() ausgewertet, siehe Dateikopf.
 *
 * @param  string $von    'YYYY-MM-DD HH:MM:SS'
 * @param  string $bis    'YYYY-MM-DD HH:MM:SS' (exklusiv)
 * @param  int    $raumId 0 = alle Raeume
 * @return array Zeilen mit id, raum_id, kurs_id, benutzer_id, start, ende,
 *               raum, kurs, gebucht_von
 */
function kalender_buchungen_laden($von, $bis, $raumId)
{
    // Gleiche Ueberschneidungsbedingung wie bei Regel 2: die Buchung
    // beginnt vor dem Ende des Zeitraums und endet nach seinem Beginn.
    $sql = 'SELECT b.id, b.raum_id, b.kurs_id, b.benutzer_id, b.start, b.ende,
                   r.name AS raum, k.titel AS kurs, u.name AS gebucht_von
            FROM buchung b
            JOIN raum r     ON r.id = b.raum_id
            JOIN kurs k     ON k.id = b.kurs_id
            JOIN benutzer u ON u.id = b.benutzer_id
            WHERE b.start < ? AND b.ende > ?';
    $parameter = array($bis, $von);

    if ((int) $raumId > 0) {
        $sql .= ' AND b.raum_id = ?';
        $parameter[] = (int) $raumId;
    }

    $sql .= ' ORDER BY b.start, r.name';

    return abfrage($sql, $parameter)->fetchAll();
}

/**
 * Passt die Buchung zu den Filtern Kurs und "nur meine"?
 *
 * @param  array $buchung   Zeile aus kalender_buchungen_laden()
 * @param  array $param     Ergebnis von kalender_parameter()
 * @param  int   $benutzerId
 * @return bool
 */
function kalender_sichtbar($buchung, $param, $benutzerId)
{
    if ($param['kurs_id'] > 0 && (int) $buchung['kurs_id'] !== $param['kurs_id']) {
        return false;
    }
    if ($param['nur_meine'] && (int) $buchung['benutzer_id'] !== (int) $benutzerId) {
        return false;
    }

    return true;
}

/**
 * Die Zeilen der Wochen- und Tagesansicht: Startzeiten der Slots von 07:00
 * bis 19:30. Das ist zeitslots() ohne den letzten Eintrag, denn um 20:00
 * endet der Tag - da beginnt kein Slot mehr.
 *
 * @return string[] array('07:00', '07:30', ..., '19:30')
 */
function kalender_slots()
{
    $slots = zeitslots();
    array_pop($slots);

    return $slots;
}

/**
 * Index des Slots, in dem eine Uhrzeit liegt (0 = 07:00), begrenzt auf den
 * sichtbaren Tag. Liegt die Zeit vor 07:00, gibt es 0, nach 20:00 die
 * Anzahl der Slots.
 *
 * @param  string $zeit 'HH:MM'
 * @return int
 */
function kalender_slot_index($zeit)
{
    $minuten      = (int) substr($zeit, 0, 2) * 60 + (int) substr($zeit, 3, 2);
    $startMinuten = (int) substr(BUCHUNG_TAG_START, 0, 2) * 60 + (int) substr(BUCHUNG_TAG_START, 3, 2);

    $index = (int) floor(($minuten - $startMinuten) / KALENDER_SLOT_MINUTEN);
    $anzahl = count(kalender_slots());

    return max(0, min($anzahl, $index));
}

/**
 * Verteilt die Buchungen auf ein Raster aus Spalten x Slots, wie es die
 * Wochen- und Tagesansicht als Tabelle mit rowspan braucht.
 *
 * Jede Zelle ist eines von:
 *   array('typ' => 'frei')
 *   array('typ' => 'start', 'buchung' => ..., 'slots' => n, 'sichtbar' => bool)
 *   array('typ' => 'belegt')   - von einem rowspan darueber verdeckt,
 *                                die Seite gibt hier KEIN <td> aus
 *
 * Ueberschneidungen im selben Raum kann es wegen Regel 2 nicht geben. Falls
 * doch (z. B. per Hand in die Datenbank geschrieben), gewinnt die frueher
 * beginnende Buchung, damit die Tabelle nicht auseinanderfaellt.
 *
 * @param  array    $buchungen   Zeilen aus kalender_buchungen_laden()
 * @param  string[] $spalten     Spaltenschluessel (Datum oder Raum-ID)
 * @param  string   $spaltenFeld 'datum' (Woche) oder 'raum_id' (Tag)
 * @param  array    $param       Ergebnis von kalender_parameter()
 * @param  int      $benutzerId
 * @return array [spalte][slotIndex] => Zelle
 */
function kalender_raster($buchungen, $spalten, $spaltenFeld, $param, $benutzerId)
{
    $anzahl = count(kalender_slots());

    $raster = array();
    foreach ($spalten as $spalte) {
        $raster[$spalte] = array_fill(0, $anzahl, array('typ' => 'frei'));
    }

    foreach ($buchungen as $buchung) {
        $spalte = $spaltenFeld === 'datum'
            ? substr($buchung['start'], 0, 10)
            : (string) (int) $buchung['raum_id'];

        if (!isset($raster[$spalte])) {
            continue;
        }

        $von = kalender_slot_index(substr($buchung['start'], 11, 5));
        $bis = kalender_slot_index(substr($buchung['ende'], 11, 5));

        if ($bis <= $von) {
            continue;
        }

        // Kollision (siehe oben): ist ein Slot schon vergeben, faellt die
        // spaetere Buchung aus dem Raster.
        $frei = true;
        for ($i = $von; $i < $bis; $i++) {
            if ($raster[$spalte][$i]['typ'] !== 'frei') {
                $frei = false;
                break;
            }
        }
        if (!$frei) {
            continue;
        }

        $raster[$spalte][$von] = array(
            'typ'      => 'start',
            'buchung'  => $buchung,
            'slots'    => $bis - $von,
            'sichtbar' => kalender_sichtbar($buchung, $param, $benutzerId),
        );
        for ($i = $von + 1; $i < $bis; $i++) {
            $raster[$spalte][$i] = array('typ' => 'belegt');
        }
    }

    return $raster;
}

/**
 * Gruppiert die sichtbaren Buchungen nach Tag, fuer die Monatsansicht.
 *
 * @param  array $buchungen Zeilen aus kalender_buchungen_laden()
 * @param  array $param     Ergebnis von kalender_parameter()
 * @param  int   $benutzerId
 * @return array 'YYYY-MM-DD' => Liste von Buchungen (nach Startzeit)
 */
function kalender_nach_tag($buchungen, $param, $benutzerId)
{
    $jeTag = array();

    foreach ($buchungen as $buchung) {
        if (!kalender_sichtbar($buchung, $param, $benutzerId)) {
            continue;
        }
        $jeTag[substr($buchung['start'], 0, 10)][] = $buchung;
    }

    return $jeTag;
}

/**
 * Feste Farbe pro Kurs: Index in die kleine Palette (CSS-Klasse
 * .farbe-N in belegung.php). Haengt nur an der kurs_id, damit derselbe
 * Kurs in jeder Ansicht und auf jeder Seite dieselbe Farbe hat.
 *
 * @param  int $kursId
 * @return int 0 .. KALENDER_FARBEN - 1
 */
function kalender_kursfarbe($kursId)
{
    return ((int) $kursId) % KALENDER_FARBEN;
}

/**
 * Darf in diesem Slot ein "Buchen"-Link erscheinen? Nur wenn der Benutzer
 * ueberhaupt einen Kurs buchen darf und der Slot nicht schon begonnen hat.
 * Die eigentliche Pruefung aller Regeln macht buchung_pruefen() beim
 * Speichern - das hier blendet nur sinnlose Links aus.
 *
 * @param  string $datum 'YYYY-MM-DD'
 * @param  string $zeit  'HH:MM'
 * @param  bool   $darfBuchen
 * @return bool
 */
function kalender_slot_buchbar($datum, $zeit, $darfBuchen)
{
    if (!$darfBuchen) {
        return false;
    }

    return !buchung_ist_vergangen($datum . ' ' . $zeit . ':00');
}
