<?php
// login.php

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

session_starten();

// Wer schon angemeldet ist, braucht diese Seite nicht.
if (ist_eingeloggt()) {
    header('Location: kurse.php');
    exit;
}

$fehler       = '';
$benutzername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $benutzername = isset($_POST['username']) ? trim($_POST['username']) : '';
    $passwort     = isset($_POST['password']) ? $_POST['password'] : '';

    if ($benutzername === '' || $passwort === '') {
        $fehler = 'Bitte Benutzername und Passwort eingeben.';
    } elseif (anmelden($benutzername, $passwort)) {
        header('Location: kurse.php');
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
<style>
  :root {
    --teal: #1a8f9c;
    --blue: #2f6fb0;
    --orange: #e8792e;
    --card-bg: #ffffff;
    --page-bg: #eef3f4;
    --text-dark: #2b3a42;
    --text-muted: #7c8a91;
    --border: #dfe6e8;
  }

  * { box-sizing: border-box; }

  body {
    margin: 0;
    min-height: 100vh;
    font-family: "Segoe UI", Roboto, Arial, sans-serif;
    background: var(--page-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
  }

  /* ---- Dekorativer Wellen-Header oben auf der Seite ---- */
  .top-banner {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: 130px;
    overflow: hidden;
    z-index: 0;
  }

  .top-banner svg {
    width: 100%;
    height: 100%;
    display: block;
  }

  /* leichtes "Schaltkreis/Binär"-Muster im Hintergrund, wie im Vorbild */
  .top-banner::after {
    content: "";
    position: absolute;
    inset: 0;
    background-image:
      repeating-linear-gradient(90deg, rgba(255,255,255,0.08) 0 1px, transparent 1px 40px),
      repeating-linear-gradient(0deg, rgba(255,255,255,0.08) 0 1px, transparent 1px 40px);
    mix-blend-mode: overlay;
    pointer-events: none;
  }

  .help-region {
    position: fixed;
    top: 4px;
    right: 14px;
    display: flex;
    align-items: center;
    gap: 14px;
    z-index: 2;
  }

  .help-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: rgba(255,255,255,0.9);
    color: var(--blue);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
  }

  .lang-select {
    display: flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.9);
    border-radius: 20px;
    padding: 5px 12px;
    font-size: 13px;
    color: var(--text-dark);
  }

  /* ---- Login-Karte ---- */
  .login-card {
    position: relative;
    z-index: 1;
    background: var(--card-bg);
    width: 100%;
    max-width: 380px;
    padding: 36px 32px 32px;
    border-radius: 18px;
    box-shadow: 0 20px 45px rgba(20, 40, 50, 0.15);
    text-align: center;
  }

  .logo {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-bottom: 4px;
  }

  .logo-mark {
    width: 34px;
    height: 34px;
    flex-shrink: 0;
    object-fit: contain;
  }

  .logo-text {
    font-size: 26px;
    font-weight: 700;
    letter-spacing: -0.5px;
  }

  .logo-text .fit { color: var(--orange); }
  .logo-text .info { color: var(--blue); }

  .logo-subtitle {
    font-size: 11px;
    letter-spacing: 1px;
    color: var(--text-muted);
    margin: 0 0 28px;
    text-transform: uppercase;
  }

  .field {
    position: relative;
    margin-bottom: 16px;
    text-align: left;
  }

  /* Labels bleiben für Screenreader vorhanden, werden aber optisch
     ausgeblendet - im Feld selbst steht ja bereits das Platzhalter-Icon
     + der Placeholder-Text, genau wie im Referenz-Design. */
  .visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
  }

  .field input {
    width: 100%;
    padding: 13px 16px 13px 42px;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 14px;
    color: var(--text-dark);
    background: #fbfcfc;
    outline: none;
    transition: border-color 0.15s ease;
  }

  .field input::placeholder {
    color: #a7b2b6;
  }

  .field input:focus {
    border-color: var(--blue);
    background: #ffffff;
  }

  .field .icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    color: #9aa7ac;
  }

  .field .icon svg {
    width: 100%;
    height: 100%;
  }

  .forgot-row {
    text-align: right;
    margin-bottom: 22px;
  }

  .forgot-row a {
    font-size: 13px;
    color: var(--blue);
    text-decoration: none;
    font-weight: 600;
  }

  .forgot-row a:hover,
  .forgot-row a:focus-visible {
    text-decoration: underline;
  }

  .btn-login {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 700;
    color: #ffffff;
    cursor: pointer;
    background: linear-gradient(90deg, var(--blue) 0%, var(--teal) 45%, var(--orange) 100%);
    letter-spacing: 0.3px;
  }

  .btn-login:hover {
    filter: brightness(1.05);
  }

  .btn-login:focus-visible,
  a:focus-visible,
  input:focus-visible {
    outline: 2px solid var(--blue);
    outline-offset: 2px;
  }

  @media (max-width: 420px) {
    .login-card {
      padding: 28px 22px 26px;
      max-width: 100%;
    }
  }

  /* ---- Fehlermeldung beim Anmelden (Backend-Logik) ---- */
  .login-fehler {
    margin: -6px 0 16px;
    padding: 10px 14px;
    border-radius: 10px;
    background: #fdeceb;
    border: 1px solid #f0b3ae;
    color: #a13a2f;
    font-size: 13px;
    text-align: left;
  }
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
        <a href="passwort_setzen.php">Passwort vergessen?</a>
      </div>

      <button type="submit" class="btn-login">Anmelden</button>
    </form>
  </main>

</body>
</html>
