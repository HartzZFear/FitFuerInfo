<?php
// login.php

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

session_starten();
csrf_pruefen();

// Wer schon angemeldet ist, braucht diese Seite nicht.
if (ist_eingeloggt()) {
    header('Location: kurse.php');
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
    $benutzername = isset($_POST['username']) ? trim($_POST['username']) : '';
    $passwort     = isset($_POST['password']) ? $_POST['password'] : '';

    if ($benutzername === '' || $passwort === '') {
        $fehler = 'Bitte Benutzername und Passwort eingeben.';
    } elseif (anmelden($benutzername, $passwort)) {
        header('Location: belegung.php');
        exit;
    } else {
        $fehler = 'Benutzername oder Passwort ist falsch, oder das Konto ist nicht freigeschaltet.';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Anmelden – FitFürInfo</title>
<link rel="stylesheet" href="/fitfuerinfo/assets/css/style.css">
<style>
 body {
    margin: 0;
    min-height: 100vh;
    background: var(--page-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
}

.top-banner{
  top: 0px;
}

.help-region { display: none; }
</style>
</head>
<body>

  <!-- Dekorativer Wellen-Streifen oben, wie im Referenzbild -->
  <div class="top-banner" aria-hidden="true">
    <svg viewBox="0 0 1440 200" preserveAspectRatio="none">
      <defs>
        <linearGradient id="waveGradient" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%"  stop-color="#1a8f9c"/>
          <stop offset="45%" stop-color="#2f6fb0"/>
          <stop offset="100%" stop-color="#e8792e"/>
        </linearGradient>
      </defs>
      <path fill="url(#waveGradient)"
            d="M0,80 C240,160 480,0 720,60 C960,120 1200,20 1440,90 L1440,0 L0,0 Z"/>
      <path fill="url(#waveGradient)" opacity="0.55"
            d="M0,120 C280,60 520,180 780,110 C1040,40 1260,140 1440,100 L1440,0 L0,0 Z"/>
    </svg>
  </div>

  <div class="help-region">
    <a href="#" class="help-icon" aria-label="Hilfe">?</a>
    <div class="lang-select">🇩🇪 DE ▾</div>
  </div>

  <main class="login-card">
    <div class="logo">
      <img class="logo-mark" src="<?php echo BASE_URL; ?>/src/web/assets/logo.png" alt="">
      <span class="logo-text"><span class="fit">FitFür</span><span class="info">Info</span></span>
    </div>
    <p class="logo-subtitle">Fitness &amp; Informatik vereint</p>

    <form method="post" action="login.php">
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
        <p>Erstes Login oder neuen Code erhalten? <span class="forgot-ziel">→ <a href="passwort_setzen.php">Passwort setzen</a></span></p>
        <p class="forgot-hinweis">Passwort vergessen? Wende dich an den Systemverwalter, er erzeugt dir einen neuen Freischaltcode.</p>
      </div>

      <button type="submit" class="btn-login">Anmelden</button>
    </form>
  </main>

</body>
</html>
