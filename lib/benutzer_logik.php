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
 * Einen Benutzer laden. passwort_hash wird nur gelesen, damit
 * benutzer_status() erkennt, ob schon ein Passwort gesetzt ist.
 *
 * @param  int $benutzerId
 * @return array|false Zeile mit id, name, email, rolle, aktiv, passwort_hash,
 *                     freischaltcode, code_gueltig_bis - oder false
 */
function benutzer_laden($benutzerId)
{
    return abfrage(
        'SELECT id, name, email, rolle, aktiv, passwort_hash, freischaltcode, code_gueltig_bis
         FROM benutzer WHERE id = ?',
        array((int) $benutzerId)
    )->fetch();
}

/**
 * Benutzerliste mit den Filtern der Seite benutzer. Alle drei Filter sind
 * frei kombinierbar und landen ausschliesslich als Parameter im Prepared
 * Statement.
 *
 * @param  string $suche  Teil von Name oder E-Mail, '' = alle
 * @param  string $rolle  'admin', 'mitarbeiter' oder '' = alle
 * @param  string $status 'aktiv', 'gesperrt', 'wartet' oder '' = alle
 * @return array Zeilen wie bei benutzer_laden()
 */
function benutzer_liste($suche, $rolle, $status)
{
    $bedingungen = array();
    $parameter   = array();

    $sql = 'SELECT id, name, email, rolle, aktiv, passwort_hash, freischaltcode, code_gueltig_bis
            FROM benutzer';

    if ($suche !== '') {
        $bedingungen[] = '(name LIKE ? OR email LIKE ?)';
        $parameter[]   = '%' . $suche . '%';
        $parameter[]   = '%' . $suche . '%';
    }

    if ($rolle !== '') {
        $bedingungen[] = 'rolle = ?';
        $parameter[]   = $rolle;
    }

    // Gleiche Einteilung wie benutzer_status(): gesperrt geht vor "wartet".
    if ($status === 'gesperrt') {
        $bedingungen[] = 'aktiv = 0';
    } elseif ($status === 'wartet') {
        $bedingungen[] = 'aktiv = 1 AND passwort_hash IS NULL';
    } elseif ($status === 'aktiv') {
        $bedingungen[] = 'aktiv = 1 AND passwort_hash IS NOT NULL';
    }

    if (!empty($bedingungen)) {
        $sql .= ' WHERE ' . implode(' AND ', $bedingungen);
    }

    $sql .= ' ORDER BY name';

    return abfrage($sql, $parameter)->fetchAll();
}

/**
 * Kennzahlen fuer die Karten der Benutzerliste - je eine Abfrage fuer alle
 * Benutzer statt einer pro Karte.
 *
 * @return array 'kurse'     => benutzer_id => Anzahl Kurse als Eigentuemer,
 *               'raeume'    => benutzer_id => Anzahl Raeume als Bearbeiter,
 *               'buchungen' => benutzer_id => Anzahl kommender Buchungen
 *               (Benutzer ohne Eintrag fehlen)
 */
function benutzer_kennzahlen()
{
    $ergebnis = array('kurse' => array(), 'raeume' => array(), 'buchungen' => array());

    // Eigentuemer = Ersteller ODER Eintrag in kurs_eigentuemer. UNION
    // entfernt doppelte Paare, wenn jemand beides ist.
    $zeilen = abfrage(
        'SELECT benutzer_id, COUNT(*) AS anzahl
         FROM (
             SELECT ersteller_id AS benutzer_id, id AS kurs_id FROM kurs
             UNION
             SELECT benutzer_id, kurs_id FROM kurs_eigentuemer
         ) AS eigentum
         GROUP BY benutzer_id'
    )->fetchAll();
    foreach ($zeilen as $zeile) {
        $ergebnis['kurse'][$zeile['benutzer_id']] = (int) $zeile['anzahl'];
    }

    $zeilen = abfrage(
        'SELECT benutzer_id, COUNT(*) AS anzahl FROM raum_bearbeiter GROUP BY benutzer_id'
    )->fetchAll();
    foreach ($zeilen as $zeile) {
        $ergebnis['raeume'][$zeile['benutzer_id']] = (int) $zeile['anzahl'];
    }

    $zeilen = abfrage(
        'SELECT benutzer_id, COUNT(*) AS anzahl FROM buchung WHERE start >= ? GROUP BY benutzer_id',
        array(date('Y-m-d H:i:s'))
    )->fetchAll();
    foreach ($zeilen as $zeile) {
        $ergebnis['buchungen'][$zeile['benutzer_id']] = (int) $zeile['anzahl'];
    }

    return $ergebnis;
}

/**
 * Kennzahlen fuer das eigene Profil.
 *
 * @param  int $benutzerId
 * @return array 'kurse' (als Eigentuemer), 'raeume' (als Bearbeiter),
 *               'buchungen' (kommende) - jeweils int
 */
