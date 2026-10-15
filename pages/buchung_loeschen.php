<?php
// buchung_loeschen.php
// Sicherheitsabfrage vor dem Loeschen einer Buchung. Die eigentliche
// Loeschung passiert erst per POST (Bestaetigung), nicht schon beim
// Aufruf per GET-Link.
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/funktionen.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/kurs_rechte.php';
require_once __DIR__ . '/../lib/buchung_logik.php';

erfordere_login();
csrf_pruefen();

$meineId  = benutzer_id();
$istAdmin = ist_admin();

$buchungId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($buchungId < 1) {
    zugriff_verweigert_seite(
        'Es wurde keine gültige Buchung angegeben.',
        'buchungen.php',
        'Zurück zur Belegung'
    );
}

$buchung = abfrage(
    'SELECT b.id, b.benutzer_id, b.start, b.ende, r.name AS raum, k.titel AS kurs
     FROM buchung b
     JOIN raum r ON r.id = b.raum_id
     JOIN kurs k ON k.id = b.kurs_id
     WHERE b.id = ?',
    array($buchungId)
)->fetch();

if (!$buchung) {
    zugriff_verweigert_seite(
        'Diese Buchung existiert nicht oder wurde bereits gelöscht.',
        'buchungen.php',
        'Zurück zur Belegung'
    );
}

// Rechtepruefung serverseitig - der Aufruf per URL muss genauso scheitern
// wie der Klick auf einen Button, den es gar nicht gibt.
if (!buchung_darf_verwalten($buchungId, $meineId, $istAdmin)) {
    zugriff_verweigert_seite(
        'Sie haben diese Buchung nicht angelegt und können sie deshalb nicht löschen.',
        'buchungen.php',
        'Zurück zur Belegung'
    );
}
if (!buchung_darf_loeschen($buchung, $meineId, $istAdmin)) {
    zugriff_verweigert_seite(
        'Diese Buchung liegt in der Vergangenheit. Nur der Systemverwalter kann sie noch löschen.',
        'buchungen.php',
        'Zurück zur Belegung'
    );
}

$fehler = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        abfrage('DELETE FROM buchung WHERE id = ?', array($buchungId));
        header('Location: ' . url('buchungen'));
        exit;
    } catch (PDOException $e) {
        $fehler = 'Die Buchung konnte nicht gelöscht werden. Bitte erneut versuchen.';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Buchung löschen – FitFürInfo</title>
<link rel="stylesheet" href="<?php echo h(asset_url('css/style.css')); ?>">
<link rel="stylesheet" href="<?php echo h(asset_url('css/formular.css')); ?>">
</head>
<body class="seite-buchung_loeschen">

  <div class="kopf">
    <h1>FitFürInfo</h1>
    <p>Buchung löschen</p>
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
      <a href="<?php echo h(url('raeume')); ?>" class="reiter">Räume</a>
      <a href="<?php echo h(url('belegung')); ?>" class="reiter active">Belegung</a>
      <?php if ($istAdmin): ?>
      <a href="<?php echo h(url('benutzer')); ?>" class="reiter">Benutzer</a>
      <?php endif; ?>
    </nav>

    <?php session_fehler_anzeigen(); ?>

    <div class="karte">
      <h2 class="karte-titel">Buchung löschen</h2>

      <?php if ($fehler !== ''): ?>
      <div class="fehler"><?php echo h($fehler); ?></div>
      <p><a class="link-abbrechen" href="<?php echo h(url('buchungen')); ?>">Zurück zur Belegung</a></p>
      <?php else: ?>
      <p class="karte-text">
        Soll die Buchung von <strong><?php echo h($buchung['kurs']); ?></strong>
        in <strong><?php echo h($buchung['raum']); ?></strong>
        <?php echo h(buchung_zeitraum_text($buchung['start'], $buchung['ende'])); ?>
        wirklich gelöscht werden? Das kann nicht rückgängig gemacht werden.
      </p>
      <form method="post" action="buchung_loeschen.php?id=<?php echo (int) $buchungId; ?>">
        <?php csrf_feld(); ?>
        <div class="knopf-reihe">
          <button type="submit" class="knopf knopf-loeschen">Ja, endgültig löschen</button>
          <a class="link-abbrechen" href="<?php echo h(url('buchungen')); ?>">Abbrechen</a>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>

</body>
</html>
