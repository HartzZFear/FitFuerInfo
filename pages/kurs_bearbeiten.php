<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// kurs_bearbeiten (Aufruf: index.php?seite=kurs_bearbeiten)
// Kurs anlegen (ohne ?id=) oder bearbeiten (mit ?id=N).
//
// Wer die Teilnehmerzahl erhoeht oder Software hinzufuegt, kann damit
// bereits bestehende Buchungen ungueltig machen (Buchungsregeln 4 und 5).
// Das prueft kurs_speichern() vor dem Commit - siehe
// kurs_konflikte_mit_buchungen().
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
    $kurs = kurs_laden($kursId);

    if (!$kurs) {
        zugriff_verweigert_seite('Dieser Kurs existiert nicht oder wurde bereits gelöscht.');
    }
    if (!kurs_darf_verwalten($kursId, $meineId, $istAdmin)) {
        zugriff_verweigert_seite('Sie sind nicht Eigentümer dieses Kurses und können ihn deshalb nicht bearbeiten.');
    }
}

$alleSoftware  = software_alle();
$alleBenutzer  = $istAdmin ? benutzer_aktive() : array();

$titel                   = $istBearbeiten ? $kurs['titel'] : '';
$beschreibung             = ($istBearbeiten && $kurs['beschreibung'] !== null) ? $kurs['beschreibung'] : '';
$maxTeilnehmer            = $istBearbeiten ? $kurs['max_teilnehmer'] : '';
$ausgewaehlteSoftwareIds  = $istBearbeiten ? kurs_software_ids($kursId) : array();
$ausgewaehlteEigentuemerIds = ($istBearbeiten && $istAdmin) ? kurs_eigentuemer_eintraege($kursId) : array();

$fehler       = array();
$hatKonflikte = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titel               = eingabe_text($_POST, 'titel');
    $beschreibung         = eingabe_text($_POST, 'beschreibung');
    $maxTeilnehmerEingabe = eingabe_text($_POST, 'max_teilnehmer');

    $ausgewaehlteSoftwareIds = formular_ids('software');
    if ($istAdmin) {
        $ausgewaehlteEigentuemerIds = formular_ids('eigentuemer');
    }

    // Gueltige Teilnehmerzahl uebernehmen; bei ungueltiger Eingabe bleibt
    // im Feld der bisherige Wert stehen.
    $zahl = kurs_teilnehmerzahl($maxTeilnehmerEingabe);
    if ($zahl !== null) {
        $maxTeilnehmer = $zahl;
    }

    $ergebnis = kurs_speichern($kursId, array(
        'titel'          => $titel,
        'beschreibung'   => $beschreibung,
        'max_teilnehmer' => $maxTeilnehmerEingabe,
        'software'       => $ausgewaehlteSoftwareIds,
        'eigentuemer'    => $ausgewaehlteEigentuemerIds,
    ), $meineId, $istAdmin);

    if ($ergebnis === true) {
        header('Location: ' . url('kurse'));
        exit;
    }
    $fehler       = $ergebnis['fehler'];
    $hatKonflikte = $ergebnis['konflikte'];
}

$seitentitel   = $istBearbeiten ? 'Kurs bearbeiten' : 'Neuen Kurs anlegen';
$seitenCss     = array('formular.css');
$ohneKopfzeile = true;
?>
<?php formular_kopf($seitentitel); ?>

  <div class="inhalt">
    <div class="karte">
      <h2 class="karte-titel"><?php echo h($seitentitel); ?></h2>

      <?php echo fehler_liste($fehler); ?>

      <?php /* Bei Konflikten mit bestehenden Buchungen hilft der Blick auf die
               Termine des Kurses - deshalb dann den Link dorthin einblenden. */ ?>
      <?php if ($hatKonflikte && $kursId !== null): ?>
      <?php echo konflikt_hinweis('Buchungen dieses Kurses anzeigen', url('buchungen', array('kurs_id' => (int) $kursId))); ?>
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

        <?php echo knopf_reihe('Speichern', 'kurse'); ?>
      </form>
    </div>
  </div>
