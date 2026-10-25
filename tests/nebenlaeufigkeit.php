<?php
/**
 * Nebenlaeufigkeitstest: Zwei Benutzer buchen GLEICHZEITIG denselben Raum
 * zur selben Zeit. Genau eine der beiden Buchungen darf gespeichert werden.
 *
 * Aufruf (Apache und MySQL muessen laufen), im Projektordner:
 *
 *     C:\xampp\php\php.exe tests\nebenlaeufigkeit.php
 *
 * Nur auf der Kommandozeile: Im Browser aufgerufen tut die Datei nichts.
 *
 * Ablauf
 *   1. Zwei getrennte Sitzungen melden sich als admin / test1 an. Getrennt
 *      deshalb, weil PHP Anfragen derselben Sitzung nacheinander abarbeitet
 *      (Sperre auf der Session-Datei) - dann gaebe es gar keinen Wettlauf.
 *   2. Gesucht wird ein Raum, in den zwei verschiedene Kurse passen
 *      (Software und Plaetze), und ein freier Tag weit in der Zukunft.
 *   3. 10 Runden: In jeder Runde schicken beide Sitzungen per curl_multi
 *      gleichzeitig eine Buchung fuer denselben Raum und dieselbe halbe
 *      Stunde ab (Sitzung A Kurs 1, Sitzung B Kurs 2).
 *   4. Je Runde wird in der Datenbank gezaehlt: genau 1 Buchung = OK,
 *      2 Buchungen = Doppelbuchung.
 *   5. Am Ende werden die Testbuchungen wieder geloescht.
 *
 * Warum es funktioniert: buchung_speichern() in lib/buchung_logik.php sperrt
 * in einer Transaktion zuerst die Zeile des Raums (SELECT ... FOR UPDATE),
 * prueft dann auf Ueberschneidung und schreibt erst danach. Die zweite
 * Anfrage wartet an der Sperre, bis die erste fertig ist, und sieht deren
 * Buchung bei der Pruefung.
 *
 * Vorfuehrung im Fachgespraech ohne Sperre
 *   - In lib/buchung_logik.php in buchung_speichern() die beiden Zeilen
 *     mit "FOR UPDATE" vorruebergehend auskommentieren und den Test starten.
 *   - Dann KANN eine Doppelbuchung entstehen - aber nicht bei jedem Lauf:
 *     Es ist eine Race Condition, sie tritt nur auf, wenn beide Anfragen
 *     genau gleichzeitig zwischen "pruefen" und "schreiben" stehen. Das
 *     haengt vom Zufall der Zeitablaeufe ab. Deshalb laeuft der Test 10
 *     Runden; notfalls mehrmals starten.
 *   - Nach der Vorfuehrung die beiden FOR-UPDATE-Zeilen UNBEDINGT wieder
 *     einkommentieren (git diff lib/buchung_logik.php muss leer sein).
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/funktionen.php';
require_once __DIR__ . '/../lib/buchung_logik.php';

const RUNDEN = 10;

$basis = 'http://localhost' . BASE_URL . '/index.php?seite=';

/**
 * Baut eine curl-Anfrage (noch nicht abgeschickt).
 *
 * @param  string     $url
 * @param  array|null $post   Formularfelder, null = GET
 * @param  string     $cookie z. B. 'PHPSESSID=abc'
 * @return resource curl-Handle
 */
function test_anfrage($url, $post, $cookie)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    if ($cookie !== '') {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    return $ch;
}

/**
 * Schickt eine einzelne Anfrage ab.
 *
 * @return array status, antwort (Header + HTML)
 */
function test_senden($url, $post, $cookie)
{
    $ch      = test_anfrage($url, $post, $cookie);
    $antwort = curl_exec($ch);
    $status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('status' => $status, 'antwort' => (string) $antwort);
}

/**
 * Meldet eine neue Sitzung an.
 *
 * @return string Cookie der angemeldeten Sitzung, z. B. 'PHPSESSID=abc'
 */
function test_anmelden($basis, $name, $passwort)
{
    $seite  = test_senden($basis . 'login', null, '');
    $cookie = test_cookie($seite['antwort']);
    $login  = test_senden($basis . 'login', array(
        'csrf'     => test_token($seite['antwort']),
        'username' => $name,
        'password' => $passwort,
    ), $cookie);

    // Nach dem Login vergibt die Anwendung eine neue Session-ID.
    $neu = test_cookie($login['antwort']);
    if ($login['status'] !== 302 || $neu === '') {
        fwrite(STDERR, "Anmeldung als $name fehlgeschlagen.\n");
        exit(1);
    }
    return $neu;
}

/** Liest die Session-ID aus der Antwort (Set-Cookie). */
function test_cookie($antwort)
{
    return preg_match('/Set-Cookie: (PHPSESSID=[^;]+)/i', $antwort, $treffer) ? $treffer[1] : '';
}

