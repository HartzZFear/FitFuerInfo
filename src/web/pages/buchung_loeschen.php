<?php
// buchung_loeschen.php
// Sicherheitsabfrage vor dem Loeschen einer Buchung. Die eigentliche
// Loeschung passiert erst per POST (Bestaetigung), nicht schon beim
// Aufruf per GET-Link.
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../kurs_rechte.php';
require_once __DIR__ . '/../buchung_logik.php';

erfordere_login();

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
        header('Location: buchungen.php');
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
<style>
  :root {
    --teal: #1a8f9c;
    --blue: #2f6fb0;
    --orange: #e8792e;
    --card-bg: #ffffff;
    --page-bg: #eef3f4;
    --text-dark: #2b3a42;
    --text-muted: #7c8a91;
    --border: #dfe6e8;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    min-height: 100vh;
    font-family: "Segoe UI", Roboto, Arial, sans-serif;
    background: var(--page-bg);
  }
  .kopf {
    position: relative;
    background: linear-gradient(135deg, var(--teal), var(--blue));
    color: #fff;
    padding: 40px 20px 70px;
    text-align: center;
  }
  .kopf h1 { margin: 0 0 4px; font-size: 24px; font-weight: 700; }
  .kopf p { margin: 0; opacity: .9; font-size: 14px; }
  .kopf .welle { position: absolute; left: 0; bottom: -1px; width: 100%; height: 60px; display: block; }
  .inhalt { max-width: 520px; margin: 24px auto 40px; padding: 0 16px; }

  /* ---- Tab-Leiste: gleiche Optik wie die Tabs in kurse.php ---- */
  .tab-group {
    display: flex;
    gap: 16px;
    max-width: 380px;
    margin: 0 auto 20px;
  }
  .tab {
    flex: 1;
    text-align: center;
    padding: 10px 0;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-muted);
    text-decoration: none;
    cursor: pointer;
    border: 1px solid var(--border);
    background: #fbfcfc;
  }
  .tab:hover,
  .tab:focus-visible { border-color: var(--blue); color: var(--blue); }
  .tab.active {
    background: linear-gradient(90deg, var(--blue) 0%, var(--teal) 45%, var(--orange) 100%);
    color: #ffffff;
    border-color: transparent;
  }

  .karte {
    background: var(--card-bg);
    border-radius: 14px;
    box-shadow: 0 12px 28px rgba(20, 40, 50, .12);
    padding: 28px 28px 32px;
  }
  .karte-titel { margin: 0 0 16px; font-size: 20px; color: var(--text-dark); }
  .karte-text { margin: 0 0 22px; font-size: 14px; color: var(--text-muted); line-height: 1.5; }
  .knopf-reihe { display: flex; align-items: center; gap: 16px; }
  .knopf {
    padding: 11px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    color: #ffffff;
    cursor: pointer;
  }
  .knopf-loeschen { background: linear-gradient(90deg, var(--orange) 0%, #d9534f 100%); }
  .knopf-loeschen:hover { filter: brightness(1.05); }
  .link-abbrechen {
    font-size: 14px;
    color: var(--text-muted);
    text-decoration: none;
  }
  .link-abbrechen:hover { text-decoration: underline; }
  .fehler {
    margin: 0 0 20px;
    padding: 10px 14px;
    border-radius: 8px;
    background: #fdeceb;
    border: 1px solid #f0b3ae;
    color: #a13a2f;
    font-size: 13px;
  }
</style>
</head>
<body>

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

    <nav class="tab-group">
      <a href="kurse.php" class="tab">Kurse</a>
      <a href="raeume.php" class="tab">Räume</a>
      <a href="buchungen.php" class="tab active">Belegung</a>
    </nav>

    <div class="karte">
      <h2 class="karte-titel">Buchung löschen</h2>

      <?php if ($fehler !== ''): ?>
      <div class="fehler"><?php echo h($fehler); ?></div>
      <p><a class="link-abbrechen" href="buchungen.php">Zurück zur Belegung</a></p>
      <?php else: ?>
      <p class="karte-text">
        Soll die Buchung von <strong><?php echo h($buchung['kurs']); ?></strong>
        in <strong><?php echo h($buchung['raum']); ?></strong>
        <?php echo h(buchung_zeitraum_text($buchung['start'], $buchung['ende'])); ?>
        wirklich gelöscht werden? Das kann nicht rückgängig gemacht werden.
      </p>
      <form method="post" action="buchung_loeschen.php?id=<?php echo (int) $buchungId; ?>">
        <div class="knopf-reihe">
          <button type="submit" class="knopf knopf-loeschen">Ja, endgültig löschen</button>
          <a class="link-abbrechen" href="buchungen.php">Abbrechen</a>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>

</body>
</html>
