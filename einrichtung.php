<?php
/**
 * Einrichtung auf einem frischen Rechner: legt config/config.php an und
 * importiert src/db/schema.sql und seed.sql (setzt alle Daten zurueck).
 *
 * Aufruf: http://localhost/fitfuerinfo/einrichtung.php
 *
 * Laeuft als einzige Seite NICHT ueber index.php: Vor der Einrichtung gibt
 * es weder config.php noch die Datenbank, index.php bzw. lib/db.php wuerden
 * dann abbrechen. Deshalb kein lib/db.php und kein BASE_URL - CSS, Logo und
 * Links sind relativ zu dieser Datei. Die Funktionen stehen in
 * lib/einrichtung_logik.php.
 *
 * Kein JavaScript: Das Formular schickt ein POST, PHP fuehrt alle Schritte
 * in dieser einen Anfrage aus und zeigt danach dieselbe Seite mit Ergebnis.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/lib/ausgabe.php';
require_once __DIR__ . '/lib/einrichtung_logik.php';
require_once __DIR__ . '/templates/bausteine.php';

// Nur vom eigenen Rechner aus - bei GET und POST (Begruendung bei
// einrichtung_erlaubt()).
if (!einrichtung_erlaubt()) {
    http_response_code(403);
    exit('Die Einrichtung ist nur direkt auf diesem Rechner über http://localhost/ erlaubt.');
}

$ausgefuehrt = $_SERVER['REQUEST_METHOD'] === 'POST';
$schritte    = $ausgefuehrt ? einrichtung_ausfuehren() : einrichtung_schritte();
$erfolg      = $schritte[count($schritte) - 1]['status'] === 'erledigt';

// Zeichen im Kreis vor jedem Schritt (nicht ausgefuehrt: die Nummer) und
// der Status in Worten fuer Screenreader (das Zeichen allein sagt ihnen nichts).
$zeichen    = array('erledigt' => '✓', 'fehler' => '✗');
$statusText = array('erledigt' => 'erledigt', 'fehler' => 'Fehler', 'offen' => 'nicht ausgeführt');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Einrichtung – FitFürInfo</title>
<link rel="stylesheet" href="assets/css/style.css?v=<?php echo h(ASSET_VERSION); ?>">
<link rel="stylesheet" href="assets/css/einrichtung.css?v=<?php echo h(ASSET_VERSION); ?>">
</head>
<?php /* seite-login: gleicher Aufbau wie die Anmeldung (Welle, Karte in
         der Mitte) - das CSS dafuer steht schon in style.css. */ ?>
<body class="seite-login seite-einrichtung">
  <div class="login-welle" aria-hidden="true">
<?php welle_svg(); ?>
  </div>

  <main class="login-card">
    <div class="logo">
      <img class="logo-mark" src="assets/imgs/logo.png" alt="">
      <span class="logo-text"><span class="fit">FitFür</span><span class="info">Info</span></span>
    </div>
    <p class="logo-subtitle">Einrichtung</p>

<?php if (!$erfolg): ?>
    <p class="einrichtung-text">Legt die Datenbank fitfuerinfo mit Testdaten an. Vorhandene Daten werden dabei gelöscht.</p>
<?php endif; ?>

    <ol class="schritte">
<?php foreach ($schritte as $i => $schritt): ?>
      <li class="schritt schritt-<?php echo h($schritt['status']); ?>">
        <span class="schritt-zeichen" aria-hidden="true"><?php echo h($schritt['status'] === 'offen' ? $i + 1 : $zeichen[$schritt['status']]); ?></span>
        <span class="schritt-text">
          <span class="schritt-titel"><?php echo h($schritt['titel']); ?><span class="visually-hidden"> – <?php echo h($statusText[$schritt['status']]); ?></span></span>
<?php if ($schritt['meldung'] !== ''): ?>
          <span class="schritt-meldung"><?php echo h($schritt['meldung']); ?></span>
<?php endif; ?>
        </span>
      </li>
<?php endforeach; ?>
    </ol>

<?php if ($erfolg): ?>
    <div class="einrichtung-erfolg">
      Die Einrichtung ist abgeschlossen. Testzugang: <strong>admin</strong> / <strong>test1</strong>
    </div>
    <a href="index.php" class="btn-login">Zur Anmeldung</a>
<?php else: ?>
    <form method="post" action="einrichtung.php">
      <button type="submit" class="btn-login"><?php echo $ausgefuehrt ? 'Erneut versuchen' : 'Einrichtung starten'; ?></button>
    </form>
<?php endif; ?>
  </main>
</body>
</html>
