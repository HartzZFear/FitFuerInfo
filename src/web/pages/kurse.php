<?php
// kurse.php
// Reine Darstellungsseite ohne Funktionalität, im gleichen visuellen Stil wie login.php
// (Wellen-Header, Farbpalette Türkis/Blau/Orange, weiße Cards mit Schatten).

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../kurs_rechte.php';

erfordere_login();

$meineId  = benutzer_id();
$istAdmin = ist_admin();

$suche     = isset($_GET['suche']) ? trim($_GET['suche']) : '';
$nurEigene = isset($_GET['ownership']) && $_GET['ownership'] === 'own';

$bedingungen = array();
$parameter   = array();

$sql = 'SELECT DISTINCT k.id, k.titel, k.beschreibung, k.max_teilnehmer, k.ersteller_id
        FROM kurs k
        LEFT JOIN kurs_eigentuemer ke ON ke.kurs_id = k.id';

if ($suche !== '') {
    $bedingungen[] = 'k.titel LIKE ?';
    $parameter[]   = '%' . $suche . '%';
}

if ($nurEigene) {
    $bedingungen[] = '(k.ersteller_id = ? OR ke.benutzer_id = ?)';
    $parameter[]   = $meineId;
    $parameter[]   = $meineId;
}

if (!empty($bedingungen)) {
    $sql .= ' WHERE ' . implode(' AND ', $bedingungen);
}

$sql .= ' ORDER BY k.titel';

$kurse = abfrage($sql, $parameter)->fetchAll();

// Eigentuemer und Software je Kurs nachladen (fuer Berechtigungen und Chips).
$eigentuemerJeKurs = array();
$softwareJeKurs    = array();

$kursIds = array();
foreach ($kurse as $k) {
    $kursIds[] = $k['id'];
}

if (!empty($kursIds)) {
    $platzhalter = implode(',', array_fill(0, count($kursIds), '?'));

    $zeilen = abfrage(
        'SELECT kurs_id, benutzer_id FROM kurs_eigentuemer WHERE kurs_id IN (' . $platzhalter . ')',
        $kursIds
    )->fetchAll();
    foreach ($zeilen as $zeile) {
        $eigentuemerJeKurs[$zeile['kurs_id']][] = (int) $zeile['benutzer_id'];
    }

    $zeilen = abfrage(
        'SELECT ks.kurs_id, s.name
         FROM kurs_software ks
         JOIN software s ON s.id = ks.software_id
         WHERE ks.kurs_id IN (' . $platzhalter . ')
         ORDER BY s.name',
        $kursIds
    )->fetchAll();
    foreach ($zeilen as $zeile) {
        $softwareJeKurs[$zeile['kurs_id']][] = $zeile['name'];
    }
}

/**
 * Nur fuer die Kartenanzeige: ist der eingeloggte Benutzer Ersteller oder
 * in kurs_eigentuemer eingetragen? Die massgebliche, serverseitige Pruefung
 * fuer Bearbeiten/Loeschen selbst steht in kurs_darf_verwalten() (kurse.php).
 */
