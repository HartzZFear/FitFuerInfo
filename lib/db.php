<?php
/**
 * Datenbankverbindung (PDO).
 *
 * Verwendung in jeder Seite:
 *   require_once __DIR__ . '/db.php';
 *   $db = db();
 *
 * db() baut die Verbindung beim ersten Aufruf auf und gibt bei weiteren
 * Aufrufen dieselbe Instanz zurueck. So gibt es pro Seitenaufruf genau
 * eine Verbindung.
 *
 * Hinweis: Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

// config.php laden. Fehlt sie, ist die Einrichtung nicht abgeschlossen.
if (!file_exists(__DIR__ . '/config.php')) {
    die(
        'Fehler: src/web/config.php fehlt. '
        . 'Bitte src/web/config.example.php kopieren und als src/web/config.php speichern. '
        . 'Details stehen in SETUP.md, Abschnitt 5.'
    );
}
require_once __DIR__ . '/config.php';

if (defined('DEBUG') && DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

/**
 * Liefert die PDO-Verbindung.
 *
 * @return PDO
 */
function db()
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST
         . ';dbname=' . DB_NAME
         . ';charset=' . DB_CHARSET;

    $optionen = array(
        // Fehler als Exception werfen statt still zu scheitern
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        // Ergebnisse als assoziatives Array
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Echte Prepared Statements erzwingen (Schutz vor SQL-Injection)
        PDO::ATTR_EMULATE_PREPARES   => false,
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $optionen);
    } catch (PDOException $e) {
        if (defined('DEBUG') && DEBUG) {
            die('Datenbankverbindung fehlgeschlagen: ' . $e->getMessage());
        }
        die('Datenbankverbindung fehlgeschlagen.');
    }

    return $pdo;
}

/**
 * Kurzform fuer eine Abfrage mit Prepared Statement.
 *
 * Beispiel:
 *   $zeilen = abfrage('SELECT * FROM kurs WHERE ersteller_id = ?', array($id));
 *
 * Parameter IMMER ueber das zweite Argument uebergeben, nie per
 * Stringverkettung in das SQL schreiben.
 *
 * @param  string $sql
 * @param  array  $parameter
 * @return PDOStatement
 */
function abfrage($sql, $parameter = array())
{
    $stmt = db()->prepare($sql);
    $stmt->execute($parameter);
    return $stmt;
}

/**
 * Text sicher fuer die Ausgabe in HTML aufbereiten (Schutz vor XSS).
 * Jede Ausgabe von Daten aus der Datenbank durch diese Funktion schicken.
 *
 * @param  string $text
 * @return string
 */
function h($text)
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
