<?php
/**
 * Selbsttest der lokalen Einrichtung.
 *
 * Aufruf im Browser: <BASE_URL>/tests/test.php
 *
 * Prueft PHP-Version, Datenbankverbindung, Tabellen und Testdaten.
 * Diese Datei ist nur ein Hilfsmittel fuer die Einrichtung und
 * gehoert NICHT in die fertige Abgabe.
 */

$ergebnisse = array();

function pruefe($titel, $ok, $info)
{
    global $ergebnisse;
    $ergebnisse[] = array('titel' => $titel, 'ok' => $ok, 'info' => $info);
}

// --- 1. PHP-Version ----------------------------------------------------
$phpOk = version_compare(PHP_VERSION, '5.6.0', '>=')
      && version_compare(PHP_VERSION, '7.0.0', '<');
pruefe(
    'PHP-Version',
    $phpOk,
    'Gefunden: ' . PHP_VERSION
        . ($phpOk ? '' : ' – erwartet wird 5.6.x wie auf dem Schulrechner')
);

// --- 2. PDO-MySQL-Treiber ---------------------------------------------
$pdoOk = extension_loaded('pdo_mysql');
pruefe(
    'PDO-MySQL-Treiber',
    $pdoOk,
    $pdoOk ? 'geladen' : 'fehlt – in der php.ini pdo_mysql aktivieren'
);

// --- 3. config.php -----------------------------------------------------
$configOk = file_exists(__DIR__ . '/../config/config.php');
pruefe(
    'config/config.php vorhanden',
    $configOk,
    $configOk
        ? 'gefunden'
        : 'fehlt – config/config.example.php kopieren und als config/config.php speichern'
);

$db = null;

// --- 4. Datenbankverbindung -------------------------------------------
if ($configOk && $pdoOk) {
    require_once __DIR__ . '/../lib/db.php';
    try {
        $db = db();
        pruefe('Datenbankverbindung', true, 'verbunden mit ' . DB_NAME . ' auf ' . DB_HOST);
    } catch (Exception $e) {
        pruefe('Datenbankverbindung', false, $e->getMessage());
    }

    // config.php ist jetzt geladen: ASSET_VERSION haengt als ?v=... an den
    // CSS/JS-Links (asset_url()). Aeltere lokale config.php-Dateien haben sie
    // noch nicht.
    pruefe(
        'ASSET_VERSION in config.php',
        defined('ASSET_VERSION'),
        defined('ASSET_VERSION')
            ? 'Version ' . ASSET_VERSION
            : 'fehlt – Zeile mit ASSET_VERSION aus config/config.example.php uebernehmen'
    );
}

// --- 5. Tabellen -------------------------------------------------------
$erwartet = array(
    'benutzer', 'buchung', 'kurs', 'kurs_eigentuemer', 'kurs_software',
    'raum', 'raum_bearbeiter', 'raum_software', 'software'
);

if ($db !== null) {
    $vorhanden = array();
    $stmt = $db->query('SHOW TABLES');
    while ($zeile = $stmt->fetch(PDO::FETCH_NUM)) {
        $vorhanden[] = $zeile[0];
    }
    $fehlend = array_diff($erwartet, $vorhanden);
    pruefe(
        'Tabellen (9 erwartet)',
        count($fehlend) === 0,
        count($fehlend) === 0
            ? count($vorhanden) . ' Tabellen gefunden'
            : 'Es fehlen: ' . implode(', ', $fehlend) . ' – bitte src/db/schema.sql importieren'
    );

    // --- 6. Testdaten --------------------------------------------------
    if (count($fehlend) === 0) {
        $zaehler = array();
        foreach (array('benutzer' => 5, 'kurs' => 4, 'raum' => 3, 'buchung' => 15) as $t => $soll) {
            $anzahl = (int) $db->query('SELECT COUNT(*) FROM `' . $t . '`')->fetchColumn();
            $zaehler[] = $t . ': ' . $anzahl . '/' . $soll;
        }
        $seedOk = ((int) $db->query('SELECT COUNT(*) FROM benutzer')->fetchColumn()) > 0;
        pruefe(
            'Testdaten',
            $seedOk,
            $seedOk
                ? implode('  |  ', $zaehler)
                : 'keine Daten – bitte src/db/seed.sql importieren'
        );

        // --- 7. Passworthash testen ------------------------------------
        $hash = $db->query("SELECT passwort_hash FROM benutzer WHERE name = 'admin'")->fetchColumn();
        $pwOk = $hash && password_verify('test1', $hash);
        pruefe(
            'Passwortpruefung (admin / test1)',
            $pwOk,
            $pwOk ? 'password_verify funktioniert' : 'Hash passt nicht – seed.sql neu importieren'
        );
    }
}

$allesOk = true;
foreach ($ergebnisse as $e) {
    if (!$e['ok']) { $allesOk = false; }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Selbsttest – FitFuerInfo</title>
<?php /* Relativ und ohne ?v=: Die Testseite muss auch laufen, wenn
         config.php (BASE_URL, ASSET_VERSION) noch fehlt. */ ?>
<link rel="stylesheet" href="test.css">
</head>
<body>

<h1>Selbsttest der lokalen Einrichtung</h1>
<p class="sub">FitFuerInfo – Kurs- und Raumverwaltung</p>

<table>
<tr><th class="spalte-status">Status</th><th class="spalte-pruefung">Pruefung</th><th>Ergebnis</th></tr>
<?php foreach ($ergebnisse as $e): ?>
<tr>
  <td class="<?php echo $e['ok'] ? 'ok' : 'fail'; ?>"><?php echo $e['ok'] ? 'OK' : 'FEHLER'; ?></td>
  <td><?php echo h($e['titel']); ?></td>
  <td><?php echo h($e['info']); ?></td>
</tr>
<?php endforeach; ?>
</table>

<?php if ($allesOk): ?>
<div class="box gut">
  Alles in Ordnung. Die Einrichtung ist abgeschlossen und du kannst mit deinen
  Karten auf dem Kanban-Board anfangen.
</div>
<?php else: ?>
<div class="box schlecht">
  Mindestens eine Pruefung ist fehlgeschlagen. Die Ursache steht in der Spalte
  Ergebnis. Weitere Hinweise stehen in <code>SETUP.md</code>.
</div>
<?php endif; ?>

</body>
</html>
