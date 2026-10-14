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
 * Die Farbvariablen (--teal, --blue, ...) definiert weiterhin jede Seite
 * selbst in :root, weil ihr eigenes CSS sie ebenfalls braucht.
 *
 * Breite: Ab 1024 px passt alles in eine Zeile (ab 1280 px in der
 * urspruenglichen Groesse, darunter etwas kompakter). Unter 1024 px darf
 * die Kopfzeile umbrechen: Logo und Knoepfe oben, Tabs darunter. Weil sie
 * dann unterschiedlich hoch sein kann, ist sie dort nicht mehr fixiert,
 * sondern scrollt mit der Seite - so verdeckt sie nie Inhalt.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

require_once __DIR__ . '/auth.php';

/**
 * Gibt CSS, Kopfzeile, Wellen-Banner und das Scroll-Skript des Banners aus.
 * Das CSS wird pro Seitenaufruf nur einmal ausgegeben, auch wenn die
 * Funktion versehentlich zweimal aufgerufen wird.
 *
 * @param string $aktiverTab
 */
function kopfzeile($aktiverTab)
{
    static $cssAusgegeben = false;

    if (!$cssAusgegeben) {
        kopfzeile_css();
        $cssAusgegeben = true;
    }

    $basis = BASE_URL . '/src/web/pages/';

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
      <img src="<?php echo h(BASE_URL . '/src/web/assets/logo.png'); ?>" alt="">
      <span><span class="fit">FitFür</span><span class="info">Info</span></span>
    </div>

    <nav class="tab-group<?php echo count($tabs) > 3 ? ' vier' : ''; ?>">
<?php foreach ($tabs as $seite => $beschriftung): ?>
      <a href="<?php echo h($basis . $seite . '.php'); ?>" class="tab<?php echo $aktiverTab === $seite ? ' active' : ''; ?>"<?php echo $aktiverTab === $seite ? ' aria-current="page"' : ''; ?>><?php echo h($beschriftung); ?></a>
<?php endforeach; ?>
    </nav>

    <div class="header-actions">
      <a href="<?php echo h($basis . 'profil.php'); ?>" class="btn-header profile<?php echo $aktiverTab === 'profil' ? ' active' : ''; ?>"<?php echo $aktiverTab === 'profil' ? ' aria-current="page"' : ''; ?>>Profile</a>
      <a href="<?php echo h($basis . 'logout.php'); ?>" class="btn-header logout">Log Out</a>
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
      <a href="<?php echo h($basis . 'hilfe.php'); ?>" class="help-icon<?php echo $aktiverTab === 'hilfe' ? ' active' : ''; ?>" aria-label="Hilfe" title="Hilfe"<?php echo $aktiverTab === 'hilfe' ? ' aria-current="page"' : ''; ?>>?</a>
      <div class="lang-select" aria-hidden="true">🇩🇪 DE ▾</div>
    </div>
  </div>

  <script>
    // Welle beim Scrollen sanft nach oben schieben und langsam ausblenden
    // (Parallax: bewegt sich langsamer als der eigentliche Scroll, dadurch
    // wirkt das Verschwinden hinter der Kopfzeile weich statt abrupt).
    (function () {
      var wave = document.getElementById('waveBanner');
      var fadeDistance = 130;   // ab wie viel Scroll-px die Welle komplett weg ist
      var parallaxFactor = 0.4; // < 1 => Welle bewegt sich langsamer als der Scroll
      var ticking = false;

      function updateWave() {
        var scrolled = window.pageYOffset || document.documentElement.scrollTop;
        var progress = Math.min(scrolled / fadeDistance, 1);

        wave.style.transform = 'translateY(' + (-scrolled * parallaxFactor) + 'px)';
        wave.style.opacity = String(1 - progress);
        ticking = false;
      }

      window.addEventListener('scroll', function () {
        if (!ticking) {
          window.requestAnimationFrame(updateWave);
          ticking = true;
        }
      }, { passive: true });

      updateWave();
    })();
  </script>
<?php
}

/**
 * Das CSS der Kopfzeile. Steht hinter dem CSS der Seite und gewinnt damit
 * bei gleicher Spezifitaet.
 */
