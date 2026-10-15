<?php
/**
 * Gemeinsame Funktionen fuer die Benutzerverwaltung.
 *
 * Benutzer anlegen, bearbeiten und sperren darf nur der Admin. Ein Passwort
 * vergibt er dabei NIE: Er erzeugt einen Freischaltcode, und der Mitarbeiter
 * setzt sein Passwort damit selbst in passwort_setzen.php (siehe auth.php).
 *
 * Geloescht werden Benutzer bewusst nicht - kurs.ersteller_id und
 * buchung.benutzer_id verweisen per RESTRICT auf sie. Stattdessen wird das
 * Konto gesperrt (aktiv = 0); erfordere_login() beendet dann auch eine noch
 * laufende Session.
 *
 * Damit sich niemand aussperrt, gilt:
 *   - der letzte aktive Admin darf weder gesperrt noch zum Mitarbeiter
 *     gemacht werden
 *   - der Admin darf sich nicht selbst sperren
 * Diese Pruefung steht in benutzer_darf_aendern() und laeuft IMMER
 * serverseitig, nicht nur ueber das Ein-/Ausblenden der Buttons.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/funktionen.php';
require_once __DIR__ . '/auth.php';

/**
 * So lange ist ein neu erzeugter Freischaltcode gueltig.
 */
define('FREISCHALTCODE_GUELTIG_TAGE', 7);

/**
 * Erzeugt einen neuen Freischaltcode fuer den Benutzer und speichert ihn
 * mit einer Gueltigkeit von FREISCHALTCODE_GUELTIG_TAGE Tagen. Ein noch
 * offener alter Code wird dadurch ungueltig.
 *
 * passwort_hash bleibt unveraendert: Ein bestehendes Passwort gilt weiter,
 * bis der neue Code in passwort_setzen.php eingeloest wird ("Passwort
 * vergessen"). Erst code_einloesen() ueberschreibt es.
 *
 * Der Code wird im Klartext zurueckgegeben, damit der Aufrufer ihn genau
 * EINMAL anzeigen kann.
 *
 * @param  int $benutzerId
 * @return string|false false, wenn es den Benutzer nicht gibt
 */
function benutzer_code_erzeugen($benutzerId)
{
    $vorhanden = abfrage('SELECT id FROM benutzer WHERE id = ?', array((int) $benutzerId))->fetch();
    if (!$vorhanden) {
        return false;
    }

    $code       = erzeuge_freischaltcode();
    $gueltigBis = date('Y-m-d H:i:s', strtotime('+' . FREISCHALTCODE_GUELTIG_TAGE . ' days'));

    abfrage(
        'UPDATE benutzer SET freischaltcode = ?, code_gueltig_bis = ? WHERE id = ?',
        array($code, $gueltigBis, (int) $benutzerId)
    );

    return $code;
}

/**
 * Anzahl der Admins, die nicht gesperrt sind.
 *
 * @return int
 */
function benutzer_anzahl_aktive_admins()
{
    return (int) abfrage(
        'SELECT COUNT(*) FROM benutzer WHERE rolle = ? AND aktiv = 1',
        array('admin')
    )->fetchColumn();
}

/**
 * Sperrt (SELECT ... FOR UPDATE) die Zeilen aller aktiven Admins bis zum
 * Ende der laufenden Transaktion. So koennen zwei Admins nicht gleichzeitig
 * je den anderen sperren und damit keinen aktiven Admin uebrig lassen.
 * Gehoert deshalb vor benutzer_darf_aendern() in dieselbe Transaktion.
 */
function benutzer_admins_sperren()
{
    abfrage('SELECT id FROM benutzer WHERE rolle = ? AND aktiv = 1 FOR UPDATE', array('admin'));
}

