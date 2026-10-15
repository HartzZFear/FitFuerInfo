<?php
// raum_loeschen.php
// Sicherheitsabfrage vor dem Loeschen eines Raums. Die eigentliche
// Loeschung passiert erst per POST (Bestaetigung), nicht schon beim
// Aufruf per GET-Link.
//
// Loeschen darf nur der Admin. Ein Raum mit Buchungen bleibt stehen: der
// Fremdschluessel buchung.raum_id steht auf RESTRICT. Hier wird das vorher
// geprueft und die Anzahl angezeigt, statt den Benutzer in einen
// Datenbankfehler laufen zu lassen.
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/funktionen.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/raum_rechte.php';

erfordere_login();
csrf_pruefen();

$meineId  = benutzer_id();
$istAdmin = ist_admin();

$raumId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($raumId < 1) {
    zugriff_verweigert_seite(
        'Es wurde kein gültiger Raum angegeben.',
        'raeume.php',
        'Zurück zu den Räumen'
    );
}

$raum = abfrage('SELECT * FROM raum WHERE id = ?', array($raumId))->fetch();

if (!$raum) {
    zugriff_verweigert_seite(
        'Dieser Raum existiert nicht oder wurde bereits gelöscht.',
        'raeume.php',
        'Zurück zu den Räumen'
    );
}

// Rechtepruefung serverseitig - der Aufruf per URL muss genauso scheitern
// wie der Klick auf einen Button, den es gar nicht gibt.
if (!raum_darf_loeschen($istAdmin)) {
    zugriff_verweigert_seite(
        'Räume kann nur der Systemverwalter löschen.',
        'raeume.php',
        'Zurück zu den Räumen'
    );
}

// Auch vergangene Buchungen zaehlen mit: der Fremdschluessel unterscheidet
// nicht, und die Belegung der letzten Wochen soll nachvollziehbar bleiben.
$anzahlBuchungen = (int) abfrage(
    'SELECT COUNT(*) FROM buchung WHERE raum_id = ?',
    array($raumId)
)->fetchColumn();

$fehler = '';
if ($anzahlBuchungen > 0) {
    $fehler = 'Der Raum kann nicht gelöscht werden, solange noch '
        . $anzahlBuchungen . ' Buchung(en) dafür bestehen. Zuerst die Buchungen entfernen.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fehler === '') {
    try {
        // raum_software und raum_bearbeiter haengen per ON DELETE CASCADE
        // am Raum und verschwinden automatisch mit.
        abfrage('DELETE FROM raum WHERE id = ?', array($raumId));
        header('Location: raeume.php');
        exit;
    } catch (PDOException $e) {
        $fehler = 'Der Raum kann nicht gelöscht werden, solange noch Buchungen dafür bestehen.';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Raum löschen – FitFürInfo</title>
<link rel="stylesheet" href="<?php echo h(asset_url('css/style.css')); ?>">
<link rel="stylesheet" href="<?php echo h(asset_url('css/formular.css')); ?>">
</head>
<body class="seite-raum_loeschen">

  <div class="kopf">
    <h1>FitFürInfo</h1>
    <p>Raum löschen</p>
    <svg class="welle" viewBox="0 0 1440 200" preserveAspectRatio="none">
      <defs>
        <linearGradient id="waveGradient" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stop-color="#1a8f9c"/>
          <stop offset="45%" stop-color="#2f6fb0"/>
          <stop offset="100%" stop-color="#e8792e"/>
        </linearGradient>
      </defs>
      <path fill="url(#waveGradient)" d="M0,80 C240,160 480,0 720,60 C960,120 1200,20 1440,90 L1440,0 L0,0 Z"/>
    </svg>
  </div>

  <div class="inhalt">

    <nav class="reiter-gruppe">
      <a href="<?php echo h(url('kurse')); ?>" class="reiter">Kurse</a>
      <a href="raeume.php" class="reiter active">Räume</a>
      <a href="belegung.php" class="reiter">Belegung</a>
      <?php if ($istAdmin): ?>
      <a href="benutzer.php" class="reiter">Benutzer</a>
      <?php endif; ?>
    </nav>

    <?php session_fehler_anzeigen(); ?>

    <div class="karte">
      <h2 class="karte-titel">Raum löschen</h2>

      <?php if ($fehler !== ''): ?>
      <div class="fehler"><?php echo h($fehler); ?></div>
      <p>
        <a class="link-belegung" href="buchungen.php?raum_id=<?php echo (int) $raumId; ?>">Belegung dieses Raums anzeigen</a>
      </p>
      <p><a class="link-abbrechen" href="raeume.php">Zurück zu den Räumen</a></p>
      <?php else: ?>
      <p class="karte-text">
        Soll der Raum <strong><?php echo h($raum['name']); ?></strong>
        mit <?php echo (int) $raum['arbeitsplaetze']; ?> Arbeitsplätzen wirklich gelöscht werden?
        Das kann nicht rückgängig gemacht werden.
      </p>
      <form method="post" action="raum_loeschen.php?id=<?php echo (int) $raumId; ?>">
        <?php csrf_feld(); ?>
        <div class="knopf-reihe">
          <button type="submit" class="knopf knopf-loeschen">Ja, endgültig löschen</button>
          <a class="link-abbrechen" href="raeume.php">Abbrechen</a>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>

</body>
</html>
