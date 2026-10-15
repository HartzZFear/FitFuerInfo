<?php
// kurs_loeschen.php
// Sicherheitsabfrage vor dem Loeschen eines Kurses. Die eigentliche
// Loeschung passiert erst per POST (Bestaetigung), nicht schon beim
// Aufruf per GET-Link.
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/funktionen.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/kurs_rechte.php';

erfordere_login();
csrf_pruefen();

$meineId  = benutzer_id();
$istAdmin = ist_admin();

$kursId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($kursId < 1) {
    zugriff_verweigert_seite('Es wurde kein gültiger Kurs angegeben.');
}

$kurs = abfrage('SELECT * FROM kurs WHERE id = ?', array($kursId))->fetch();

if (!$kurs) {
    zugriff_verweigert_seite('Dieser Kurs existiert nicht oder wurde bereits gelöscht.');
}
if (!kurs_darf_verwalten($kursId, $meineId, $istAdmin)) {
    zugriff_verweigert_seite('Sie sind nicht Eigentümer dieses Kurses und können ihn deshalb nicht löschen.');
}

$anzahlBuchungen = (int) abfrage('SELECT COUNT(*) FROM buchung WHERE kurs_id = ?', array($kursId))->fetchColumn();

$fehler = '';
if ($anzahlBuchungen > 0) {
    $fehler = 'Der Kurs kann nicht gelöscht werden, solange noch '
        . $anzahlBuchungen . ' Buchung(en) dafür bestehen. Zuerst die Buchungen entfernen.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fehler === '') {
    try {
        abfrage('DELETE FROM kurs WHERE id = ?', array($kursId));
        header('Location: ' . url('kurse'));
        exit;
    } catch (PDOException $e) {
        $fehler = 'Der Kurs kann nicht gelöscht werden, solange noch Buchungen dafür bestehen.';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kurs löschen – FitFürInfo</title>
<link rel="stylesheet" href="<?php echo h(asset_url('css/style.css')); ?>">
<link rel="stylesheet" href="<?php echo h(asset_url('css/formular.css')); ?>">
</head>
<body class="seite-kurs_loeschen">

  <div class="kopf">
    <h1>FitFürInfo</h1>
    <p>Kurs löschen</p>
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
    <div class="karte">
      <h2 class="karte-titel">Kurs löschen</h2>

      <?php if ($fehler !== ''): ?>
      <div class="fehler"><?php echo h($fehler); ?></div>
      <p><a class="link-abbrechen" href="<?php echo h(url('kurse')); ?>">Zurück zu den Kursen</a></p>
      <?php else: ?>
      <p class="karte-text">
        Soll der Kurs <strong><?php echo h($kurs['titel']); ?></strong> wirklich gelöscht werden?
        Das kann nicht rückgängig gemacht werden.
      </p>
      <form method="post" action="kurs_loeschen.php?id=<?php echo (int) $kursId; ?>">
        <?php csrf_feld(); ?>
        <div class="knopf-reihe">
          <button type="submit" class="knopf knopf-loeschen">Ja, endgültig löschen</button>
          <a class="link-abbrechen" href="<?php echo h(url('kurse')); ?>">Abbrechen</a>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>

</body>
</html>