function benutzer_profil_zahlen($benutzerId)
{
    // Eigentuemer = Ersteller ODER Eintrag in kurs_eigentuemer (wie
    // benutzer_kennzahlen()). UNION entfernt doppelte Kurse.
    $kurse = (int) abfrage(
        'SELECT COUNT(*) FROM (
             SELECT id AS kurs_id FROM kurs WHERE ersteller_id = ?
             UNION
             SELECT kurs_id FROM kurs_eigentuemer WHERE benutzer_id = ?
         ) AS eigentum',
        array($benutzerId, $benutzerId)
    )->fetchColumn();

    $raeume = (int) abfrage(
        'SELECT COUNT(*) FROM raum_bearbeiter WHERE benutzer_id = ?',
        array($benutzerId)
    )->fetchColumn();

    $buchungen = (int) abfrage(
        'SELECT COUNT(*) FROM buchung WHERE benutzer_id = ? AND start >= ?',
        array($benutzerId, date('Y-m-d H:i:s'))
    )->fetchColumn();

    return array('kurse' => $kurse, 'raeume' => $raeume, 'buchungen' => $buchungen);
}

/**
 * Aendert das eigene Passwort. Das aktuelle Passwort muss stimmen, damit
 * niemand an einem kurz unbeaufsichtigten Rechner das Passwort eines
 * anderen umstellen kann.
 *
 * Wie passwort_setzen, aber Regeln und Wiederholung werden unabhaengig
 * voneinander geprueft, damit alle Probleme auf einmal angezeigt werden.
 *
 * @param  int    $benutzerId
 * @param  string $altesPasswort
 * @param  string $passwort1
 * @param  string $passwort2 Wiederholung
 * @return true|array true bei Erfolg, sonst deutsche Fehlermeldungen
 */
function benutzer_passwort_aendern($benutzerId, $altesPasswort, $passwort1, $passwort2)
{
    $fehler = array();

    // Hash frisch aus der Datenbank, aktueller_benutzer() liefert ihn bewusst nicht.
    $hash = abfrage('SELECT passwort_hash FROM benutzer WHERE id = ?', array($benutzerId))->fetchColumn();

    $altesStimmt = false;
    if ($altesPasswort === '') {
        $fehler[] = 'Bitte das aktuelle Passwort eingeben.';
    } elseif (!is_string($hash) || !password_verify($altesPasswort, $hash)) {
        $fehler[] = 'Das aktuelle Passwort ist falsch.';
    } else {
        $altesStimmt = true;
    }

    if ($passwort1 === '' || $passwort2 === '') {
        $fehler[] = 'Bitte das neue Passwort zweimal eingeben.';
    } else {
        $fehler = array_merge($fehler, pruefe_passwortregeln($passwort1));

        if ($passwort1 !== $passwort2) {
            $fehler[] = 'Die beiden neuen Passwörter stimmen nicht überein.';
        }
        // Nur vergleichen, wenn das alte stimmt - sonst liesse sich ueber
        // diese Meldung das aktuelle Passwort erraten.
        if ($altesStimmt && $passwort1 === $altesPasswort) {
            $fehler[] = 'Das neue Passwort muss sich vom bisherigen unterscheiden.';
        }
    }

    if (!empty($fehler)) {
        return $fehler;
    }

    // Ein noch offener Freischaltcode ("Passwort vergessen") wird mit
    // verworfen: Wer sein Passwort gerade selbst gesetzt hat, braucht ihn
    // nicht mehr, und ein herumliegender Code koennte es sonst
    // ueberschreiben.
    abfrage(
        'UPDATE benutzer
         SET passwort_hash = ?, freischaltcode = NULL, code_gueltig_bis = NULL
         WHERE id = ?',
        array(password_hash($passwort1, PASSWORD_DEFAULT), $benutzerId)
    );

    return true;
}

/**
 * Alle nicht gesperrten Benutzer, alphabetisch - fuer die Checkbox-Listen
 * "Eigentuemer" (Kurs) und "Bearbeiter" (Raum), die nur der Admin sieht.
 *
 * @return array Zeilen mit id, name
 */
