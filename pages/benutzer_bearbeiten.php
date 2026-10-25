<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// benutzer_bearbeiten (Aufruf: index.php?seite=benutzer_bearbeiten)
// Benutzer anlegen (ohne ?id=) oder bearbeiten (mit ?id=N). Nur fuer den Admin.
//
// Es gibt hier bewusst KEIN Passwortfeld: Der Admin darf kein Passwort eines
// Mitarbeiters kennen. Ein neuer Benutzer wird ohne Passwort gespeichert und
// bekommt einen Freischaltcode, der danach genau einmal angezeigt wird; das
// Passwort setzt der Mitarbeiter selbst in passwort_setzen.php.
//
// Die Rolle wird hier nur angezeigt, nie geaendert: Neue Benutzer sind
// Mitarbeiter, und das Admin-Konto kann nicht gesperrt werden (siehe
// benutzer_speichern() und benutzer_sperre_pruefen()).
//
// Loeschen gibt es nicht - kurs.ersteller_id und buchung.benutzer_id stehen
// auf RESTRICT. Stattdessen wird gesperrt.
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

erfordere_admin();
csrf_pruefen();

$istBearbeiten = isset($_GET['id']) && $_GET['id'] !== '';
$zielId        = $istBearbeiten ? (int) $_GET['id'] : null;

$benutzer = null;

if ($istBearbeiten) {
    $benutzer = benutzer_laden($zielId);

    if (!$benutzer) {
        zugriff_verweigert_seite(
            'Diesen Benutzer gibt es nicht.',
            'benutzer',
            'Zurück zur Benutzerliste'
        );
    }
}

// --- Vorbelegung der Felder ---------------------------------------------
$name  = $istBearbeiten ? $benutzer['name'] : '';
$email = $istBearbeiten && $benutzer['email'] !== null ? $benutzer['email'] : '';
$rolle = $istBearbeiten ? $benutzer['rolle'] : 'mitarbeiter';
$aktiv = $istBearbeiten ? (int) $benutzer['aktiv'] === 1 : true;

$fehler = array();

// --- Speichern ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $aktiv = isset($_POST['aktiv']) && $_POST['aktiv'] === '1';

    // Die Rolle wird bewusst nicht aus dem Formular gelesen - siehe
    // benutzer_speichern().
    $ergebnis = benutzer_speichern($benutzer, array(
        'name'  => $name,
        'email' => $email,
        'aktiv' => $aktiv,
    ));

    if ($ergebnis === true) {
        // Beim Anlegen zeigt die Benutzerliste den neuen Freischaltcode an,
        // beim Bearbeiten eine Bestaetigung.
        if ($istBearbeiten) {
            $_SESSION['benutzer_meldung'] = 'Die Änderungen an ' . $name . ' wurden gespeichert.';
            $_SESSION['benutzer_fehler']  = array();
        }
        header('Location: ' . url('benutzer'));
        exit;
    }
    $fehler = $ergebnis;
}

$seitentitel   = $istBearbeiten ? 'Benutzer bearbeiten' : 'Neuen Benutzer anlegen';
$seitenCss     = array('formular.css');
$ohneKopfzeile = true;
?>
<?php formular_kopf($seitentitel); ?>

  <div class="inhalt">

<?php formular_reiter('benutzer'); ?>

    <?php session_fehler_anzeigen(); ?>

    <div class="karte">
      <h2 class="karte-titel"><?php echo h($seitentitel); ?></h2>

      <?php echo fehler_liste($fehler); ?>

      <?php if ($istBearbeiten && benutzer_status($benutzer) === 'wartet'): ?>
      <div class="hinweis">
        Dieser Benutzer hat noch kein Passwort gesetzt.
        <?php if ($benutzer['freischaltcode'] !== null && strtotime($benutzer['code_gueltig_bis']) >= time()): ?>
        Sein Freischaltcode ist gültig bis <?php echo h(date('d.m.Y, H:i', strtotime($benutzer['code_gueltig_bis']))); ?> Uhr.
        <?php else: ?>
        Sein Freischaltcode ist abgelaufen – in der Benutzerliste einen neuen erzeugen.
        <?php endif; ?>
      </div>
      <?php elseif (!$istBearbeiten): ?>
      <div class="hinweis">
        Ein Passwort wird hier nicht vergeben. Nach dem Speichern erscheint einmalig ein
        Freischaltcode (<?php echo (int) FREISCHALTCODE_GUELTIG_TAGE; ?> Tage gültig), mit dem der
        Mitarbeiter sein Passwort selbst setzt.
      </div>
      <?php endif; ?>

      <form method="post" action="<?php echo h(url('benutzer_bearbeiten', $istBearbeiten ? array('id' => (int) $zielId) : array())); ?>" novalidate>
        <?php csrf_feld(); ?>
        <label for="name">Benutzername</label>
        <input type="text" id="name" name="name" maxlength="50" value="<?php echo h($name); ?>" required>

        <label for="email">E-Mail <span class="optional">(optional)</span></label>
        <input type="email" id="email" name="email" maxlength="100" value="<?php echo h($email); ?>">

        <?php /* Nur Anzeige: deaktivierte Felder schickt der Browser nicht mit,
                 und benutzer_speichern() liest die Rolle ohnehin nicht. */ ?>
        <label for="rolle">Rolle</label>
        <select id="rolle" disabled>
          <option selected><?php echo $rolle === 'admin' ? 'Admin' : 'Mitarbeiter'; ?></option>
        </select>

        <label class="checkbox-item">
          <input type="checkbox" name="aktiv" value="1" <?php echo $aktiv ? 'checked' : ''; ?>>
          aktiv (darf sich anmelden)
        </label>

        <?php echo knopf_reihe('Speichern', 'benutzer'); ?>
      </form>

      <p class="regel-hinweis">
        Benutzer können nicht gelöscht werden, weil an ihnen Kurse und Buchungen hängen.
        Wer keinen Zugang mehr haben soll, wird gesperrt („aktiv“ abwählen) – eine laufende
        Sitzung endet dann beim nächsten Klick.
        Die Rolle wird hier nicht geändert, und das Admin-Konto kann nicht gesperrt werden.
      </p>
    </div>
  </div>
