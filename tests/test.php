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

// --- 3b. config/app.php (im Repo) ---------------------------------------
$appOk = file_exists(__DIR__ . '/../config/app.php');
pruefe(
    'config/app.php vorhanden',
    $appOk,
    $appOk ? 'gefunden' : 'fehlt – Datei aus dem Repo holen (git pull)'
);

// Aeltere config.php-Dateien definieren ASSET_VERSION bzw. PW_MIN_LAENGE
// noch selbst. Dann meldet PHP "Constant ... already defined" und es gilt
// still der alte Wert aus config.php statt dem aus config/app.php.
if ($configOk) {
    $configText = file_get_contents(__DIR__ . '/../config/config.php');
    foreach (array('ASSET_VERSION', 'PW_MIN_LAENGE') as $konstante) {
        $alteZeile = preg_match('/define\s*\(\s*.' . $konstante . './', $configText) === 1;
        pruefe(
            'config.php ohne ' . $konstante,
            !$alteZeile,
            $alteZeile
                ? 'veraltet – die Zeile mit ' . $konstante . ' in config/config.php loeschen, '
                    . 'der Wert steht jetzt in config/app.php'
                : 'in Ordnung'
        );
    }
}

$db = null;

// --- 4. Datenbankverbindung -------------------------------------------
if ($configOk && $appOk && $pdoOk) {
    require_once __DIR__ . '/../lib/db.php';
    try {
        $db = db();
        pruefe('Datenbankverbindung', true, 'verbunden mit ' . DB_NAME . ' auf ' . DB_HOST);
    } catch (Exception $e) {
        pruefe('Datenbankverbindung', false, $e->getMessage());
    }

    // config/app.php ist jetzt geladen (ueber lib/db.php).
    pruefe(
        'ASSET_VERSION in config/app.php',
        defined('ASSET_VERSION'),
        defined('ASSET_VERSION')
            ? 'Version ' . ASSET_VERSION
            : 'fehlt – config/app.php aus dem Repo holen (git pull)'
    );

    // Whitelist fuer index.php: zu jedem Namen muss es pages/<name>.php geben.
    $seitenOk     = defined('ERLAUBTE_SEITEN') && is_array(ERLAUBTE_SEITEN);
    $seitenFehlen = array();
    if ($seitenOk) {
        foreach (ERLAUBTE_SEITEN as $name) {
            if (!file_exists(__DIR__ . '/../pages/' . $name . '.php')) {
                $seitenFehlen[] = $name;
            }
        }
    }
    pruefe(
        'ERLAUBTE_SEITEN in config/app.php',
        $seitenOk && empty($seitenFehlen),
        !$seitenOk
            ? 'fehlt – config/app.php aus dem Repo holen (git pull)'
            : (empty($seitenFehlen)
                ? count(ERLAUBTE_SEITEN) . ' Seiten, alle Dateien in pages/ vorhanden'
                : 'keine Datei in pages/ fuer: ' . implode(', ', $seitenFehlen))
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
<title>Selbsttest – FitFürInfo</title>
<?php /* Relativ und ohne ?v=: Die Testseite muss auch laufen, wenn
         config.php (BASE_URL, ASSET_VERSION) noch fehlt. */ ?>
<link rel="stylesheet" href="test.css">
</head>
<body>

<h1>Selbsttest der lokalen Einrichtung</h1>
<p class="sub">FitFürInfo – Kurs- und Raumverwaltung</p>

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