function benutzer_aktive()
{
    return abfrage('SELECT id, name FROM benutzer WHERE aktiv = 1 ORDER BY name')->fetchAll();
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
 * Sperrt oder entsperrt einen Benutzer (Knoepfe in der Benutzerliste).
 *
 * @param  array $ziel     Zeile aus benutzer_laden()
 * @param  int   $eigeneId der angemeldete Admin
 * @param  bool  $neuAktiv true = entsperren, false = sperren
 * @return true|array true bei Erfolg, sonst deutsche Fehlermeldungen
 */
function benutzer_aktiv_setzen($ziel, $eigeneId, $neuAktiv)
{
    $db = db();
    $db->beginTransaction();

    try {
        // Admin-Zeilen sperren, bevor geprueft wird - sonst koennten zwei
        // Admins gleichzeitig den jeweils anderen sperren.
        benutzer_admins_sperren();
        $fehler = benutzer_darf_aendern($ziel['id'], $eigeneId, $ziel['rolle'], $neuAktiv);

        if (!empty($fehler)) {
            $db->rollBack();
            return $fehler;
        }

        abfrage('UPDATE benutzer SET aktiv = ? WHERE id = ?', array($neuAktiv ? 1 : 0, $ziel['id']));
        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return array('Die Änderung konnte nicht gespeichert werden. Bitte erneut versuchen.');
    }

    return true;
}

/**
 * Prueft die Eingaben und legt einen Benutzer an bzw. speichert ihn.
 *
 * Ein neuer Benutzer wird ohne Passwort gespeichert und bekommt gleich
 * einen Freischaltcode; der Code wird mit benutzer_code_merken() fuer die
 * einmalige Anzeige auf der Benutzerliste abgelegt.
 *
 * @param  int|null $zielId   null = neuer Benutzer
 * @param  array    $eingabe  name, email, rolle, aktiv (bool)
 * @param  int      $eigeneId der angemeldete Admin
 * @return true|array true bei Erfolg, sonst deutsche Fehlermeldungen
 */
function benutzer_speichern($zielId, $eingabe, $eigeneId)
{
    $fehler = array();
    $name   = $eingabe['name'];
    $email  = $eingabe['email'];
    $rolle  = $eingabe['rolle'];
    $aktiv  = $eingabe['aktiv'];

    if ($name === '') {
        $fehler[] = 'Bitte einen Benutzernamen eingeben.';
    } elseif (mb_strlen($name, 'UTF-8') > 50) {
        $fehler[] = 'Der Benutzername darf höchstens 50 Zeichen lang sein.';
    }

    // E-Mail ist optional. Leer wird als NULL gespeichert.
    if ($email !== '') {
        if (mb_strlen($email, 'UTF-8') > 100) {
            $fehler[] = 'Die E-Mail-Adresse darf höchstens 100 Zeichen lang sein.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $fehler[] = 'Die E-Mail-Adresse "' . $email . '" hat kein gültiges Format (Beispiel: name@schule.de).';
        }
    }

    if (!in_array($rolle, array('admin', 'mitarbeiter'), true)) {
        $fehler[] = 'Bitte eine gültige Rolle auswählen.';
        return $fehler;
    }

    // Auch bei Formatfehlern weiterpruefen, damit alle Meldungen (z. B.
    // ungueltige E-Mail UND doppelter Name) auf einmal erscheinen.
    // Gespeichert wird nur, wenn $fehler leer bleibt.
    $db = db();
    $db->beginTransaction();

    try {
        if ($zielId !== null) {
            // Admin-Zeilen sperren, bevor geprueft wird - sonst koennten
            // zwei Admins gleichzeitig den jeweils anderen entmachten.
            benutzer_admins_sperren();
            $fehler = array_merge($fehler, benutzer_darf_aendern($zielId, $eigeneId, $rolle, $aktiv));
        }

        // Der Name ist in der Datenbank eindeutig (uk_benutzer_name).
        // Vorher pruefen, damit der Admin eine verstaendliche Meldung
        // bekommt statt eines Datenbankfehlers.
        $sql       = 'SELECT id FROM benutzer WHERE name = ?';
        $parameter = array($name);
        if ($zielId !== null) {
            $sql .= ' AND id <> ?';
            $parameter[] = $zielId;
        }

        if ($name !== '' && abfrage($sql, $parameter)->fetch()) {
            $fehler[] = 'Es gibt bereits einen Benutzer mit dem Namen "' . $name . '". '
                . 'Groß- und Kleinschreibung zählt dabei nicht.';
        }

        if (!empty($fehler)) {
            $db->rollBack();
            return $fehler;
        }

        $emailWert = $email === '' ? null : $email;

        if ($zielId !== null) {
            abfrage(
                'UPDATE benutzer SET name = ?, email = ?, rolle = ?, aktiv = ? WHERE id = ?',
                array($name, $emailWert, $rolle, $aktiv ? 1 : 0, $zielId)
            );
            $db->commit();
            return true;
        }

        // Neuer Benutzer: ohne Passwort speichern (passwort_hash bleibt
        // NULL) und gleich einen Freischaltcode erzeugen.
        abfrage(
            'INSERT INTO benutzer (name, email, passwort_hash, rolle, aktiv) VALUES (?, ?, NULL, ?, ?)',
            array($name, $emailWert, $rolle, $aktiv ? 1 : 0)
        );
        $neueId = (int) $db->lastInsertId();
        $code   = benutzer_code_erzeugen($neueId);
        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        // Faengt auch den RuntimeException aus erzeuge_freischaltcode().
        // 23000 = Verletzung eines UNIQUE-Schluessels, z. B. wenn jemand
        // denselben Namen gleichzeitig angelegt hat.
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            $fehler[] = 'Es gibt bereits einen Benutzer mit dem Namen "' . $name . '".';
        } else {
            $fehler[] = 'Der Benutzer konnte nicht gespeichert werden. Bitte erneut versuchen.';
        }
        return $fehler;
    }

    // Einmalige Anzeige uebernimmt die Benutzerliste.
    benutzer_code_merken($neueId, $code);
    return true;
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
