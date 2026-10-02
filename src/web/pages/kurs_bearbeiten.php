<?php
// kurs_bearbeiten.php
// Kurs anlegen (ohne ?id=) oder bearbeiten (mit ?id=N).
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../kurs_rechte.php';

erfordere_login();

$meineId  = benutzer_id();
$istAdmin = ist_admin();

$istBearbeiten = isset($_GET['id']) && $_GET['id'] !== '';
$kursId        = $istBearbeiten ? (int) $_GET['id'] : null;

$kurs = null;

if ($istBearbeiten) {
    $kurs = abfrage('SELECT * FROM kurs WHERE id = ?', array($kursId))->fetch();

    if (!$kurs) {
        zugriff_verweigert_seite('Dieser Kurs existiert nicht oder wurde bereits gelöscht.');
    }
    if (!kurs_darf_verwalten($kursId, $meineId, $istAdmin)) {
        zugriff_verweigert_seite('Sie sind nicht Eigentümer dieses Kurses und können ihn deshalb nicht bearbeiten.');
    }
}

$alleSoftware  = abfrage('SELECT id, name FROM software ORDER BY name')->fetchAll();
$alleBenutzer  = $istAdmin
    ? abfrage('SELECT id, name FROM benutzer WHERE aktiv = 1 ORDER BY name')->fetchAll()
    : array();

$titel                   = $istBearbeiten ? $kurs['titel'] : '';
$beschreibung             = ($istBearbeiten && $kurs['beschreibung'] !== null) ? $kurs['beschreibung'] : '';
$maxTeilnehmer            = $istBearbeiten ? $kurs['max_teilnehmer'] : '';
$ausgewaehlteSoftwareIds  = array();
$ausgewaehlteEigentuemerIds = array();

if ($istBearbeiten) {
    $zeilen = abfrage('SELECT software_id FROM kurs_software WHERE kurs_id = ?', array($kursId))->fetchAll();
    foreach ($zeilen as $zeile) {
        $ausgewaehlteSoftwareIds[] = (int) $zeile['software_id'];
    }

    if ($istAdmin) {
        $zeilen = abfrage('SELECT benutzer_id FROM kurs_eigentuemer WHERE kurs_id = ?', array($kursId))->fetchAll();
        foreach ($zeilen as $zeile) {
            $ausgewaehlteEigentuemerIds[] = (int) $zeile['benutzer_id'];
        }
    }
}

$fehler = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titel               = isset($_POST['titel']) ? trim($_POST['titel']) : '';
    $beschreibung         = isset($_POST['beschreibung']) ? trim($_POST['beschreibung']) : '';
    $maxTeilnehmerEingabe = isset($_POST['max_teilnehmer']) ? trim($_POST['max_teilnehmer']) : '';

    $ausgewaehlteSoftwareIds = array();
    if (isset($_POST['software']) && is_array($_POST['software'])) {
        foreach ($_POST['software'] as $sid) {
            $ausgewaehlteSoftwareIds[] = (int) $sid;
        }
    }

    if ($istAdmin) {
        $ausgewaehlteEigentuemerIds = array();
        if (isset($_POST['eigentuemer']) && is_array($_POST['eigentuemer'])) {
            foreach ($_POST['eigentuemer'] as $eid) {
                $ausgewaehlteEigentuemerIds[] = (int) $eid;
            }
        }
    }

    if ($titel === '') {
        $fehler[] = 'Bitte einen Kurstitel eingeben.';
    }

    if ($maxTeilnehmerEingabe === '' || !ctype_digit($maxTeilnehmerEingabe) || (int) $maxTeilnehmerEingabe < 1) {
        $fehler[] = 'Die maximale Teilnehmerzahl muss eine ganze Zahl von mindestens 1 sein.';
    } else {
        $maxTeilnehmer = (int) $maxTeilnehmerEingabe;
    }

    if (empty($fehler)) {
        $beschreibungWert = $beschreibung !== '' ? $beschreibung : null;

        if ($istBearbeiten) {
            abfrage(
                'UPDATE kurs SET titel = ?, beschreibung = ?, max_teilnehmer = ? WHERE id = ?',
                array($titel, $beschreibungWert, $maxTeilnehmer, $kursId)
            );
        } else {
            abfrage(
                'INSERT INTO kurs (titel, beschreibung, max_teilnehmer, ersteller_id) VALUES (?, ?, ?, ?)',
                array($titel, $beschreibungWert, $maxTeilnehmer, $meineId)
            );
            $kursId = (int) db()->lastInsertId();
        }

        abfrage('DELETE FROM kurs_software WHERE kurs_id = ?', array($kursId));
        foreach ($ausgewaehlteSoftwareIds as $sid) {
            abfrage('INSERT INTO kurs_software (kurs_id, software_id) VALUES (?, ?)', array($kursId, $sid));
        }

        if ($istAdmin) {
            abfrage('DELETE FROM kurs_eigentuemer WHERE kurs_id = ?', array($kursId));
            foreach ($ausgewaehlteEigentuemerIds as $eid) {
                abfrage('INSERT INTO kurs_eigentuemer (kurs_id, benutzer_id) VALUES (?, ?)', array($kursId, $eid));
            }
        }

        header('Location: kurse.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $istBearbeiten ? 'Kurs bearbeiten' : 'Neuen Kurs anlegen'; ?> – FitFürInfo</title>
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
  .inhalt { max-width: 640px; margin: 24px auto 40px; padding: 0 16px; }
  .karte {
    background: var(--card-bg);
    border-radius: 14px;
    box-shadow: 0 12px 28px rgba(20, 40, 50, .12);
    padding: 28px 28px 32px;
  }
  .karte-titel { margin: 0 0 18px; font-size: 20px; color: var(--text-dark); }
  label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-dark);
    margin: 16px 0 6px;
  }
  label:first-of-type { margin-top: 0; }
  input[type="text"],
  input[type="number"],
  textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    color: var(--text-dark);
    background: #fbfcfc;
  }
  input:focus,
  textarea:focus {
    outline: none;
    border-color: var(--blue);
    background: #ffffff;
  }
  textarea { resize: vertical; min-height: 90px; }
  fieldset {
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 12px 14px;
    margin: 18px 0 0;
  }
  legend {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-dark);
    padding: 0 6px;
  }
  .checkbox-liste {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 18px;
  }
  .checkbox-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: normal;
    color: var(--text-dark);
    margin: 0;
  }
  .checkbox-item input { width: auto; }
  .knopf-reihe {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-top: 26px;
  }
  .knopf {
    padding: 11px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    color: #ffffff;
    cursor: pointer;
    background: linear-gradient(90deg, var(--blue) 0%, var(--teal) 100%);
  }
  .knopf:hover { filter: brightness(1.05); }
  .link-abbrechen {
    font-size: 14px;
    color: var(--text-muted);
    text-decoration: none;
  }
  .link-abbrechen:hover { text-decoration: underline; }
  .fehler {
    margin: 0 0 18px;
    padding: 10px 14px;
    border-radius: 8px;
    background: #fdeceb;
    border: 1px solid #f0b3ae;
    color: #a13a2f;
    font-size: 13px;
  }
  .fehler ul { margin: 0; padding-left: 18px; }
