<?php
/**
 * Selbsttest der lokalen Einrichtung.
 *
 * Aufruf im Browser: http://localhost/fitfuerinfo/src/web/tst/test.php
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
$configOk = file_exists(__DIR__ . '/../config.php');
pruefe(
    'src/web/config.php vorhanden',
    $configOk,
    $configOk
        ? 'gefunden'
        : 'fehlt – src/web/config.example.php kopieren und als src/web/config.php speichern'
);

$db = null;

// --- 4. Datenbankverbindung -------------------------------------------
if ($configOk && $pdoOk) {
    require_once __DIR__ . '/../db.php';
    try {
        $db = db();
        pruefe('Datenbankverbindung', true, 'verbunden mit ' . DB_NAME . ' auf ' . DB_HOST);
    } catch (Exception $e) {
        pruefe('Datenbankverbindung', false, $e->getMessage());
    }
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
        foreach (array('benutzer' => 5, 'kurs' => 4, 'raum' => 3, 'buchung' => 7) as $t => $soll) {
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
<style>
body { font-family: Arial, Helvetica, sans-serif; margin: 40px; color: #222; line-height: 1.5; }
h1 { font-size: 22px; font-weight: normal; margin-bottom: 4px; }
p.sub { color: #666; margin-top: 0; }
table { border-collapse: collapse; margin-top: 20px; width: 100%; max-width: 760px; }
th, td { text-align: left; padding: 9px 12px; border-bottom: 1px solid #ddd; font-size: 14px; }
th { background: #f3f3f3; font-weight: bold; }
.ok { color: #1a7f37; font-weight: bold; }
.fail { color: #b4261e; font-weight: bold; }
.box { max-width: 760px; padding: 14px 16px; margin-top: 24px; border-radius: 4px; font-size: 14px; }
.gut { background: #e6f4ea; border: 1px solid #a8d5b5; }
.schlecht { background: #fdeceb; border: 1px solid #f0b3ae; }
code { background: #f3f3f3; padding: 1px 5px; border-radius: 3px; }
</style>
</head>
<body>

<h1>Selbsttest der lokalen Einrichtung</h1>
<p class="sub">FitFuerInfo – Kurs- und Raumverwaltung</p>

<table>
<tr><th style="width:60px;">Status</th><th style="width:230px;">Pruefung</th><th>Ergebnis</th></tr>
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
