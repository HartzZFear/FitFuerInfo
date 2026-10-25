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
    $ergebnis = benutzer_passwort_aendern(
        $meineId,
        eingabe_text($_POST, 'passwort_alt', false),
        eingabe_text($_POST, 'passwort1', false),
        eingabe_text($_POST, 'passwort2', false)
    );

    if ($ergebnis === true) {
        // Neue Session-ID, damit eine evtl. abgegriffene alte nicht weiter gilt.
        session_regenerate_id(true);

        // Post/Redirect/Get: Neuladen schickt das Formular nicht erneut ab.
        $_SESSION['profil_meldung'] = 'Das Passwort wurde geändert. Ab der nächsten Anmeldung gilt nur noch das neue Passwort.';
        header('Location: ' . url('profil'));
        exit;
    }
    $fehler = $ergebnis;
}

$meldung = isset($_SESSION['profil_meldung']) ? $_SESSION['profil_meldung'] : '';
unset($_SESSION['profil_meldung']);

// --- Daten fuer die Anzeige -----------------------------------------------
$ich = aktueller_benutzer();

$zahlen          = benutzer_profil_zahlen($meineId);
$anzahlKurse     = $zahlen['kurse'];
$anzahlRaeume    = $zahlen['raeume'];
$anzahlBuchungen = $zahlen['buchungen'];

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
