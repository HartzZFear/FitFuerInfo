<?php
/**
 * Gemeinsame Kopfzeile aller Seiten mit Tab-Leiste.
 *
 * Vorher stand dieselbe Kopfzeile (Logo, Tabs, Profile, Log Out) samt CSS
 * und Wellen-Banner in jeder Seite einzeln. Jetzt ruft jede dieser Seiten
 * direkt nach <body> nur noch
 *
 *   <?php kopfzeile('kurse'); ?>
 *
 * auf. Erlaubte Werte fuer $aktiverTab: kurse, raeume, belegung, benutzer,
 * profil, hilfe. Der Tab "Benutzer" erscheint nur fuer den Admin.
 *
 * Das CSS der Kopfzeile steht in assets/css/style.css. Jede Seite bindet
 * style.css selbst im <head> ein (vor ihrem eigenen CSS).
 *
 * Breite: Ab 1024 px passt alles in eine Zeile (ab 1280 px in der
 * urspruenglichen Groesse, darunter etwas kompakter). Unter 1024 px darf
 * die Kopfzeile umbrechen: Logo und Knoepfe oben, Tabs darunter. Weil sie
 * dann unterschiedlich hoch sein kann, ist sie dort nicht mehr fixiert,
 * sondern scrollt mit der Seite - so verdeckt sie nie Inhalt.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/funktionen.php';

/**
 * Gibt Kopfzeile, Wellen-Banner und das gemeinsame Skript (main.js) aus.
 *
 * @param string $aktiverTab
 */
function kopfzeile($aktiverTab)
{
    $tabs = array(
        'kurse'    => 'Kurse',
        'raeume'   => 'Räume',
        'belegung' => 'Belegung',
    );
    if (ist_admin()) {
        $tabs['benutzer'] = 'Benutzer';
    }
?>

  <header class="header-bar">
    <div class="header-logo">
      <img src="<?php echo h(BASE_URL . '/assets/imgs/logo.png'); ?>" alt="">
      <span><span class="fit">FitFür</span><span class="info">Info</span></span>
    </div>

    <nav class="tab-group<?php echo count($tabs) > 3 ? ' vier' : ''; ?>">
      <?php foreach ($tabs as $seite => $beschriftung): ?>
      <a href="<?php echo h(url($seite)); ?>" class="tab<?php echo $aktiverTab === $seite ? ' active' : ''; ?>"<?php echo $aktiverTab === $seite ? ' aria-current="page"' : ''; ?>><?php echo h($beschriftung); ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="header-actions">
      <a href="<?php echo h(url('profil')); ?>" class="btn-header profile<?php echo $aktiverTab === 'profil' ? ' active' : ''; ?>"<?php echo $aktiverTab === 'profil' ? ' aria-current="page"' : ''; ?>>Profile</a>
      <a href="<?php echo h(url('logout')); ?>" class="btn-header logout">Log Out</a>
    </div>
  </header>

  <div class="banner-spacer"></div>

  <!-- Dekorativer Wellen-Streifen, identisch zum Login-Header -->
  <div class="top-banner" id="waveBanner">
    <svg viewBox="0 0 1440 200" preserveAspectRatio="none" aria-hidden="true">
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
    <div class="help-region">
      <a href="<?php echo h(url('hilfe')); ?>" class="help-icon<?php echo $aktiverTab === 'hilfe' ? ' active' : ''; ?>" aria-label="Hilfe" title="Hilfe"<?php echo $aktiverTab === 'hilfe' ? ' aria-current="page"' : ''; ?>>?</a>
      <div class="lang-select" aria-hidden="true">🇩🇪 DE ▾</div>
    </div>
  </div>

  <script src="<?php echo h(asset_url('js/main.js')); ?>"></script>
  <?php
}
