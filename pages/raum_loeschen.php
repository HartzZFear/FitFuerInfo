<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// raum_loeschen (Aufruf: index.php?seite=raum_loeschen)
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

erfordere_login();
csrf_pruefen();

$meineId  = benutzer_id();
$istAdmin = ist_admin();

$raumId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($raumId < 1) {
    zugriff_verweigert_seite(
        'Es wurde kein gültiger Raum angegeben.',
        'raeume',
        'Zurück zu den Räumen'
    );
}

$raum = raum_laden($raumId);

if (!$raum) {
    zugriff_verweigert_seite(
        'Dieser Raum existiert nicht oder wurde bereits gelöscht.',
        'raeume',
        'Zurück zu den Räumen'
    );
}

// Rechtepruefung serverseitig - der Aufruf per URL muss genauso scheitern
// wie der Klick auf einen Button, den es gar nicht gibt.
if (!raum_darf_loeschen($istAdmin)) {
    zugriff_verweigert_seite(
        'Räume kann nur der Systemverwalter löschen.',
        'raeume',
        'Zurück zu den Räumen'
    );
}

$anzahlBuchungen = raum_anzahl_buchungen($raumId);

$fehler = '';
if ($anzahlBuchungen > 0) {
    $fehler = 'Der Raum kann nicht gelöscht werden, solange noch '
        . $anzahlBuchungen . ' Buchung(en) dafür bestehen. Zuerst die Buchungen entfernen.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fehler === '') {
    $ergebnis = raum_loeschen($raumId);
    if ($ergebnis === true) {
        header('Location: ' . url('raeume'));
        exit;
    }
    $fehler = $ergebnis;
}

$seitentitel   = 'Raum löschen';
$seitenCss     = array('formular.css');
$ohneKopfzeile = true;
?>
<?php formular_kopf($seitentitel); ?>

  <div class="inhalt">

<?php formular_reiter('raeume'); ?>

    <?php session_fehler_anzeigen(); ?>

    <div class="karte">
      <h2 class="karte-titel"><?php echo h($seitentitel); ?></h2>

      <?php if ($fehler !== ''): ?>
      <?php echo meldung($fehler); ?>
      <p>
        <a class="link-belegung" href="<?php echo h(url('buchungen', array('raum_id' => (int) $raumId))); ?>">Belegung dieses Raums anzeigen</a>
      </p>
      <p><a class="link-abbrechen" href="<?php echo h(url('raeume')); ?>">Zurück zu den Räumen</a></p>
      <?php else: ?>
      <p class="karte-text">
        Soll der Raum <strong><?php echo h($raum['name']); ?></strong>
        mit <?php echo (int) $raum['arbeitsplaetze']; ?> Arbeitsplätzen wirklich gelöscht werden?
        Das kann nicht rückgängig gemacht werden.
      </p>
      <form method="post" action="<?php echo h(url('raum_loeschen', array('id' => (int) $raumId))); ?>">
        <?php csrf_feld(); ?>
        <?php echo knopf_reihe('Ja, endgültig löschen', 'raeume', true); ?>
      </form>
      <?php endif; ?>
    </div>
  </div>