function ist_eigene_karte($kurs, $eigentuemerJeKurs, $benutzerId)
{
    if ((int) $kurs['ersteller_id'] === (int) $benutzerId) {
        return true;
    }
    return isset($eigentuemerJeKurs[$kurs['id']])
        && in_array((int) $benutzerId, $eigentuemerJeKurs[$kurs['id']], true);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kurse – FitFürInfo</title>
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
    padding-top: 78px; /* Platz für die fixierte Kopfzeile */
  }

  /* ---- Dekorativer Wellen-Header: fixiert unter der Kopfzeile, wird per JS
     beim Scrollen sanft (parallax + fade) ausgeblendet statt abrupt zu verschwinden ---- */
  .banner-spacer {
    height: 110px;
  }

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
    flex: 1;
    display: flex;
    gap: 16px;
    max-width: 380px;
    margin: 0 auto;
  }

  .tab {
    flex: 1;
    text-align: center;
    padding: 10px 0;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-muted);
    text-decoration: none;
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
  .btn-header.profile:focus-visible {
    background: #e6f0f9;
  }

  .btn-header.logout {
    color: #ffffff;
    background: linear-gradient(90deg, var(--orange) 0%, #d9534f 100%);
    border: none;
  }

  .btn-header.logout:hover,
  .btn-header.logout:focus-visible {
    filter: brightness(1.05);
  }

  /* ---- Hauptbereich ---- */
  .main-area {
    max-width: 1600px;
    width: 92%;
    margin: 20px auto 40px;
    padding: 0 16px 16px;
    padding-right: 292px; /* Platz für die fest positionierte Sidebar */
  }

  .course-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
  }

  .course-card {
    background: var(--card-bg);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 12px 28px rgba(20, 40, 50, 0.1);
    display: flex;
    flex-direction: column;
  }

  .course-body {
    padding: 16px 18px 18px;
    flex: 1;
    display: flex;
    flex-direction: column;
  }

  .course-title {
    margin: 0 0 8px;
    font-size: 19px;
    font-weight: 700;
    color: var(--text-dark);
  }

  .course-desc {
    margin: 0;
    font-size: 13px;
    color: var(--text-muted);
    line-height: 1.4;
    flex: 1;
  }

  .course-actions {
    display: flex;
    gap: 10px;
    margin-top: 16px;
  }

  .btn-edit,
  .btn-delete {
    flex: 1;
    border-radius: 8px;
    padding: 9px 0;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
  }

  .btn-edit {
    background: linear-gradient(90deg, var(--blue) 0%, var(--teal) 100%);
    color: #ffffff;
    border: none;
  }

  .btn-edit:hover {
    filter: brightness(1.05);
  }

  .btn-delete {
    background: linear-gradient(90deg, var(--orange) 0%, #d9534f 100%);
    color: #ffffff;
    border: none;
  }

  .btn-delete:hover {
    filter: brightness(1.05);
  }

  /* ---- Sidebar im Card-Look der Login-Seite: fest am rechten Bildschirmrand ---- */
  .sidebar {
    position: fixed;
    top: 208px;
    right: 24px;
    width: 240px;
    background: var(--card-bg);
    border-radius: 14px;
    box-shadow: 0 12px 28px rgba(20, 40, 50, 0.1);
    padding: 20px 20px 70px;
    z-index: 2;
  }

  .sidebar-label {
    color: var(--blue);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.4px;
    margin: 0 0 8px;
    text-transform: uppercase;
  }

  .search-input {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 14px;
    color: var(--text-dark);
    background: #fbfcfc;
    outline: none;
    margin-bottom: 26px;
  }

  .search-input::placeholder {
    color: #a7b2b6;
  }

  .search-input:focus {
    border-color: var(--blue);
    background: #ffffff;
  }

  .radio-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .radio-option {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: var(--text-dark);
    cursor: pointer;
  }

  .radio-option input[type="radio"] {
    accent-color: var(--blue);
    width: 16px;
    height: 16px;
  }

  .btn-add {
    position: absolute;
    bottom: 18px;
    right: 18px;
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: linear-gradient(90deg, var(--blue) 0%, var(--teal) 45%, var(--orange) 100%);
    color: #ffffff;
    border: none;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
    box-shadow: 0 8px 18px rgba(20, 40, 50, 0.25);
  }

  .btn-add:hover {
    filter: brightness(1.05);
  }

  @media (min-width: 1400px) {
    .course-grid {
      grid-template-columns: repeat(4, 1fr);
    }
  }

  @media (max-width: 700px) {
    .main-area {
      padding-right: 16px;
    }
    .course-grid {
      grid-template-columns: 1fr;
    }
    .sidebar {
      position: static;
      width: 100%;
      order: -1;
      margin-bottom: 20px;
    }
  }

  /* ---- Ergänzungen für die Datenanbindung der Kursverwaltung ---- */
  .course-meta {
    margin: 0 0 8px;
    font-size: 12px;
    color: var(--text-muted);
  }

  .software-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: 10px 0 0;
  }

  .chip {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    color: var(--teal);
    background: rgba(26, 143, 156, 0.1);
    border: 1px solid rgba(26, 143, 156, 0.25);
  }

  .empty-state {
    grid-column: 1 / -1;
    text-align: center;
    color: var(--text-muted);
    font-size: 14px;
    padding: 40px 20px;
  }

  a.btn-edit,
  a.btn-delete {
    display: inline-block;
    text-decoration: none;
  }

  a.btn-add {
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
  }

  .filter-submit {
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
</style>
</head>
<body>


  <header class="header-bar">
    <div class="header-logo">
      <img src="<?php echo BASE_URL; ?>/src/web/assets/logo.png" alt="">
      <span><span class="fit">FitFür</span><span class="info">Info</span></span>
    </div>

    <nav class="tab-group">
      <a href="kurse.php" class="tab active">Kurse</a>
      <a href="raeume.php" class="tab">Räume</a>
      <a href="buchungen.php" class="tab">Belegung</a>
    </nav>

    <div class="header-actions">
      <a href="#" class="btn-header profile">Profile</a>
      <a href="logout.php" class="btn-header logout">Log Out</a>
    </div>
  </header>

  <div class="banner-spacer"></div>

  <!-- Dekorativer Wellen-Streifen, identisch zum Login-Header -->
  <div class="top-banner" aria-hidden="true" id="waveBanner">
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
    <div class="help-region">
      <a href="#" class="help-icon" aria-label="Hilfe">?</a>
      <div class="lang-select">🇩🇪 DE ▾</div>
    </div>
  </div>

  <div class="main-area">

    <section class="course-grid">

<?php if (empty($kurse)): ?>
      <p class="empty-state">Keine Kurse gefunden. Andere Suche oder anderen Filter probieren.</p>
<?php else: ?>
  <?php foreach ($kurse as $kurs): ?>
      <article class="course-card">
        <div class="course-body">
          <h3 class="course-title"><?php echo h($kurs['titel']); ?></h3>
          <p class="course-meta">max. <?php echo (int) $kurs['max_teilnehmer']; ?> Teilnehmer</p>
          <?php if ($kurs['beschreibung'] !== null && $kurs['beschreibung'] !== ''): ?>
          <p class="course-desc"><?php echo h($kurs['beschreibung']); ?></p>
          <?php endif; ?>
          <?php if (!empty($softwareJeKurs[$kurs['id']])): ?>
          <div class="software-chips">
            <?php foreach ($softwareJeKurs[$kurs['id']] as $softwareName): ?>
            <span class="chip"><?php echo h($softwareName); ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <?php if ($istAdmin || ist_eigene_karte($kurs, $eigentuemerJeKurs, $meineId)): ?>
          <div class="course-actions">
            <a href="kurs_bearbeiten.php?id=<?php echo (int) $kurs['id']; ?>" class="btn-edit">bearbeiten</a>
            <a href="kurs_loeschen.php?id=<?php echo (int) $kurs['id']; ?>" class="btn-delete">löschen</a>
          </div>
          <?php endif; ?>
        </div>
      </article>
  <?php endforeach; ?>
<?php endif; ?>

    </section>

    <aside class="sidebar">
      <form method="get" action="kurse.php">
        <p class="sidebar-label">Suche</p>
        <input type="text" name="suche" class="search-input" placeholder="Kursname....." value="<?php echo h($suche); ?>">

        <p class="sidebar-label">Eigentümerschaft</p>
        <div class="radio-group">
          <label class="radio-option">
            <input type="radio" name="ownership" value="own" onchange="this.form.submit()" <?php echo $nurEigene ? 'checked' : ''; ?>>
            Nur eigene
          </label>
          <label class="radio-option">
            <input type="radio" name="ownership" value="all" onchange="this.form.submit()" <?php echo $nurEigene ? '' : 'checked'; ?>>
            Alle
          </label>
        </div>

        <button type="submit" class="filter-submit">Suchen</button>
      </form>

      <a href="kurs_bearbeiten.php" class="btn-add" aria-label="Kurs hinzufügen">+</a>
    </aside>

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
</body>
</html>
