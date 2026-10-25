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
// Beim Bearbeiten gelten die Regeln aus benutzer_darf_aendern(): Der letzte
// aktive Admin bleibt Admin und aktiv, und niemand sperrt sich selbst.
//
// Loeschen gibt es nicht - kurs.ersteller_id und buchung.benutzer_id stehen
// auf RESTRICT. Stattdessen wird gesperrt.
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

erfordere_admin();
csrf_pruefen();

$meineId = benutzer_id();

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
    $rolle = isset($_POST['rolle']) ? $_POST['rolle'] : '';
    $aktiv = isset($_POST['aktiv']) && $_POST['aktiv'] === '1';

    $ergebnis = benutzer_speichern($zielId, array(
        'name'  => $name,
        'email' => $email,
        'rolle' => $rolle,
        'aktiv' => $aktiv,
    ), $meineId);

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

    // Ungueltige Rolle (manipuliertes Formular): Auswahl zeigt wieder Mitarbeiter.
    if (!in_array($rolle, array('admin', 'mitarbeiter'), true)) {
        $rolle = 'mitarbeiter';
    }
}

$seitentitel   = $istBearbeiten ? 'Benutzer bearbeiten' : 'Neuen Benutzer anlegen';
$seitenCss     = array('formular.css');
$ohneKopfzeile = true;
$istIch        = $istBearbeiten && (int) $zielId === (int) $meineId;
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

        <label for="rolle">Rolle</label>
        <select id="rolle" name="rolle">
          <option value="mitarbeiter" <?php echo $rolle === 'mitarbeiter' ? 'selected' : ''; ?>>Mitarbeiter</option>
          <option value="admin" <?php echo $rolle === 'admin' ? 'selected' : ''; ?>>Admin</option>
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
        <?php if ($istIch): ?>
        Das eigene Konto kann nicht gesperrt werden.
        <?php endif; ?>
        Der letzte aktive Admin bleibt immer Admin und aktiv.
      </p>
    </div>
  </div>
