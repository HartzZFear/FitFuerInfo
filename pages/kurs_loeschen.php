<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// kurs_loeschen (Aufruf: index.php?seite=kurs_loeschen)
// Sicherheitsabfrage vor dem Loeschen eines Kurses. Die eigentliche
// Loeschung passiert erst per POST (Bestaetigung), nicht schon beim
// Aufruf per GET-Link.
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

erfordere_login();
csrf_pruefen();

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
        header('Location: ' . url('kurse'));
        exit;
    } catch (PDOException $e) {
        $fehler = 'Der Kurs kann nicht gelöscht werden, solange noch Buchungen dafür bestehen.';
    }
}

$seitentitel   = 'Kurs löschen';
$seitenCss     = array('formular.css');
$ohneKopfzeile = true;
?>
<?php formular_kopf($seitentitel); ?>

  <div class="inhalt">
    <div class="karte">
      <h2 class="karte-titel"><?php echo h($seitentitel); ?></h2>

      <?php if ($fehler !== ''): ?>
      <div class="fehler"><?php echo h($fehler); ?></div>
      <p><a class="link-abbrechen" href="<?php echo h(url('kurse')); ?>">Zurück zu den Kursen</a></p>
      <?php else: ?>
      <p class="karte-text">
        Soll der Kurs <strong><?php echo h($kurs['titel']); ?></strong> wirklich gelöscht werden?
        Das kann nicht rückgängig gemacht werden.
      </p>
      <form method="post" action="<?php echo h(url('kurs_loeschen', array('id' => (int) $kursId))); ?>">
        <?php csrf_feld(); ?>
        <div class="knopf-reihe">
          <button type="submit" class="knopf knopf-loeschen">Ja, endgültig löschen</button>
          <a class="link-abbrechen" href="<?php echo h(url('kurse')); ?>">Abbrechen</a>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