/** Liest das CSRF-Token aus dem Formular. */
function test_token($antwort)
{
    return preg_match('/name="csrf" value="([0-9a-f]+)"/', $antwort, $treffer) ? $treffer[1] : '';
}

// --- 1. Zwei getrennte Sitzungen -------------------------------------------
$sitzungen = array();
foreach (array('A', 'B') as $name) {
    $cookie = test_anmelden($basis, 'admin', 'test1');
    $form   = test_senden($basis . 'buchung_bearbeiten', null, $cookie);
    $sitzungen[$name] = array('cookie' => $cookie, 'csrf' => test_token($form['antwort']));
}

// --- 2. Raum mit zwei passenden Kursen, freier Tag -------------------------
$raumId = 0;
$kurse  = array();
$jeRaum = array();
foreach (abfrage('SELECT id FROM kurs ORDER BY id')->fetchAll() as $kurs) {
    foreach (passende_raeume($kurs['id']) as $raum) {
        $jeRaum[$raum['id']][] = (int) $kurs['id'];
    }
}
foreach ($jeRaum as $id => $kursIds) {
    if (count($kursIds) >= 2) {
        $raumId = (int) $id;
        $kurse  = array_slice($kursIds, 0, 2);
        break;
    }
}
if ($raumId === 0) {
    fwrite(STDERR, "Kein Raum gefunden, in den zwei Kurse passen.\n");
    exit(1);
}

// Ein Werktag in etwa 10 Wochen, an dem Raum und beide Kurse ganztags frei sind.
$tag = new DateTime('+70 days');
while (true) {
    $datum = $tag->format('Y-m-d');
    $frei  = (int) $tag->format('N') <= 5;
    foreach ($kurse as $kursId) {
        if ($frei && buchung_pruefen($raumId, $kursId, $datum . ' 07:00:00', $datum . ' 20:00:00') !== array()) {
            $frei = false;
        }
    }
    if ($frei) {
        break;
    }
    $tag->modify('+1 day');
}

$hoechsteIdVorher = (int) abfrage('SELECT COALESCE(MAX(id), 0) FROM buchung')->fetchColumn();

echo 'Raum-ID ' . $raumId . ', Kurse ' . implode(' und ', $kurse) . ', ' . $tag->format('d.m.Y') . "\n";
echo str_repeat('-', 64) . "\n";

// --- 3./4. Runden ------------------------------------------------------------
$slots     = zeitslots();
$fehlerhaft = 0;

for ($runde = 1; $runde <= RUNDEN; $runde++) {
    $start = $slots[$runde - 1];
    $ende  = $slots[$runde];

    $multi   = curl_multi_init();
    $handles = array();
    foreach (array('A' => $kurse[0], 'B' => $kurse[1]) as $name => $kursId) {
        $handles[$name] = test_anfrage($basis . 'buchung_bearbeiten', array(
            'csrf'    => $sitzungen[$name]['csrf'],
            'kurs_id' => $kursId,
            'raum_id' => $raumId,
            'datum'   => $datum,
            'start'   => $start,
            'ende'    => $ende,
        ), $sitzungen[$name]['cookie']);
        curl_multi_add_handle($multi, $handles[$name]);
    }

    // Beide Anfragen laufen jetzt gleichzeitig.
    do {
        curl_multi_exec($multi, $laufend);
        curl_multi_select($multi);
    } while ($laufend > 0);

    $ergebnis = array();
    foreach ($handles as $name => $ch) {
        // 302 = gespeichert und weitergeleitet, 200 = Formular mit Fehlermeldung
        $ergebnis[] = $name . ': ' . (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 302 ? 'gebucht  ' : 'abgelehnt');
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }
    curl_multi_close($multi);

    $anzahl = (int) abfrage(
        'SELECT COUNT(*) FROM buchung WHERE raum_id = ? AND start = ? AND id > ?',
        array($raumId, $datum . ' ' . $start . ':00', $hoechsteIdVorher)
    )->fetchColumn();

    $ok = $anzahl === 1;
    if (!$ok) {
        $fehlerhaft++;
    }
    printf("Runde %2d  %s-%s  %s  -> %d Buchung(en)  %s\n", $runde, $start, $ende,
        implode('  ', $ergebnis), $anzahl, $ok ? 'OK' : 'FEHLER (Doppelbuchung)');
}

// --- 5. Aufraeumen ---------------------------------------------------------
abfrage('DELETE FROM buchung WHERE id > ? AND raum_id = ? AND start LIKE ?',
    array($hoechsteIdVorher, $raumId, $datum . '%'));

echo str_repeat('-', 64) . "\n";
if ($fehlerhaft === 0) {
    echo 'Ergebnis: In allen ' . RUNDEN . " Runden wurde genau eine Buchung gespeichert.\n";
    exit(0);
}
echo 'Ergebnis: ' . $fehlerhaft . ' von ' . RUNDEN . " Runden mit Doppelbuchung!\n";
exit(1);
