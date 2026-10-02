<?php
// kurs_loeschen.php
// Sicherheitsabfrage vor dem Loeschen eines Kurses. Die eigentliche
// Loeschung passiert erst per POST (Bestaetigung), nicht schon beim
// Aufruf per GET-Link.
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../kurs_rechte.php';

erfordere_login();

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
        header('Location: kurse.php');
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
      <p><a class="link-abbrechen" href="kurse.php">Zurück zu den Kursen</a></p>
      <?php else: ?>
      <p class="karte-text">
        Soll der Kurs <strong><?php echo h($kurs['titel']); ?></strong> wirklich gelöscht werden?
        Das kann nicht rückgängig gemacht werden.
      </p>
      <form method="post" action="kurs_loeschen.php?id=<?php echo (int) $kursId; ?>">
        <div class="knopf-reihe">
          <button type="submit" class="knopf knopf-loeschen">Ja, endgültig löschen</button>
          <a class="link-abbrechen" href="kurse.php">Abbrechen</a>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>

</body>
</html>
