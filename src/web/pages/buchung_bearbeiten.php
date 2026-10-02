<?php
// buchung_bearbeiten.php
// Buchung anlegen (ohne ?id=) oder bearbeiten (mit ?id=N).
//
// Beim Anlegen koennen Kurs, Raum, Datum und Startzeit per GET vorbelegt
// werden (?raum_id=&kurs_id=&datum=&start=). Das nutzt spaeter der
// Belegungskalender, wenn man auf eine freie Zelle klickt.
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../kurs_rechte.php';
require_once __DIR__ . '/../buchung_logik.php';

erfordere_login();

$meineId  = benutzer_id();
$istAdmin = ist_admin();

$istBearbeiten = isset($_GET['id']) && $_GET['id'] !== '';
$buchungId     = $istBearbeiten ? (int) $_GET['id'] : null;

$buchung = null;

if ($istBearbeiten) {
    $buchung = abfrage('SELECT * FROM buchung WHERE id = ?', array($buchungId))->fetch();

    if (!$buchung) {
        zugriff_verweigert_seite(
            'Diese Buchung existiert nicht oder wurde bereits gelöscht.',
            'buchungen.php',
            'Zurück zur Belegung'
        );
    }
    // Rechtepruefung serverseitig - nicht nur durch Ausblenden der Buttons
    // in buchungen.php.
    if (!buchung_darf_verwalten($buchungId, $meineId, $istAdmin)) {
        zugriff_verweigert_seite(
            'Sie haben diese Buchung nicht angelegt und können sie deshalb nicht bearbeiten.',
            'buchungen.php',
            'Zurück zur Belegung'
        );
    }
    if (buchung_ist_vergangen($buchung['start'])) {
        zugriff_verweigert_seite(
            'Diese Buchung liegt in der Vergangenheit und kann nicht mehr bearbeitet werden.',
            'buchungen.php',
            'Zurück zur Belegung'
        );
    }
}

// Nur Kurse anbieten, fuer die der Benutzer buchen darf. Der Admin sieht alle.
$meineKurse = buchbare_kurse($meineId, $istAdmin);
$alleRaeume = abfrage('SELECT id, name, arbeitsplaetze FROM raum ORDER BY name')->fetchAll();
$alleSlots  = zeitslots();

// --- Vorbelegung der Felder ---------------------------------------------
if ($istBearbeiten) {
    $kursIdWert = (string) (int) $buchung['kurs_id'];
    $raumIdWert = (string) (int) $buchung['raum_id'];
    $datumWert  = substr($buchung['start'], 0, 10);
    $startWert  = substr($buchung['start'], 11, 5);
    $endeWert   = substr($buchung['ende'], 11, 5);
} else {
    $kursIdWert = isset($_GET['kurs_id']) ? (string) (int) $_GET['kurs_id'] : '';
    $raumIdWert = isset($_GET['raum_id']) ? (string) (int) $_GET['raum_id'] : '';
    $datumWert  = isset($_GET['datum'])   ? trim($_GET['datum'])            : date('Y-m-d');
    $startWert  = isset($_GET['start'])   ? trim($_GET['start'])            : '08:00';

    // Endzeit vorbelegen: eine Stunde nach dem Start, aber nie nach
    // BUCHUNG_TAG_ENDE. Der Benutzer kann sie danach frei aendern.
    $endeWert = BUCHUNG_TAG_ENDE;
    $startPos = array_search($startWert, $alleSlots, true);
    if ($startPos !== false && isset($alleSlots[$startPos + 2])) {
        $endeWert = $alleSlots[$startPos + 2];
    }
}

$fehler         = array();
$passendeRaeume = array();

