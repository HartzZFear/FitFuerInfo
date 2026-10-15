<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// profil (Aufruf: index.php?seite=profil)
// Eigenes Profil: Stammdaten, ein paar Kennzahlen und "Passwort ändern".
// Gleicher visueller Stil wie kurse.php (Kopfzeile mit Tabs, Wellen-Banner,
// weisse Cards mit Schatten).
//
// Name, E-Mail und Rolle kann nur der Admin aendern (benutzer_bearbeiten.php).
// Hier aendert jeder nur sein eigenes Passwort - und muss dafuer das aktuelle
// kennen, damit niemand an einem kurz unbeaufsichtigten Rechner das Passwort
// eines anderen umstellen kann.
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

erfordere_login();
csrf_pruefen();

$meineId = benutzer_id();

// --- Passwort aendern (POST) ----------------------------------------------
$fehler = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $altesPasswort = isset($_POST['passwort_alt']) ? $_POST['passwort_alt'] : '';
    $passwort1     = isset($_POST['passwort1']) ? $_POST['passwort1'] : '';
    $passwort2     = isset($_POST['passwort2']) ? $_POST['passwort2'] : '';

    // Hash frisch aus der Datenbank, aktueller_benutzer() liefert ihn bewusst nicht.
    $hash = abfrage('SELECT passwort_hash FROM benutzer WHERE id = ?', array($meineId))->fetchColumn();

    $altesStimmt = false;
    if ($altesPasswort === '') {
        $fehler[] = 'Bitte das aktuelle Passwort eingeben.';
    } elseif (!is_string($hash) || !password_verify($altesPasswort, $hash)) {
        $fehler[] = 'Das aktuelle Passwort ist falsch.';
    } else {
        $altesStimmt = true;
    }

    // Wie in passwort_setzen.php, aber Regeln und Wiederholung werden
    // unabhaengig voneinander geprueft, damit alle Probleme auf einmal
    // angezeigt werden.
    if ($passwort1 === '' || $passwort2 === '') {
        $fehler[] = 'Bitte das neue Passwort zweimal eingeben.';
    } else {
        $fehler = array_merge($fehler, pruefe_passwortregeln($passwort1));

        if ($passwort1 !== $passwort2) {
            $fehler[] = 'Die beiden neuen Passwörter stimmen nicht überein.';
        }
        // Nur vergleichen, wenn das alte stimmt - sonst liesse sich ueber
        // diese Meldung das aktuelle Passwort erraten.
        if ($altesStimmt && $passwort1 === $altesPasswort) {
            $fehler[] = 'Das neue Passwort muss sich vom bisherigen unterscheiden.';
        }
    }

    if (empty($fehler)) {
        // Ein noch offener Freischaltcode ("Passwort vergessen") wird mit
        // verworfen: Wer sein Passwort gerade selbst gesetzt hat, braucht ihn
        // nicht mehr, und ein herumliegender Code koennte es sonst
        // ueberschreiben.
        abfrage(
            'UPDATE benutzer
             SET passwort_hash = ?, freischaltcode = NULL, code_gueltig_bis = NULL
             WHERE id = ?',
            array(password_hash($passwort1, PASSWORD_DEFAULT), $meineId)
        );

        // Neue Session-ID, damit eine evtl. abgegriffene alte nicht weiter gilt.
        session_regenerate_id(true);

        // Post/Redirect/Get: Neuladen schickt das Formular nicht erneut ab.
        $_SESSION['profil_meldung'] = 'Das Passwort wurde geändert. Ab der nächsten Anmeldung gilt nur noch das neue Passwort.';
        header('Location: ' . url('profil'));
        exit;
    }
}

$meldung = isset($_SESSION['profil_meldung']) ? $_SESSION['profil_meldung'] : '';
unset($_SESSION['profil_meldung']);

// --- Daten fuer die Anzeige -----------------------------------------------
$ich = aktueller_benutzer();

// Eigentuemer = Ersteller ODER Eintrag in kurs_eigentuemer (wie benutzer.php).
// UNION entfernt doppelte Kurse, wenn jemand beides ist.
$anzahlKurse = (int) abfrage(
    'SELECT COUNT(*) FROM (
         SELECT id AS kurs_id FROM kurs WHERE ersteller_id = ?
         UNION
         SELECT kurs_id FROM kurs_eigentuemer WHERE benutzer_id = ?
     ) AS eigentum',
    array($meineId, $meineId)
)->fetchColumn();

