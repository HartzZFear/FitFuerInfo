<?php
/**
 * Funktionen der Einrichtungsseite einrichtung.php: config/config.php
 * anlegen, MySQL verbinden, src/db/schema.sql und seed.sql ausfuehren,
 * Ergebnis pruefen.
 *
 * Bindet bewusst NICHT lib/db.php ein: Vor der Einrichtung fehlen
 * config.php und die Datenbank, lib/db.php wuerde dann abbrechen. Deshalb
 * gibt es hier eine eigene Verbindung ohne Datenbanknamen statt db() -
 * die Datenbank legt erst schema.sql an.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

/**
 * Prueft, ob die Seite vom eigenen Rechner aus aufgerufen wird.
 * Der XAMPP-Apache ist im Schulnetz auch von anderen Rechnern erreichbar,
 * und die Einrichtung loescht alle Daten - das darf nur, wer am Rechner sitzt.
 *
 * @return bool true bei 127.0.0.1 (IPv4) oder ::1 (IPv6)
 */
function einrichtung_erlaubt()
{
    return in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1'), true);
}

/**
 * Die fuenf Schritte der Einrichtung, alle noch "nicht ausgefuehrt".
 *
 * @return array je Schritt: titel, status ('offen', 'erledigt', 'fehler'), meldung
 */
function einrichtung_schritte()
{
    $titel = array(
        'Konfiguration anlegen',
        'Verbindung zu MySQL',
        'Datenbank und Tabellen anlegen',
        'Testdaten laden',
        'Prüfung',
    );

    $schritte = array();
    foreach ($titel as $t) {
        $schritte[] = array('titel' => $t, 'status' => 'offen', 'meldung' => '');
    }
    return $schritte;
}

/**
 * Fuehrt alle Schritte nacheinander aus. Beim ersten Fehler wird
 * abgebrochen, die restlichen Schritte bleiben 'offen'.
 *
 * Jeder Schritt wirft bei einem Fehler eine Exception mit verstaendlicher
 * Meldung - so reicht ein einziges try/catch fuer alle fuenf Schritte.
 *
 * @return array wie einrichtung_schritte(), mit Status und Meldung
 */
function einrichtung_ausfuehren()
{
    $schritte = einrichtung_schritte();
    $nr       = 0;

    try {
        $schritte[0]['meldung'] = einrichtung_config_anlegen();
        $schritte[0]['status']  = 'erledigt';

        $nr  = 1;
        $pdo = einrichtung_verbinden();
        $schritte[1]['meldung'] = 'verbunden mit ' . DB_HOST;
        $schritte[1]['status']  = 'erledigt';

        $nr = 2;
        $schritte[2]['meldung'] = einrichtung_sql_ausfuehren($pdo, 'schema.sql');
        $schritte[2]['status']  = 'erledigt';

        // Dieselbe Verbindung wie bei schema.sql: seed.sql setzt sich mit
        // USE und Variablen wie SET @mo auf die Verbindung.
        $nr = 3;
        $schritte[3]['meldung'] = einrichtung_sql_ausfuehren($pdo, 'seed.sql');
        $schritte[3]['status']  = 'erledigt';

        $nr = 4;
        $schritte[4]['meldung'] = einrichtung_pruefen($pdo);
        $schritte[4]['status']  = 'erledigt';
    } catch (Exception $e) {
        $schritte[$nr]['status']  = 'fehler';
        $schritte[$nr]['meldung'] = $e->getMessage();
    }

    return $schritte;
}

/**
 * Leitet BASE_URL aus der tatsaechlichen Adresse von einrichtung.php ab.
 * SCRIPT_NAME ist z. B. "/fitfuerinfo/einrichtung.php" -> "/fitfuerinfo".
 * Liegt das Projekt direkt in htdocs, liefert dirname() unter Windows "\"
 * und sonst "/" - beides wird zu "".
 *
 * @return string z. B. '/fitfuerinfo' oder ''
 */
function einrichtung_base_url()
{
    $ordner = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    return rtrim($ordner, '/');
}

/**
 * Schritt 1: Fehlt config/config.php, wird sie aus config.example.php
 * erstellt (die Vorlage bleibt erhalten). Dabei wird BASE_URL auf die
 * tatsaechliche Adresse gesetzt, damit das Projekt auch in einem anders
 * benannten Ordner laeuft. Eine vorhandene config.php wird nie
 * ueberschrieben, aber ihr BASE_URL muss zur Adresse passen.
 * Danach ist config.php eingebunden (DB_HOST, DB_USER, ...).
 *
 * @return string Meldung bei Erfolg
 * @throws Exception mit verstaendlicher Meldung
 */