// --- Speichern ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kursIdWert = isset($_POST['kurs_id']) ? trim($_POST['kurs_id']) : '';
    $raumIdWert = isset($_POST['raum_id']) ? trim($_POST['raum_id']) : '';
    $datumWert  = isset($_POST['datum'])   ? trim($_POST['datum'])   : '';
    $startWert  = isset($_POST['start'])   ? trim($_POST['start'])   : '';
    $endeWert   = isset($_POST['ende'])    ? trim($_POST['ende'])    : '';

    $kursId = (int) $kursIdWert;
    $raumId = (int) $raumIdWert;

    // Darf der Benutzer ueberhaupt fuer diesen Kurs buchen? Das Dropdown
    // zeigt nur erlaubte Kurse, aber ein manipuliertes Formular kann jede
    // beliebige ID schicken - deshalb hier noch einmal pruefen.
    if ($kursId < 1) {
        $fehler[] = 'Bitte einen Kurs auswählen.';
    } elseif (!kurs_darf_verwalten($kursId, $meineId, $istAdmin)) {
        zugriff_verweigert_seite(
            'Sie sind nicht Eigentümer dieses Kurses und können dafür keine Buchung anlegen.',
            'buchungen.php',
            'Zurück zur Belegung'
        );
    }

    if ($raumId < 1) {
        $fehler[] = 'Bitte einen Raum auswählen.';
    }

    $start = buchung_zeitpunkt($datumWert, $startWert);
    $ende  = buchung_zeitpunkt($datumWert, $endeWert);

    if ($start === null || $ende === null) {
        $fehler[] = 'Datum oder Uhrzeit sind ungültig. Bitte Datum, Start- und Endzeit erneut auswählen.';
    }

    if (empty($fehler)) {
        $db = db();
        $db->beginTransaction();

        try {
            // Erst raum, dann kurs sperren - immer in dieser Reihenfolge,
            // damit sich zwei gleichzeitige Buchungen nicht gegenseitig
            // blockieren (Deadlock). Die Sperre haelt bis zum COMMIT und
            // verhindert, dass zwei Anfragen beide die Pruefung bestehen
            // und sich anschliessend ueberschneiden.
            abfrage('SELECT id FROM raum WHERE id = ? FOR UPDATE', array($raumId));
            abfrage('SELECT id FROM kurs WHERE id = ? FOR UPDATE', array($kursId));

            // Beim Bearbeiten die eigene ID ausschliessen, sonst kollidiert
            // die Buchung bei den Regeln 2 und 3 mit sich selbst.
            $fehler = buchung_pruefen($raumId, $kursId, $start, $ende, $istBearbeiten ? $buchungId : null);

            if (empty($fehler)) {
                if ($istBearbeiten) {
                    // benutzer_id bleibt unveraendert: die Buchung gehoert
                    // weiterhin dem, der sie angelegt hat.
                    abfrage(
                        'UPDATE buchung SET raum_id = ?, kurs_id = ?, start = ?, ende = ? WHERE id = ?',
                        array($raumId, $kursId, $start, $ende, $buchungId)
                    );
                } else {
                    abfrage(
                        'INSERT INTO buchung (raum_id, kurs_id, benutzer_id, start, ende) VALUES (?, ?, ?, ?, ?)',
                        array($raumId, $kursId, $meineId, $start, $ende)
                    );
                }

                $db->commit();
                header('Location: buchungen.php');
                exit;
            }

            $db->rollBack();
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $fehler[] = 'Die Buchung konnte nicht gespeichert werden. Bitte erneut versuchen.';
        }
    }

    // Wenn Software (Regel 4) oder Platz (Regel 5) das Problem waren, ist
    // ein anderer Raum die Loesung - deshalb hier die Liste dazu anzeigen.
    if (!empty($fehler) && $kursId > 0 && $raumId > 0) {
        $raumZeile = abfrage('SELECT arbeitsplaetze FROM raum WHERE id = ?', array($raumId))->fetch();
        $kursZeile = abfrage('SELECT max_teilnehmer FROM kurs WHERE id = ?', array($kursId))->fetch();

        if ($raumZeile && $kursZeile) {
            $softwareVerletzt = count(buchung_fehlende_software($kursId, $raumId)) > 0;
            $platzVerletzt    = (int) $kursZeile['max_teilnehmer'] > (int) $raumZeile['arbeitsplaetze'];

            if ($softwareVerletzt || $platzVerletzt) {
                $passendeRaeume = passende_raeume($kursId);
            }
        }
    }
}

