<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// buchung_bearbeiten (Aufruf: index.php?seite=buchung_bearbeiten)
// Buchung anlegen (ohne ?id=) oder bearbeiten (mit ?id=N).
//
// Beim Anlegen koennen Kurs, Raum, Datum und Startzeit per GET vorbelegt
// werden (?raum_id=&kurs_id=&datum=&start=). Das nutzt spaeter der
// Belegungskalender, wenn man auf eine freie Zelle klickt.
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

erfordere_login();
csrf_pruefen();

$meineId  = benutzer_id();
$istAdmin = ist_admin();

$istBearbeiten = isset($_GET['id']) && $_GET['id'] !== '';
$buchungId     = $istBearbeiten ? (int) $_GET['id'] : null;

$buchung = null;

if ($istBearbeiten) {
    $buchung = buchung_laden($buchungId);

    if (!$buchung) {
        zugriff_verweigert_seite(
            'Diese Buchung existiert nicht oder wurde bereits gelöscht.',
            'buchungen',
            'Zurück zur Belegung'
        );
    }
    // Rechtepruefung serverseitig - nicht nur durch Ausblenden der Buttons
    // in buchungen.php.
    if (!buchung_darf_verwalten($buchungId, $meineId, $istAdmin)) {
        zugriff_verweigert_seite(
            'Sie haben diese Buchung nicht angelegt und können sie deshalb nicht bearbeiten.',
            'buchungen',
            'Zurück zur Belegung'
        );
    }
    if (buchung_ist_vergangen($buchung['start'])) {
        zugriff_verweigert_seite(
            'Diese Buchung liegt in der Vergangenheit und kann nicht mehr bearbeitet werden.',
            'buchungen',
            'Zurück zur Belegung'
        );
    }
}

// Nur Kurse anbieten, fuer die der Benutzer buchen darf. Der Admin sieht alle.
$meineKurse = buchbare_kurse($meineId, $istAdmin);
$alleRaeume = raum_alle();
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
    $datumWert  = isset($_GET['datum'])   ? eingabe_text($_GET, 'datum') : date('Y-m-d');
    $startWert  = isset($_GET['start'])   ? eingabe_text($_GET, 'start') : '08:00';
    $endeWert   = buchung_ende_vorschlag($startWert, $alleSlots);
}

$fehler         = array();
$passendeRaeume = array();

// --- Speichern ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kursIdWert = eingabe_text($_POST, 'kurs_id');
    $raumIdWert = eingabe_text($_POST, 'raum_id');
    $datumWert  = eingabe_text($_POST, 'datum');
    $startWert  = eingabe_text($_POST, 'start');
    $endeWert   = eingabe_text($_POST, 'ende');

    $kursId = (int) $kursIdWert;
    $raumId = (int) $raumIdWert;

    // Darf der Benutzer ueberhaupt fuer diesen Kurs buchen? Das Dropdown
    // zeigt nur erlaubte Kurse, aber ein manipuliertes Formular kann jede
    // beliebige ID schicken - deshalb hier noch einmal pruefen.
    if ($kursId >= 1 && !kurs_darf_verwalten($kursId, $meineId, $istAdmin)) {
        zugriff_verweigert_seite(
            'Sie sind nicht Eigentümer dieses Kurses und können dafür keine Buchung anlegen.',
            'buchungen',
            'Zurück zur Belegung'
        );
    }

    $ergebnis = buchung_speichern($buchungId, array(
        'kurs_id' => $kursId,
        'raum_id' => $raumId,
        'datum'   => $datumWert,
        'start'   => $startWert,
        'ende'    => $endeWert,
    ), $meineId);

    if ($ergebnis === true) {
        header('Location: ' . url('buchungen'));
        exit;
    }
    $fehler = $ergebnis;

    // Wenn Software (Regel 4) oder Platz (Regel 5) das Problem waren, ist
    // ein anderer Raum die Loesung - deshalb hier die Liste dazu anzeigen.
    $passendeRaeume = buchung_raum_vorschlaege($kursId, $raumId);
}

$seitentitel   = $istBearbeiten ? 'Buchung bearbeiten' : 'Neue Buchung anlegen';
$seitenCss     = array('formular.css');
$ohneKopfzeile = true;
?>
<?php formular_kopf($seitentitel); ?>

  <div class="inhalt">

<?php formular_reiter('belegung'); ?>

    <?php session_fehler_anzeigen(); ?>

    <div class="karte">
      <h2 class="karte-titel"><?php echo h($seitentitel); ?></h2>

      <?php echo fehler_liste($fehler); ?>

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
      <p><a class="link-abbrechen" href="<?php echo h(url('buchungen')); ?>">Zurück zur Belegung</a></p>
      <?php else: ?>

      <form method="post" action="<?php echo h(url('buchung_bearbeiten', $istBearbeiten ? array('id' => (int) $buchungId) : array())); ?>">
        <?php csrf_feld(); ?>
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

        <?php echo knopf_reihe('Speichern', 'buchungen'); ?>
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