function einrichtung_config_anlegen()
{
    $ziel    = __DIR__ . '/../config/config.php';
    $vorlage = __DIR__ . '/../config/config.example.php';
    $basis   = einrichtung_base_url();

    if (file_exists($ziel)) {
        require_once $ziel;
        if (BASE_URL !== $basis) {
            throw new Exception(
                "BASE_URL in config/config.php ist '" . BASE_URL . "', das Projekt liegt aber unter '"
                . $basis . "'. Bitte anpassen oder den Ordner in fitfuerinfo umbenennen."
            );
        }
        return 'bereits vorhanden';
    }

    // Genau diese Zeile steht in der Vorlage. var_export() schreibt den
    // Wert als gueltigen PHP-String in Anfuehrungszeichen.
    $alteZeile = "define('BASE_URL', '/fitfuerinfo');";
    $neueZeile = "define('BASE_URL', " . var_export($basis, true) . ');';

    $text = file_get_contents($vorlage);
    if (strpos($text, $alteZeile) === false) {
        throw new Exception('In config/config.example.php fehlt die Zeile ' . $alteZeile);
    }
    if (file_put_contents($ziel, str_replace($alteZeile, $neueZeile, $text)) === false) {
        throw new Exception('config/config.php konnte nicht geschrieben werden. Hat der Ordner config/ Schreibrechte?');
    }

    require_once $ziel;
    return "aus config.example.php erstellt, BASE_URL = '" . $basis . "'";
}

/**
 * Schritt 2: Verbindung zu MySQL OHNE Datenbanknamen - die Datenbank
 * fitfuerinfo gibt es auf einem frischen Rechner noch nicht.
 *
 * @return PDO
 * @throws Exception mit Hinweis auf das XAMPP Control Panel
 */
function einrichtung_verbinden()
{
    try {
        return new PDO(
            'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
        );
    } catch (PDOException $e) {
        throw new Exception(
            'Keine Verbindung: ' . $e->getMessage() . ' – Läuft MySQL im XAMPP Control Panel?'
        );
    }
}

/**
 * Schritte 3 und 4: Fuehrt eine SQL-Datei aus src/db/ Befehl fuer Befehl aus.
 *
 * Zerlegen: Zeilen, die mit -- beginnen, fallen weg; ein Befehl endet an
 * einem Semikolon am Zeilenende. Das reicht fuer schema.sql und seed.sql:
 * Dort steht kein Semikolon am Zeilenende innerhalb eines Strings, und die
 * Kommentarzeilen mit Semikolon sind vorher schon entfernt.
 *
 * Jeder Befehl laeuft mit einem eigenen exec(). Die ganze Datei in einem
 * exec() wuerde Fehler ab dem zweiten Befehl verschlucken.
 *
 * @param  PDO    $pdo
 * @param  string $datei 'schema.sql' oder 'seed.sql'
 * @return string Meldung bei Erfolg
 * @throws Exception mit Nummer des Befehls und Text von MySQL
 */
function einrichtung_sql_ausfuehren($pdo, $datei)
{
    $befehle = array();
    $befehl  = '';

    foreach (file(__DIR__ . '/../src/db/' . $datei) as $zeile) {
        if (substr(trim($zeile), 0, 2) === '--') {
            continue;
        }
        $befehl .= $zeile;
        if (substr(rtrim($zeile), -1) === ';') {
            $befehle[] = $befehl;
            $befehl    = '';
        }
    }
    $befehle[] = $befehl;

    $nr = 0;
    foreach ($befehle as $sql) {
        if (trim($sql) === '' || trim($sql) === ';') {
            continue;
        }
        $nr++;
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            throw new Exception($datei . ', Befehl ' . $nr . ': ' . $e->getMessage());
        }
    }

    return $nr . ' Befehle aus ' . $datei . ' ausgeführt';
}

/**
 * Tabellen, die schema.sql anlegt. Auch von tests/test.php verwendet.
 *
 * @return array Tabellennamen
 */
function einrichtung_tabellen()
{
    return array(
        'benutzer', 'buchung', 'kurs', 'kurs_eigentuemer', 'kurs_software',
        'raum', 'raum_bearbeiter', 'raum_software', 'software',
    );
}

/**
 * Schritt 5: Sind alle Tabellen da, gibt es Benutzer, und passt das
 * Passwort test1 zum Hash von admin? Die Verbindung steht nach seed.sql
 * (USE fitfuerinfo) schon auf der Datenbank.
 *
 * @param  PDO $pdo
 * @return string Meldung bei Erfolg
 * @throws Exception wenn etwas fehlt
 */
function einrichtung_pruefen($pdo)
{
    $vorhanden = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $fehlend   = array_diff(einrichtung_tabellen(), $vorhanden);
    if (!empty($fehlend)) {
        throw new Exception('Es fehlen Tabellen: ' . implode(', ', $fehlend));
    }

    $anzahl = (int) $pdo->query('SELECT COUNT(*) FROM benutzer')->fetchColumn();
    if ($anzahl === 0) {
        throw new Exception('Die Tabelle benutzer ist leer.');
    }

    $hash = $pdo->query("SELECT passwort_hash FROM benutzer WHERE name = 'admin'")->fetchColumn();
    if (!$hash || !password_verify('test1', $hash)) {
        throw new Exception('Anmeldung mit admin / test1 klappt nicht: Benutzer admin fehlt oder der Passwort-Hash passt nicht.');
    }

    return count($vorhanden) . ' Tabellen, ' . $anzahl . ' Benutzer, admin / test1 passt';
}
