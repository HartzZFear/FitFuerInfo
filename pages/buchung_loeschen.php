<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// buchung_loeschen (Aufruf: index.php?seite=buchung_loeschen)
// Sicherheitsabfrage vor dem Loeschen einer Buchung. Die eigentliche
// Loeschung passiert erst per POST (Bestaetigung), nicht schon beim
// Aufruf per GET-Link.
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

erfordere_login();
csrf_pruefen();

$meineId  = benutzer_id();
$istAdmin = ist_admin();

$buchungId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($buchungId < 1) {
    zugriff_verweigert_seite(
        'Es wurde keine gültige Buchung angegeben.',
        'buchungen',
        'Zurück zur Belegung'
    );
}

$buchung = buchung_mit_namen_laden($buchungId);

if (!$buchung) {
    zugriff_verweigert_seite(
        'Diese Buchung existiert nicht oder wurde bereits gelöscht.',
        'buchungen',
        'Zurück zur Belegung'
    );
}

// Rechtepruefung serverseitig - der Aufruf per URL muss genauso scheitern
// wie der Klick auf einen Button, den es gar nicht gibt.
if (!buchung_darf_verwalten($buchungId, $meineId, $istAdmin)) {
    zugriff_verweigert_seite(
        'Sie haben diese Buchung nicht angelegt und können sie deshalb nicht löschen.',
        'buchungen',
        'Zurück zur Belegung'
    );
}
if (!buchung_darf_loeschen($buchung, $meineId, $istAdmin)) {
    zugriff_verweigert_seite(
        'Diese Buchung liegt in der Vergangenheit. Nur der Systemverwalter kann sie noch löschen.',
        'buchungen',
        'Zurück zur Belegung'
    );
}

$fehler = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ergebnis = buchung_loeschen($buchungId);
    if ($ergebnis === true) {
        header('Location: ' . url('buchungen'));
        exit;
    }
    $fehler = $ergebnis;
}

$seitentitel   = 'Buchung löschen';
$seitenCss     = array('formular.css');
$ohneKopfzeile = true;
?>
<?php formular_kopf($seitentitel); ?>

  <div class="inhalt">

<?php formular_reiter('belegung'); ?>

    <?php session_fehler_anzeigen(); ?>

    <div class="karte">
      <h2 class="karte-titel"><?php echo h($seitentitel); ?></h2>

      <?php if ($fehler !== ''): ?>
      <?php echo meldung($fehler); ?>
      <p><a class="link-abbrechen" href="<?php echo h(url('buchungen')); ?>">Zurück zur Belegung</a></p>
      <?php else: ?>
      <p class="karte-text">
        Soll die Buchung von <strong><?php echo h($buchung['kurs']); ?></strong>
        in <strong><?php echo h($buchung['raum']); ?></strong>
        <?php echo h(buchung_zeitraum_text($buchung['start'], $buchung['ende'])); ?>
        wirklich gelöscht werden? Das kann nicht rückgängig gemacht werden.
      </p>
      <form method="post" action="<?php echo h(url('buchung_loeschen', array('id' => (int) $buchungId))); ?>">
        <?php csrf_feld(); ?>
        <?php echo knopf_reihe('Ja, endgültig löschen', 'buchungen', true); ?>
      </form>
      <?php endif; ?>
    </div>
  </div>
