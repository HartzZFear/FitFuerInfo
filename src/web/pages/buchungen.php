<?php
// buchungen.php
// Belegungsliste: welcher Kurs liegt wann in welchem Raum.
// Gleicher visueller Stil wie kurse.php (Kopfzeile mit Tabs, Wellen-Banner,
// Sidebar mit Filtern, "+"-Knopf unten rechts in der Sidebar).
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../kurs_rechte.php';
require_once __DIR__ . '/../buchung_logik.php';

erfordere_login();

$meineId  = benutzer_id();
$istAdmin = ist_admin();

// --- Filter aus der URL ---------------------------------------------------
$filterRaumId = isset($_GET['raum_id']) ? (int) $_GET['raum_id'] : 0;
$filterKursId = isset($_GET['kurs_id']) ? (int) $_GET['kurs_id'] : 0;
$nurMeine     = isset($_GET['wer']) && $_GET['wer'] === 'meine';

// "ab Datum", Standard heute. Ungueltige Eingaben fallen auf heute zurueck.
$abDatum = isset($_GET['ab']) ? trim($_GET['ab']) : '';
$abObj   = DateTime::createFromFormat('Y-m-d', $abDatum);
if (!$abObj || $abObj->format('Y-m-d') !== $abDatum) {
    $abDatum = date('Y-m-d');
}

$bedingungen = array('b.start >= ?');
$parameter   = array($abDatum . ' 00:00:00');

if ($filterRaumId > 0) {
    $bedingungen[] = 'b.raum_id = ?';
    $parameter[]   = $filterRaumId;
}
if ($filterKursId > 0) {
    $bedingungen[] = 'b.kurs_id = ?';
    $parameter[]   = $filterKursId;
}
if ($nurMeine) {
    $bedingungen[] = 'b.benutzer_id = ?';
    $parameter[]   = $meineId;
}

$buchungen = abfrage(
    'SELECT b.id, b.benutzer_id, b.start, b.ende,
            r.name AS raum, k.titel AS kurs, u.name AS gebucht_von
     FROM buchung b
     JOIN raum r     ON r.id = b.raum_id
     JOIN kurs k     ON k.id = b.kurs_id
     JOIN benutzer u ON u.id = b.benutzer_id
     WHERE ' . implode(' AND ', $bedingungen) . '
     ORDER BY b.start, r.name',
    $parameter
)->fetchAll();

// Fuer die Filter-Dropdowns und den "+"-Knopf.
$alleRaeume = abfrage('SELECT id, name FROM raum ORDER BY name')->fetchAll();
$alleKurse  = abfrage('SELECT id, titel FROM kurs ORDER BY titel')->fetchAll();
$meineKurse = buchbare_kurse($meineId, $istAdmin);

$jetzt = new DateTime();

/**
 * Nur fuer die Anzeige der Buttons: gehoert die Buchung dem eingeloggten
 * Benutzer? Die Daten liegen durch den JOIN schon vor, deshalb hier ohne
 * eigene Abfrage. Die massgebliche Pruefung steht serverseitig in
 * buchung_darf_verwalten() (buchung_logik.php) und laeuft beim Aufruf von
 * buchung_bearbeiten.php bzw. buchung_loeschen.php erneut.
 */
