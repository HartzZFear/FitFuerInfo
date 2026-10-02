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

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../raum_rechte.php';

erfordere_login();

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
  .link-belegung {
    font-size: 13px;
    color: var(--blue);
    text-decoration: none;
  }
  .link-belegung:hover { text-decoration: underline; }
</style>
</head>
<body>

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

    <nav class="tab-group">
      <a href="kurse.php" class="tab">Kurse</a>
      <a href="raeume.php" class="tab active">Räume</a>
      <a href="buchungen.php" class="tab">Belegung</a>
    </nav>

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