</style>
</head>
<body>

  <div class="kopf">
    <h1>FitFürInfo</h1>
    <p><?php echo $istBearbeiten ? 'Kurs bearbeiten' : 'Neuen Kurs anlegen'; ?></p>
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
      <h2 class="karte-titel"><?php echo $istBearbeiten ? 'Kurs bearbeiten' : 'Neuen Kurs anlegen'; ?></h2>

      <?php if (!empty($fehler)): ?>
      <div class="fehler">
        <ul>
          <?php foreach ($fehler as $einzelnerFehler): ?>
          <li><?php echo h($einzelnerFehler); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <form method="post" action="kurs_bearbeiten.php<?php echo $istBearbeiten ? '?id=' . (int) $kursId : ''; ?>">
        <label for="titel">Titel</label>
        <input type="text" id="titel" name="titel" value="<?php echo h($titel); ?>" required>

        <label for="beschreibung">Beschreibung</label>
        <textarea id="beschreibung" name="beschreibung"><?php echo h($beschreibung); ?></textarea>

        <label for="max_teilnehmer">Maximale Teilnehmerzahl</label>
        <input type="number" id="max_teilnehmer" name="max_teilnehmer" min="1" value="<?php echo h($maxTeilnehmer); ?>" required>

        <fieldset>
          <legend>Softwarepakete</legend>
          <div class="checkbox-liste">
            <?php foreach ($alleSoftware as $software): ?>
            <label class="checkbox-item">
              <input type="checkbox" name="software[]" value="<?php echo (int) $software['id']; ?>"
                <?php echo in_array((int) $software['id'], $ausgewaehlteSoftwareIds, true) ? 'checked' : ''; ?>>
              <?php echo h($software['name']); ?>
            </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <?php if ($istAdmin): ?>
        <fieldset>
          <legend>Eigentümer</legend>
          <div class="checkbox-liste">
            <?php foreach ($alleBenutzer as $benutzer): ?>
            <label class="checkbox-item">
              <input type="checkbox" name="eigentuemer[]" value="<?php echo (int) $benutzer['id']; ?>"
                <?php echo in_array((int) $benutzer['id'], $ausgewaehlteEigentuemerIds, true) ? 'checked' : ''; ?>>
              <?php echo h($benutzer['name']); ?>
            </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <?php endif; ?>

        <div class="knopf-reihe">
          <button type="submit" class="knopf">Speichern</button>
          <a class="link-abbrechen" href="kurse.php">Abbrechen</a>
        </div>
      </form>
    </div>
  </div>

</body>
</html>