function ist_eigene_buchung($buchung, $benutzerId, $istAdmin)
{
    if ($istAdmin) {
        return true;
    }
    return (int) $buchung['benutzer_id'] === (int) $benutzerId;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Belegung – FitFürInfo</title>
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

  /* ---- Dekorativer Wellen-Header, identisch zu kurse.php ---- */
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
  .btn-header.profile:focus-visible { background: #e6f0f9; }

  .btn-header.logout {
    color: #ffffff;
    background: linear-gradient(90deg, var(--orange) 0%, #d9534f 100%);
    border: none;
  }

  .btn-header.logout:hover,
  .btn-header.logout:focus-visible { filter: brightness(1.05); }

  /* ---- Hauptbereich ---- */
  .main-area {
    max-width: 1600px;
    width: 92%;
    margin: 20px auto 40px;
    padding: 0 16px 16px;
    padding-right: 292px; /* Platz für die fest positionierte Sidebar */
  }

  .liste-karte {
    background: var(--card-bg);
    border-radius: 14px;
    box-shadow: 0 12px 28px rgba(20, 40, 50, 0.1);
    padding: 8px 4px;
    overflow-x: auto;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
    color: var(--text-dark);
  }

  th {
    text-align: left;
    padding: 14px 16px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    color: var(--blue);
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
  }

  td {
    padding: 14px 16px;
    border-bottom: 1px solid #f0f4f5;
    vertical-align: middle;
  }

  tr:last-child td { border-bottom: none; }

  .spalte-kurs { font-weight: 600; }
  .spalte-zeit { white-space: nowrap; }

  .zeile-vergangen td { color: var(--text-muted); }

  .marke-vergangen {
    display: inline-block;
    margin-left: 8px;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
    background: #f0f4f5;
    border: 1px solid var(--border);
  }

  .zeilen-aktionen {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
  }

  .btn-edit,
  .btn-delete {
    display: inline-block;
    border-radius: 8px;
    padding: 7px 14px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    color: #ffffff;
    border: none;
  }

  .btn-edit { background: linear-gradient(90deg, var(--blue) 0%, var(--teal) 100%); }
  .btn-edit:hover { filter: brightness(1.05); }

  .btn-delete { background: linear-gradient(90deg, var(--orange) 0%, #d9534f 100%); }
  .btn-delete:hover { filter: brightness(1.05); }

  .empty-state {
    text-align: center;
    color: var(--text-muted);
    font-size: 14px;
    padding: 40px 20px;
  }

  /* ---- Sidebar mit den Filtern ---- */
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

  .sidebar select,
  .sidebar input[type="date"] {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 14px;
    font-family: inherit;
    color: var(--text-dark);
    background: #fbfcfc;
    outline: none;
    margin-bottom: 20px;
  }

  .sidebar select:focus,
  .sidebar input[type="date"]:focus {
    border-color: var(--blue);
    background: #ffffff;
  }

  .radio-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 18px;
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

  .link-zuruecksetzen {
    font-size: 13px;
    color: var(--text-muted);
    text-decoration: none;
  }
  .link-zuruecksetzen:hover { text-decoration: underline; }

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
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
  }

  .btn-add:hover { filter: brightness(1.05); }

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

  @media (max-width: 700px) {
    .main-area { padding-right: 16px; }
    .sidebar {
      position: static;
      width: 100%;
      margin-bottom: 20px;
    }
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
      <a href="kurse.php" class="tab">Kurse</a>
      <a href="raeume.php" class="tab">Räume</a>
      <a href="buchungen.php" class="tab active">Belegung</a>
    </nav>

    <div class="header-actions">
      <a href="#" class="btn-header profile">Profile</a>
      <a href="logout.php" class="btn-header logout">Log Out</a>
    </div>
  </header>

  <div class="banner-spacer"></div>

  <!-- Dekorativer Wellen-Streifen, identisch zu kurse.php -->
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

    <section class="liste-karte">
<?php if (empty($buchungen)): ?>
      <p class="empty-state">Keine Buchungen gefunden. Anderen Filter oder ein früheres Datum probieren.</p>
<?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Datum</th>
            <th>Uhrzeit</th>
            <th>Raum</th>
            <th>Kurs</th>
            <th>gebucht von</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
<?php foreach ($buchungen as $zeile): ?>
<?php
    $startObj    = new DateTime($zeile['start']);
    $endeObj     = new DateTime($zeile['ende']);
    $istVergangen = $startObj < $jetzt;
    $darfVerwalten = ist_eigene_buchung($zeile, $meineId, $istAdmin);
?>
          <tr<?php echo $istVergangen ? ' class="zeile-vergangen"' : ''; ?>>
            <td class="spalte-zeit">
              <?php echo h($startObj->format('d.m.Y')); ?>
              <?php if ($istVergangen): ?><span class="marke-vergangen">vorbei</span><?php endif; ?>
            </td>
            <td class="spalte-zeit">
              <?php echo h($startObj->format('H:i')); ?>–<?php echo h($endeObj->format('H:i')); ?>
            </td>
            <td><?php echo h($zeile['raum']); ?></td>
            <td class="spalte-kurs"><?php echo h($zeile['kurs']); ?></td>
            <td><?php echo h($zeile['gebucht_von']); ?></td>
            <td>
              <div class="zeilen-aktionen">
                <?php if ($darfVerwalten && !$istVergangen): ?>
                <a href="buchung_bearbeiten.php?id=<?php echo (int) $zeile['id']; ?>" class="btn-edit">bearbeiten</a>
                <?php endif; ?>
                <?php if ($darfVerwalten && (!$istVergangen || $istAdmin)): ?>
                <a href="buchung_loeschen.php?id=<?php echo (int) $zeile['id']; ?>" class="btn-delete">löschen</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
<?php endif; ?>
    </section>

    <aside class="sidebar">
      <form method="get" action="buchungen.php">
        <p class="sidebar-label">Raum</p>
        <select name="raum_id" onchange="this.form.submit()">
          <option value="0">Alle Räume</option>
          <?php foreach ($alleRaeume as $raumOption): ?>
          <option value="<?php echo (int) $raumOption['id']; ?>"
            <?php echo ((int) $raumOption['id'] === $filterRaumId) ? 'selected' : ''; ?>>
            <?php echo h($raumOption['name']); ?>
          </option>
          <?php endforeach; ?>
        </select>

        <p class="sidebar-label">Kurs</p>
        <select name="kurs_id" onchange="this.form.submit()">
          <option value="0">Alle Kurse</option>
          <?php foreach ($alleKurse as $kursOption): ?>
          <option value="<?php echo (int) $kursOption['id']; ?>"
            <?php echo ((int) $kursOption['id'] === $filterKursId) ? 'selected' : ''; ?>>
            <?php echo h($kursOption['titel']); ?>
          </option>
          <?php endforeach; ?>
        </select>

        <p class="sidebar-label">Ab Datum</p>
        <input type="date" name="ab" value="<?php echo h($abDatum); ?>" onchange="this.form.submit()">

        <p class="sidebar-label">Wer</p>
        <div class="radio-group">
          <label class="radio-option">
            <input type="radio" name="wer" value="meine" onchange="this.form.submit()" <?php echo $nurMeine ? 'checked' : ''; ?>>
            Nur meine Buchungen
          </label>
          <label class="radio-option">
            <input type="radio" name="wer" value="alle" onchange="this.form.submit()" <?php echo $nurMeine ? '' : 'checked'; ?>>
            Alle
          </label>
        </div>

        <a class="link-zuruecksetzen" href="buchungen.php">Filter zurücksetzen</a>

        <button type="submit" class="filter-submit">Filtern</button>
      </form>

      <?php if (!empty($meineKurse)): ?>
      <a href="buchung_bearbeiten.php" class="btn-add" aria-label="Buchung hinzufügen">+</a>
      <?php endif; ?>
    </aside>

  </div>

  <script>
    // Welle beim Scrollen sanft nach oben schieben und langsam ausblenden
    // (gleiches Verhalten wie in kurse.php).
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
