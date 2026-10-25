<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// raum_bearbeiten (Aufruf: index.php?seite=raum_bearbeiten)
// Raum anlegen (ohne ?id=) oder bearbeiten (mit ?id=N).
//
// Anlegen darf nur der Admin. Bearbeiten duerfen der Admin und die in
// raum_bearbeiter eingetragenen Mitarbeiter; die Bearbeiterliste selbst
// aendert nur der Admin - sonst koennte man sich gegenseitig aussperren
// oder sich selbst Zustaendigkeiten geben.
//
// Wer Arbeitsplaetze reduziert oder Software entfernt, kann damit bereits
// bestehende Buchungen ungueltig machen (Buchungsregeln 4 und 5). Das prueft
// raum_speichern() vor dem Commit - siehe raum_konflikte_mit_buchungen().
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

erfordere_login();
csrf_pruefen();

$meineId  = benutzer_id();
$istAdmin = ist_admin();

$istBearbeiten = isset($_GET['id']) && $_GET['id'] !== '';
$raumId        = $istBearbeiten ? (int) $_GET['id'] : null;

$raum = null;

if ($istBearbeiten) {
    $raum = raum_laden($raumId);

    if (!$raum) {
        zugriff_verweigert_seite(
            'Dieser Raum existiert nicht oder wurde bereits gelöscht.',
            'raeume',
            'Zurück zu den Räumen'
        );
    }
    // Rechtepruefung serverseitig - nicht nur durch Ausblenden der Buttons
    // in raeume.php.
    if (!raum_darf_bearbeiten($raumId, $meineId, $istAdmin)) {
        zugriff_verweigert_seite(
            'Sie sind für diesen Raum nicht als Bearbeiter eingetragen und können ihn deshalb nicht bearbeiten.',
            'raeume',
            'Zurück zu den Räumen'
        );
    }
} elseif (!raum_darf_anlegen($istAdmin)) {
    zugriff_verweigert_seite(
        'Neue Räume kann nur der Systemverwalter anlegen.',
        'raeume',
        'Zurück zu den Räumen'
    );
}

$alleSoftware = software_alle();
$alleBenutzer = $istAdmin ? benutzer_aktive() : array();

// --- Vorbelegung der Felder ---------------------------------------------
$name                     = $istBearbeiten ? $raum['name'] : '';
$arbeitsplaetze           = $istBearbeiten ? $raum['arbeitsplaetze'] : '';
$ausgewaehlteSoftwareIds  = $istBearbeiten ? raum_software_ids($raumId) : array();
$ausgewaehlteBearbeiterIds = ($istBearbeiten && $istAdmin) ? raum_bearbeiter_ids($raumId) : array();

$fehler        = array();
$hatKonflikte  = false;

// --- Speichern ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name                 = eingabe_text($_POST, 'name');
    $arbeitsplaetzeEingabe = eingabe_text($_POST, 'arbeitsplaetze');

    $ausgewaehlteSoftwareIds = formular_ids('software');

    // Die Bearbeiterliste kommt nur vom Admin - ein manipuliertes Formular
    // eines Mitarbeiters wird hier schlicht ignoriert.
    if ($istAdmin) {
        $ausgewaehlteBearbeiterIds = formular_ids('bearbeiter');
    }

    // Gueltige Anzahl uebernehmen; bei ungueltiger Eingabe bleibt im Feld
    // der bisherige Wert stehen.
    $zahl = raum_arbeitsplaetze($arbeitsplaetzeEingabe);
    if ($zahl !== null) {
        $arbeitsplaetze = $zahl;
    }

    $ergebnis = raum_speichern($raumId, array(
        'name'           => $name,
        'arbeitsplaetze' => $arbeitsplaetzeEingabe,
        'software'       => $ausgewaehlteSoftwareIds,
        'bearbeiter'     => $ausgewaehlteBearbeiterIds,
    ), $istAdmin);

    if ($ergebnis === true) {
        header('Location: ' . url('raeume'));
        exit;
    }
    $fehler       = $ergebnis['fehler'];
    $hatKonflikte = $ergebnis['konflikte'];
}

$seitentitel   = $istBearbeiten ? 'Raum bearbeiten' : 'Neuen Raum anlegen';
$seitenCss     = array('formular.css');
$ohneKopfzeile = true;
?>
<?php formular_kopf($seitentitel); ?>

  <div class="inhalt">

<?php formular_reiter('raeume'); ?>

    <?php session_fehler_anzeigen(); ?>

    <div class="karte">
      <h2 class="karte-titel"><?php echo h($seitentitel); ?></h2>

      <?php echo fehler_liste($fehler); ?>

      <?php /* Bei Konflikten mit bestehenden Buchungen hilft der Blick in die
               Belegung des Raums - deshalb dann den Link dorthin einblenden. */ ?>
      <?php if ($hatKonflikte && $raumId !== null): ?>
      <?php echo konflikt_hinweis('Belegung dieses Raums anzeigen', url('buchungen', array('raum_id' => (int) $raumId))); ?>
      <?php endif; ?>

      <form method="post" action="<?php echo h(url('raum_bearbeiten', $istBearbeiten ? array('id' => (int) $raumId) : array())); ?>">
        <?php csrf_feld(); ?>
        <label for="name">Name</label>
        <input type="text" id="name" name="name" maxlength="50" value="<?php echo h($name); ?>" required>

        <label for="arbeitsplaetze">Arbeitsplätze</label>
        <input type="number" id="arbeitsplaetze" name="arbeitsplaetze" min="1"
               value="<?php echo h($arbeitsplaetze); ?>" required>

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
          <legend>Bearbeiter</legend>
          <div class="checkbox-liste">
            <?php foreach ($alleBenutzer as $benutzer): ?>
            <label class="checkbox-item">
              <input type="checkbox" name="bearbeiter[]" value="<?php echo (int) $benutzer['id']; ?>"
                <?php echo in_array((int) $benutzer['id'], $ausgewaehlteBearbeiterIds, true) ? 'checked' : ''; ?>>
              <?php echo h($benutzer['name']); ?>
            </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <?php endif; ?>

        <?php echo knopf_reihe('Speichern', 'raeume'); ?>
      </form>

      <p class="regel-hinweis">
        Weniger Arbeitsplätze oder entfernte Software können bestehende Buchungen ungültig
        machen: ein Kurs braucht einen Raum mit genug Plätzen und mit seiner Software.
        Deshalb wird die Änderung abgelehnt, solange noch ein kommender Termin in diesem
        Raum davon betroffen wäre. Vergangene Buchungen bleiben als Historie unberührt.
      </p>
    </div>
  </div>