$seitenTitel = $istBearbeiten ? 'Buchung bearbeiten' : 'Neue Buchung anlegen';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo h($seitenTitel); ?> – FitFürInfo</title>
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
  .karte-titel { margin: 0 0 18px; font-size: 20px; color: var(--text-dark); }
  .karte-text { margin: 0 0 22px; font-size: 14px; color: var(--text-muted); line-height: 1.5; }
  label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-dark);
    margin: 16px 0 6px;
  }
  label:first-of-type { margin-top: 0; }
  input[type="date"],
  select {
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
  select:focus {
    outline: none;
    border-color: var(--blue);
    background: #ffffff;
  }
  .zeit-reihe { display: flex; gap: 16px; }
  .zeit-reihe > div { flex: 1; }
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
  .fehler li + li { margin-top: 4px; }
  .hinweis {
    margin: 0 0 18px;
    padding: 10px 14px;
    border-radius: 8px;
    background: #eaf4f5;
    border: 1px solid #b9dde1;
    color: #17636c;
    font-size: 13px;
  }
  .regel-hinweis {
    margin: 18px 0 0;
    font-size: 12px;
    color: var(--text-muted);
    line-height: 1.5;
  }
</style>
</head>
<body>

  <div class="kopf">
    <h1>FitFürInfo</h1>
    <p><?php echo h($seitenTitel); ?></p>
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
      <h2 class="karte-titel"><?php echo h($seitenTitel); ?></h2>

      <?php if (!empty($fehler)): ?>
      <div class="fehler">
        <ul>
          <?php foreach ($fehler as $einzelnerFehler): ?>
          <li><?php echo h($einzelnerFehler); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <?php if (!empty($passendeRaeume)): ?>
      <div class="hinweis">
        Passende Räume für diesen Kurs:
        <?php
          $namen = array();
          foreach ($passendeRaeume as $passenderRaum) {
              $namen[] = $passenderRaum['name'] . ' (' . (int) $passenderRaum['arbeitsplaetze'] . ' Plätze)';
          }
          echo h(implode(', ', $namen));
        ?>
      </div>
      <?php endif; ?>

      <?php if (empty($meineKurse)): ?>
      <p class="karte-text">
        Sie sind für keinen Kurs als Eigentümer eingetragen und können deshalb keine Buchung anlegen.
      </p>
      <p><a class="link-abbrechen" href="buchungen.php">Zurück zur Belegung</a></p>
      <?php else: ?>

      <form method="post" action="buchung_bearbeiten.php<?php echo $istBearbeiten ? '?id=' . (int) $buchungId : ''; ?>">
        <label for="kurs_id">Kurs</label>
        <select id="kurs_id" name="kurs_id" required>
          <option value="">– bitte wählen –</option>
          <?php foreach ($meineKurse as $kursOption): ?>
          <option value="<?php echo (int) $kursOption['id']; ?>"
            <?php echo ((string) (int) $kursOption['id'] === $kursIdWert) ? 'selected' : ''; ?>>
            <?php echo h($kursOption['titel']); ?>
            (<?php echo (int) $kursOption['max_teilnehmer']; ?> Teilnehmer)
          </option>
          <?php endforeach; ?>
        </select>

        <label for="raum_id">Raum</label>
        <select id="raum_id" name="raum_id" required>
          <option value="">– bitte wählen –</option>
          <?php foreach ($alleRaeume as $raumOption): ?>
          <option value="<?php echo (int) $raumOption['id']; ?>"
            <?php echo ((string) (int) $raumOption['id'] === $raumIdWert) ? 'selected' : ''; ?>>
            <?php echo h($raumOption['name']); ?>
            (<?php echo (int) $raumOption['arbeitsplaetze']; ?> Plätze)
          </option>
          <?php endforeach; ?>
        </select>

        <label for="datum">Datum</label>
        <input type="date" id="datum" name="datum" value="<?php echo h($datumWert); ?>"
               min="<?php echo date('Y-m-d'); ?>" required>

        <div class="zeit-reihe">
          <div>
            <label for="start">Startzeit</label>
            <select id="start" name="start" required>
              <?php foreach ($alleSlots as $slot): ?>
              <option value="<?php echo h($slot); ?>" <?php echo ($slot === $startWert) ? 'selected' : ''; ?>>
                <?php echo h($slot); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="ende">Endzeit</label>
            <select id="ende" name="ende" required>
              <?php foreach ($alleSlots as $slot): ?>
              <option value="<?php echo h($slot); ?>" <?php echo ($slot === $endeWert) ? 'selected' : ''; ?>>
                <?php echo h($slot); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="knopf-reihe">
          <button type="submit" class="knopf">Speichern</button>
          <a class="link-abbrechen" href="buchungen.php">Abbrechen</a>
        </div>
      </form>

      <p class="regel-hinweis">
        Buchbar sind Montag bis Freitag von <?php echo h(BUCHUNG_TAG_START); ?> bis
        <?php echo h(BUCHUNG_TAG_ENDE); ?> Uhr in halben Stunden. Raum und Kurs müssen im
        gewählten Zeitraum frei sein, der Raum muss die Software des Kurses haben und
        genug Arbeitsplätze bieten.
      </p>

      <?php endif; ?>
    </div>
  </div>

</body>
</html>