/**
 * Prueft, ob eine Aenderung an Rolle oder Status eines bestehenden Benutzers
 * erlaubt ist, und sammelt - wie buchung_pruefen() - alle Verstoesse ein.
 *
 * Abgelehnt wird, wenn
 *   - der Admin sich selbst sperren wuerde, oder
 *   - der Benutzer der letzte aktive Admin ist und gesperrt oder zum
 *     Mitarbeiter gemacht werden soll.
 *
 * @param  int    $zielId    der zu aendernde Benutzer
 * @param  int    $eigeneId  der angemeldete Admin
 * @param  string $neueRolle 'admin' oder 'mitarbeiter'
 * @param  bool   $neuAktiv
 * @return array leer = erlaubt, sonst deutsche Fehlermeldungen
 */
function benutzer_darf_aendern($zielId, $eigeneId, $neueRolle, $neuAktiv)
{
    $fehler = array();

    $ziel = abfrage('SELECT rolle, aktiv FROM benutzer WHERE id = ?', array((int) $zielId))->fetch();
    if (!$ziel) {
        $fehler[] = 'Diesen Benutzer gibt es nicht.';
        return $fehler;
    }

    if ((int) $zielId === (int) $eigeneId && !$neuAktiv) {
        $fehler[] = 'Sie können Ihr eigenes Konto nicht sperren.';
    }

    $istAktiverAdmin    = $ziel['rolle'] === 'admin' && (int) $ziel['aktiv'] === 1;
    $bleibtAktiverAdmin = $neueRolle === 'admin' && $neuAktiv;

    if ($istAktiverAdmin && !$bleibtAktiverAdmin && benutzer_anzahl_aktive_admins() <= 1) {
        $fehler[] = 'Das ist der letzte aktive Admin. Er kann weder gesperrt noch zum Mitarbeiter '
            . 'gemacht werden - sonst könnte niemand mehr Benutzer verwalten.';
    }

    return $fehler;
}

/**
 * Status eines Benutzers fuer Anzeige und Filter.
 *
 * @param  array $benutzer Zeile mit aktiv und passwort_hash
 * @return string 'gesperrt', 'wartet' oder 'aktiv'
 */
function benutzer_status($benutzer)
{
    if ((int) $benutzer['aktiv'] !== 1) {
        return 'gesperrt';
    }
    if ($benutzer['passwort_hash'] === null) {
        return 'wartet';
    }
    return 'aktiv';
}

/**
 * Legt einen frisch erzeugten Code fuer die einmalige Anzeige in der
 * Session ab. benutzer.php zeigt ihn beim naechsten Aufruf genau einmal an
 * und loescht ihn sofort wieder (Post/Redirect/Get - ein Neuladen der Seite
 * erzeugt so keinen weiteren Code und zeigt den alten nicht noch einmal).
 *
 * @param int    $benutzerId
 * @param string $code
 */
function benutzer_code_merken($benutzerId, $code)
{
    session_starten();

    $zeile = abfrage(
        'SELECT name, code_gueltig_bis FROM benutzer WHERE id = ?',
        array((int) $benutzerId)
    )->fetch();

    $_SESSION['code_anzeige'] = array(
        'name'        => $zeile['name'],
        'code'        => $code,
        'gueltig_bis' => $zeile['code_gueltig_bis'],
    );
}

/**
 * Zeigt den gemerkten Freischaltcode einmalig auf der Hinweisseite
 * pages/freischaltcode.php an, entfernt ihn aus der Session und beendet das
 * Skript. Ist nichts gemerkt, kehrt die Funktion einfach zurueck.
 *
 * Dass der Code nicht ueber den Browser-Cache wieder auftaucht, regeln die
 * Cache-Header aus cache_dynamisch(), die index.php fuer jede Seite setzt.
 */
function benutzer_code_anzeigen_falls_vorhanden()
{
    session_starten();

    if (!isset($_SESSION['code_anzeige'])) {
        return;
    }

    $anzeige = $_SESSION['code_anzeige'];
    unset($_SESSION['code_anzeige']);

    $schema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

    seite_wechseln('freischaltcode', array(
        'name'        => $anzeige['name'],
        'code'        => $anzeige['code'],
        'gueltig_bis' => date('d.m.Y, H:i', strtotime($anzeige['gueltig_bis'])) . ' Uhr',
        'link'        => $schema . '://' . $host . url('passwort_setzen'),
    ));
}
