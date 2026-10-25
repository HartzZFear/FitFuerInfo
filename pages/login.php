<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// login - Anmeldeseite (ohne Kopfzeile, eigene Welle).
// Aufruf: index.php?seite=login, oder index.php ohne Angabe, solange
// niemand angemeldet ist.

csrf_pruefen();

// Wer schon angemeldet ist, braucht diese Seite nicht.
if (ist_eingeloggt()) {
    header('Location: ' . url('kurse'));
    exit;
}

$fehler       = '';
$benutzername = '';

// Hierher leitet erfordere_login(), wenn das Konto waehrend einer laufenden
// Session gesperrt wurde.
if (isset($_GET['gesperrt']) && $_GET['gesperrt'] === '1') {
    $fehler = 'Ihr Konto wurde gesperrt und Sie wurden abgemeldet. Bitte wenden Sie sich an den Systemverwalter.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $benutzername = eingabe_text($_POST, 'username');
    $passwort     = eingabe_text($_POST, 'password', false);

    if ($benutzername === '' || $passwort === '') {
        $fehler = 'Bitte Benutzername und Passwort eingeben.';
    } elseif (anmelden($benutzername, $passwort)) {
        header('Location: ' . url('belegung'));
        exit;
    } else {
        $fehler = 'Benutzername oder Passwort ist falsch, oder das Konto ist nicht freigeschaltet.';
    }
}

$seitentitel   = 'Anmelden';
$ohneKopfzeile = true;
?>
  <!-- Dekorativer Wellen-Streifen oben, wie im Referenzbild -->
  <div class="login-welle" aria-hidden="true">
<?php welle_svg(); ?>
  </div>

  <div class="help-region">
    <a href="#" class="help-icon" aria-label="Hilfe">?</a>
    <div class="lang-select">🇩🇪 DE ▾</div>
  </div>

  <main class="login-card">
    <div class="logo">
      <img class="logo-mark" src="<?php echo h(BASE_URL . '/assets/imgs/logo.png'); ?>" alt="">
      <span class="logo-text"><span class="fit">FitFür</span><span class="info">Info</span></span>
    </div>
    <p class="logo-subtitle">Fitness &amp; Informatik vereint</p>

    <form method="post" action="<?php echo h(url('login')); ?>">
      <?php csrf_feld(); ?>
      <?php if ($fehler !== ''): ?>
      <div class="login-fehler"><?php echo h($fehler); ?></div>
      <?php endif; ?>

      <div class="field">
        <span class="icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
        </span>
        <label for="username" class="visually-hidden">E-Mail oder Benutzername</label>
        <input type="text" id="username" name="username" placeholder="Benutzername" value="<?php echo h($benutzername); ?>">
      </div>

      <div class="field">
        <span class="icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="4" y="11" width="16" height="9" rx="2"/>
            <path d="M8 11V7a4 4 0 0 1 8 0v4"/>
          </svg>
        </span>
        <label for="password" class="visually-hidden">Passwort</label>
        <input type="password" id="password" name="password" placeholder="Passwort">
      </div>

      <div class="forgot-row">
        <p>Erstes Login oder neuen Code erhalten? <span class="forgot-ziel">→ <a href="<?php echo h(url('passwort_setzen')); ?>">Passwort setzen</a></span></p>
        <p class="forgot-hinweis">Passwort vergessen? Wende dich an den Systemverwalter, er erzeugt dir einen neuen Freischaltcode.</p>
      </div>

      <button type="submit" class="btn-login">Anmelden</button>
    </form>
  </main>