function kopfzeile_css()
{
    ?>
<style>
  body {
    padding-top: 78px; /* Platz für die fixierte Kopfzeile */
  }

  /* ---- Dekorativer Wellen-Header: fixiert unter der Kopfzeile, wird per JS
     beim Scrollen sanft (parallax + fade) ausgeblendet statt abrupt zu verschwinden ---- */
  .banner-spacer { height: 110px; }

  .top-banner {
    position: fixed;
    top: 78px;
    left: 0;
    right: 0;
    height: 110px;
    overflow: hidden;
    z-index: 1;
    will-change: transform, opacity;
    transition: transform 0.05s linear, opacity 0.05s linear;
  }

  .top-banner svg {
    width: 100%;
    height: 100%;
    display: block;
    transition: transform 0.05s linear;
  }

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
    position: absolute;
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

  .help-icon:hover,
  .help-icon:focus-visible { background: #ffffff; }

  .help-icon.active {
    background: linear-gradient(90deg, var(--blue) 0%, var(--teal) 45%, var(--orange) 100%);
    color: #ffffff;
    box-shadow: 0 0 0 2px #ffffff;
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

  /* ---- Kopfzeile: Logo links, Tabs mittig, Profile/Log Out rechts ---- */
  .header-bar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    width: 100%;
    height: 78px;
    background: var(--card-bg);
    box-shadow: 0 4px 14px rgba(20, 40, 50, 0.12);
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 0 24px;
    z-index: 3;
  }

  .header-logo {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
  }

  .header-logo img {
    width: 56px;
    height: 56px;
    object-fit: contain;
  }

  .header-logo span {
    font-size: 32px;
    font-weight: 700;
    color: var(--text-dark);
    white-space: nowrap;
    line-height: 1;
  }

  .header-logo .fit { color: var(--orange); }
  .header-logo .info { color: var(--blue); }

  .tab-group {
    flex: 1 1 auto;
    display: flex;
    gap: 16px;
    max-width: 380px;
    margin: 0 auto;
  }

  /* Mit dem Admin-Tab "Benutzer" etwas mehr Platz, damit kein Text
     am Rand klebt. */
  .tab-group.vier { max-width: 440px; }

  /* flex-basis 0 = alle Tabs gleich breit; min-width verhindert, dass
     ein Tab schmaler als seine Beschriftung wird (sonst wird sie
     abgeschnitten). */
  .tab {
    flex: 1 1 0;
    min-width: max-content;
    text-align: center;
    padding: 10px 12px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-muted);
    text-decoration: none;
    white-space: nowrap;
    cursor: pointer;
    border: 1px solid var(--border);
    background: #fbfcfc;
  }

  .tab:hover,
  .tab:focus-visible {
    border-color: var(--blue);
    color: var(--blue);
  }

  .tab.active {
    background: linear-gradient(90deg, var(--blue) 0%, var(--teal) 45%, var(--orange) 100%);
    color: #ffffff;
    border-color: transparent;
  }

  .header-actions {
    display: flex;
    gap: 10px;
    flex-shrink: 0;
  }

  .btn-header {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    border: 1px solid var(--border);
    white-space: nowrap;
  }

  .btn-header.profile {
    color: var(--blue);
    background: #f2f7fb;
    border-color: #d7e6f2;
  }

  .btn-header.profile:hover,
  .btn-header.profile:focus-visible { background: #e6f0f9; }

  .btn-header.profile.active {
    color: #ffffff;
    background: linear-gradient(90deg, var(--blue) 0%, var(--teal) 100%);
    border-color: transparent;
  }

  .btn-header.logout {
    color: #ffffff;
    background: linear-gradient(90deg, var(--orange) 0%, #d9534f 100%);
    border: none;
  }

  .btn-header.logout:hover,
  .btn-header.logout:focus-visible { filter: brightness(1.05); }

  /* ---- 1024 bis 1279 px: gleiche Zeile, etwas kompakter ---- */
  @media (max-width: 1279px) {
    .header-bar { gap: 14px; padding: 0 16px; }
    .header-logo img { width: 46px; height: 46px; }
    .header-logo span { font-size: 26px; }
    .tab-group { gap: 10px; }
    .tab { padding: 9px 10px; font-size: 13px; }
    .header-actions { gap: 8px; }
    .btn-header { padding: 8px 12px; }
  }

  /* ---- Unter 1024 px: Kopfzeile darf umbrechen ----
     Logo und Knoepfe bleiben oben, die Tabs rutschen in eine eigene Zeile.
     Die Kopfzeile ist dann nicht mehr fixiert (ihre Hoehe haengt vom
     Umbruch ab), das Banner folgt ihr im normalen Fluss. */
  @media (max-width: 1023px) {
    body { padding-top: 0; }

    .header-bar {
      position: relative;
      height: auto;
      min-height: 78px;
      flex-wrap: wrap;
      row-gap: 10px;
      padding: 10px 16px 12px;
    }

    .header-logo { margin-right: auto; }

    .tab-group,
    .tab-group.vier {
      order: 3;
      flex: 1 1 100%;
      max-width: none;
      flex-wrap: wrap;
      gap: 8px;
    }

    .header-actions { flex-wrap: wrap; }

    .banner-spacer { display: none; }

    .top-banner {
      position: relative;
      top: 0;
    }

    /* Die Filterleiste der Listen-Seiten haengt sonst fest bei 208 px und
       wuerde von einer umgebrochenen Kopfzeile ueberdeckt. Ab 700 px abwaerts
       stellt jede Seite sie ohnehin selbst in den normalen Fluss. */
    .main-area { position: relative; }
  }

  @media (min-width: 701px) and (max-width: 1023px) {
    .sidebar {
      position: absolute;
      top: 0;
      right: 16px;
    }
  }
</style>
<?php
}