$anzahlRaeume = (int) abfrage(
    'SELECT COUNT(*) FROM raum_bearbeiter WHERE benutzer_id = ?',
    array($meineId)
)->fetchColumn();

$anzahlBuchungen = (int) abfrage(
    'SELECT COUNT(*) FROM buchung WHERE benutzer_id = ? AND start >= ?',
    array($meineId, date('Y-m-d H:i:s'))
)->fetchColumn();

$rollenTexte = array(
    'admin'       => 'Admin (Systemverwalter)',
    'mitarbeiter' => 'Mitarbeiter',
);
$rolleText = isset($rollenTexte[$ich['rolle']]) ? $rollenTexte[$ich['rolle']] : $ich['rolle'];

$seitentitel = 'Profil';
$aktiverTab  = 'profil';
$seitenCss   = array('profil.css');
?>
  <div class="main-area">

    <?php session_fehler_anzeigen(); ?>

    <?php if ($meldung !== ''): ?>
    <div class="meldung-erfolg" role="status"><?php echo h($meldung); ?></div>
    <?php endif; ?>

    <div class="profil-raster">

      <section class="karte">
        <h2 class="karte-titel">Mein Profil</h2>
        <dl class="daten">
          <dt>Benutzername</dt>
          <dd><?php echo h($ich['name']); ?></dd>

          <dt>E-Mail</dt>
          <?php if ($ich['email'] !== null && $ich['email'] !== ''): ?>
          <dd><?php echo h($ich['email']); ?></dd>
          <?php else: ?>
          <dd class="leer">keine E-Mail hinterlegt</dd>
          <?php endif; ?>

          <dt>Rolle</dt>
          <dd><?php echo h($rolleText); ?></dd>

          <dt>Eigene Kurse</dt>
          <dd><?php echo (int) $anzahlKurse; ?><?php if ($anzahlKurse > 0): ?> <a href="<?php echo h(url('kurse', array('ownership' => 'own'))); ?>">anzeigen</a><?php endif; ?></dd>

          <dt>Räume als Bearbeiter</dt>
          <dd><?php echo (int) $anzahlRaeume; ?><?php if ($anzahlRaeume > 0): ?> <a href="<?php echo h(url('raeume', array('zustaendig' => 'meine'))); ?>">anzeigen</a><?php endif; ?></dd>

          <dt>Kommende Buchungen</dt>
          <dd><?php echo (int) $anzahlBuchungen; ?><?php if ($anzahlBuchungen > 0): ?> <a href="<?php echo h(url('buchungen', array('wer' => 'meine'))); ?>">anzeigen</a><?php endif; ?></dd>
        </dl>

        <p class="hinweis">
          Benutzername, E-Mail und Rolle können Sie hier nicht selbst ändern.
          <?php if (ist_admin()): ?>
          Als Admin ändern Sie diese Angaben im Tab <a href="<?php echo h(url('benutzer')); ?>">Benutzer</a>.
          <?php else: ?>
          Wenden Sie sich dafür an den Systemverwalter (Admin).
          <?php endif; ?>
        </p>
      </section>

      <section class="karte">
        <h2 class="karte-titel">Passwort ändern</h2>

        <?php if (!empty($fehler)): ?>
        <div class="meldung" role="alert">
          <ul>
            <?php foreach ($fehler as $einzelnerFehler): ?>
            <li><?php echo h($einzelnerFehler); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <form method="post" action="<?php echo h(url('profil')); ?>" novalidate>
          <?php csrf_feld(); ?>

          <label for="passwort_alt">Aktuelles Passwort</label>
          <input type="password" id="passwort_alt" name="passwort_alt" autocomplete="current-password">

          <label for="passwort1">Neues Passwort</label>
          <input type="password" id="passwort1" name="passwort1" autocomplete="new-password">

          <label for="passwort2">Neues Passwort wiederholen</label>
          <input type="password" id="passwort2" name="passwort2" autocomplete="new-password">

          <p class="hinweis">
            Das Passwort muss mindestens <?php echo (int) PW_MIN_LAENGE; ?> Zeichen lang sein
            und mindestens einen Kleinbuchstaben sowie eine Ziffer enthalten.
          </p>

          <button type="submit" class="knopf">Passwort ändern</button>
        </form>
      </section>

    </div>
  </div>
