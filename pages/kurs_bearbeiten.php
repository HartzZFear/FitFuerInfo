<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// kurs_bearbeiten (Aufruf: index.php?seite=kurs_bearbeiten)
// Kurs anlegen (ohne ?id=) oder bearbeiten (mit ?id=N).
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

erfordere_login();
csrf_pruefen();

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

        header('Location: ' . url('kurse'));
        exit;
    }
}

$seitentitel   = $istBearbeiten ? 'Kurs bearbeiten' : 'Neuen Kurs anlegen';
$seitenCss     = array('formular.css');
$ohneKopfzeile = true;
?>
<?php formular_kopf($seitentitel); ?>

  <div class="inhalt">
    <div class="karte">
      <h2 class="karte-titel"><?php echo h($seitentitel); ?></h2>

      <?php if (!empty($fehler)): ?>
      <div class="fehler">
        <ul>
          <?php foreach ($fehler as $einzelnerFehler): ?>
          <li><?php echo h($einzelnerFehler); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <form method="post" action="<?php echo h(url('kurs_bearbeiten', $istBearbeiten ? array('id' => (int) $kursId) : array())); ?>">
        <?php csrf_feld(); ?>
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
          <a class="link-abbrechen" href="<?php echo h(url('kurse')); ?>">Abbrechen</a>
        </div>
      </form>
    </div>
  </div>
