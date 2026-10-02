<?php
// belegung.php
// Belegungskalender in drei Ansichten: Monat, Woche (pro Raum), Tag (alle
// Raeume). Gleicher visueller Stil wie kurse.php und buchungen.php
// (Kopfzeile mit Tabs, Wellen-Banner, Sidebar mit Filtern).
//
// Die Logik (Parameter, Zeitraum, Verteilung auf Slots, Blaettern) steht in
// kalender_logik.php - diese Seite gibt nur aus.
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../kurs_rechte.php';
require_once __DIR__ . '/../buchung_logik.php';
require_once __DIR__ . '/../kalender_logik.php';

erfordere_login();

$meineId  = benutzer_id();
$istAdmin = ist_admin();

// Fuer die Filter-Dropdowns und die Pruefung der IDs aus der URL.
$alleRaeume = abfrage('SELECT id, name FROM raum ORDER BY name')->fetchAll();
$alleKurse  = abfrage('SELECT id, titel FROM kurs ORDER BY titel')->fetchAll();

$raumNamen = array();
foreach ($alleRaeume as $raum) {
    $raumNamen[(int) $raum['id']] = $raum['name'];
}
$kursIds = array();
foreach ($alleKurse as $kurs) {
    $kursIds[] = (int) $kurs['id'];
}

$param   = kalender_parameter($_GET, array_keys($raumNamen), $kursIds);
$ansicht = $param['ansicht'];
$datum   = $param['datum'];

// Buchen-Links nur, wenn der Benutzer mindestens einen Kurs buchen darf.
$meineKurse   = buchbare_kurse($meineId, $istAdmin);
$darfBuchen   = !empty($meineKurse);
$meineKursIds = array();
foreach ($meineKurse as $kurs) {
    $meineKursIds[] = (int) $kurs['id'];
}

// Die Wochenansicht zeigt genau einen Raum - ohne Filter den ersten nach
// Name. Der Filter in der URL bleibt dabei unveraendert, damit ein Wechsel
// in die Tagesansicht wieder alle Raeume zeigt.
$raumIdAnzeige = $param['raum_id'];
if ($ansicht === 'woche' && $raumIdAnzeige === 0 && !empty($alleRaeume)) {
    $raumIdAnzeige = (int) $alleRaeume[0]['id'];
}

$zeitraum  = kalender_zeitraum($ansicht, $datum);
$buchungen = empty($alleRaeume)
    ? array()
    : kalender_buchungen_laden($zeitraum['von'], $zeitraum['bis'], $raumIdAnzeige);

$slots    = kalender_slots();
$heute    = date('Y-m-d');
$titel    = kalender_titel($ansicht, $datum);

// Spalten der Slot-Ansichten: Woche = Tage, Tag = Raeume.
$spalten = array();
if ($ansicht === 'woche') {
    $spalten = $zeitraum['tage'];
    $raster  = kalender_raster($buchungen, $spalten, 'datum', $param, $meineId);
} elseif ($ansicht === 'tag') {
    foreach ($raumNamen as $raumId => $raumName) {
        if ($param['raum_id'] === 0 || $param['raum_id'] === $raumId) {
            $spalten[] = (string) $raumId;
        }
    }
    $raster = kalender_raster($buchungen, $spalten, 'raum_id', $param, $meineId);
} else {
    $jeTag = kalender_nach_tag($buchungen, $param, $meineId);
}

// Legende: nur die Kurse, die im sichtbaren Zeitraum auch vorkommen.
$legendeKurse = array();
foreach ($buchungen as $buchung) {
    if (kalender_sichtbar($buchung, $param, $meineId)) {
        $legendeKurse[(int) $buchung['kurs_id']] = $buchung['kurs'];
    }
}
asort($legendeKurse);

// Blaettern und Umschalten - die Filter bleiben ueber kalender_url() erhalten.
$urlZurueck = kalender_url($param, array('datum' => kalender_blaettern($ansicht, $datum, -1)));
$urlWeiter  = kalender_url($param, array('datum' => kalender_blaettern($ansicht, $datum, 1)));
$urlHeute   = kalender_url($param, array('datum' => kalender_heute()));

$beschriftungZurueck = array('monat' => 'Vormonat', 'woche' => 'Vorwoche', 'tag' => 'Vortag');
$beschriftungWeiter  = array('monat' => 'Folgemonat', 'woche' => 'Folgewoche', 'tag' => 'Folgetag');

// Listenansicht mit denselben Filtern, ab dem ersten sichtbaren Tag.
$listenParameter = array('ab' => substr($zeitraum['von'], 0, 10));
if ($raumIdAnzeige > 0) {
    $listenParameter['raum_id'] = $raumIdAnzeige;
}
if ($param['kurs_id'] > 0) {
    $listenParameter['kurs_id'] = $param['kurs_id'];
}
if ($param['nur_meine']) {
    $listenParameter['wer'] = 'meine';
}
$urlListe = 'buchungen.php?' . http_build_query($listenParameter);

/**
 * Link zum Anlegen einer Buchung in einem freien Slot. Ist ein Kurs
 * gefiltert, den der Benutzer buchen darf, wird er gleich mit vorbelegt.
 */
function buchen_url($raumId, $datum, $zeit, $param, $meineKursIds)
{
    $query = array(
        'raum_id' => (int) $raumId,
        'datum'   => $datum,
        'start'   => $zeit,
    );
    if ($param['kurs_id'] > 0 && in_array($param['kurs_id'], $meineKursIds, true)) {
        $query['kurs_id'] = $param['kurs_id'];
    }

    return 'buchung_bearbeiten.php?' . http_build_query($query);
}

/**
 * Text fuer das title-Attribut eines Buchungsblocks.
 */
function buchung_tooltip($buchung)
{
    return $buchung['kurs'] . ' · ' . substr($buchung['start'], 11, 5) . '–' . substr($buchung['ende'], 11, 5)
        . ' · ' . $buchung['raum'] . ' · gebucht von ' . $buchung['gebucht_von'];
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
    --slot-hoehe: 24px;
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

  .kalender-karte {
    background: var(--card-bg);
    border-radius: 14px;
    box-shadow: 0 12px 28px rgba(20, 40, 50, 0.1);
    padding: 18px 18px 20px;
  }

  /* ---- Werkzeugleiste: Ansicht, Blättern, Überschrift ---- */
  .werkzeugleiste {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 14px;
    margin-bottom: 16px;
  }

  .kalender-titel {
    flex: 1;
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: var(--text-dark);
    white-space: nowrap;
  }

  .umschalter,
  .blaettern {
    display: flex;
    gap: 6px;
  }

  .knopf {
    display: inline-block;
    padding: 7px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-muted);
    text-decoration: none;
    border: 1px solid var(--border);
    background: #fbfcfc;
    white-space: nowrap;
  }

  .knopf:hover,
  .knopf:focus-visible {
    border-color: var(--blue);
    color: var(--blue);
  }

  .knopf.active {
    background: linear-gradient(90deg, var(--blue) 0%, var(--teal) 100%);
    color: #ffffff;
    border-color: transparent;
  }

  .empty-state {
    text-align: center;
    color: var(--text-muted);
    font-size: 14px;
    padding: 40px 20px;
  }

  /* ---- Woche / Tag: Raster aus Halbstunden-Slots (CSS Grid) ---- */
  .slot-scroll { overflow-x: auto; }

  .slot-raster {
    display: grid;
    /* grid-template-columns setzt die Seite inline (Anzahl Spalten) */
    grid-template-rows: auto repeat(<?php echo count($slots); ?>, var(--slot-hoehe));
    font-size: 12px;
    color: var(--text-dark);
  }

  .kopf-zelle {
    padding: 8px 6px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    color: var(--blue);
    text-align: center;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .slot-raster .kopf-zelle { grid-row: 1; }

  .kopf-zelle a { color: inherit; text-decoration: none; }
  .kopf-zelle a:hover { text-decoration: underline; }
  .kopf-zelle.heute { color: var(--orange); }

  .zeit-zelle {
    padding-right: 8px;
    font-size: 11px;
    color: var(--text-muted);
    text-align: right;
    line-height: 1;
    transform: translateY(-5px); /* Uhrzeit sitzt auf der Linie */
  }

  .zeit-zelle.volle-stunde { font-weight: 700; color: var(--text-dark); }

  .slot {
    display: block;
    border-top: 1px solid #f0f4f5;
    border-left: 1px solid #f0f4f5;
  }

  .slot.volle-stunde { border-top-color: var(--border); }
  .slot.vergangen { background: #f7f9f9; }

  a.slot {
    color: transparent;
    text-decoration: none;
    font-size: 16px;
    font-weight: 700;
    text-align: center;
    line-height: var(--slot-hoehe);
  }

  a.slot:hover,
  a.slot:focus-visible {
    color: var(--blue);
    background: #eef5fb;
    outline: none;
  }

  .block {
    display: block;
    margin: 1px 3px;
    padding: 4px 8px;
    border-radius: 6px;
    border-left: 4px solid;
    overflow: hidden;
    text-decoration: none;
    color: var(--text-dark);
    line-height: 1.35;
    position: relative;
    z-index: 1;
  }

  .block strong { display: block; font-size: 12px; }
  .block span { display: block; font-size: 11px; color: #4d5d65; }

  a.block:hover,
  a.block:focus-visible { filter: brightness(0.95); }

  .block.vergangen { opacity: 0.55; }

  /* Eigene Buchung: zusätzlich ein deutlicher dunkler Rahmen */
  .block.eigene { box-shadow: inset 0 0 0 2px var(--text-dark); }

  /* Durch Kurs-/"nur meine"-Filter ausgeblendet, belegt den Slot aber trotzdem */
  .block.verdeckt {
    border-left-color: #c3ccd0;
    background: repeating-linear-gradient(45deg, #f0f3f4 0 6px, #e6eaec 6px 12px);
  }

  /* ---- Feste Kursfarben (Index aus kalender_kursfarbe()) ---- */
  .farbe-0 { background: rgba(26, 143, 156, 0.16);  border-color: #1a8f9c; }
  .farbe-1 { background: rgba(47, 111, 176, 0.16);  border-color: #2f6fb0; }
  .farbe-2 { background: rgba(232, 121, 46, 0.18);  border-color: #e8792e; }
  .farbe-3 { background: rgba(123, 94, 167, 0.16);  border-color: #7b5ea7; }
  .farbe-4 { background: rgba(58, 154, 91, 0.16);   border-color: #3a9a5b; }
  .farbe-5 { background: rgba(200, 80, 75, 0.16);   border-color: #c8504b; }
  .farbe-6 { background: rgba(201, 154, 26, 0.20);  border-color: #c99a1a; }
  .farbe-7 { background: rgba(90, 111, 124, 0.16);  border-color: #5a6f7c; }

  /* ---- Monat ---- */
  .monat-raster {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    border-top: 1px solid var(--border);
    border-left: 1px solid var(--border);
  }

  .monat-raster .kopf-zelle {
    border-right: 1px solid var(--border);
  }

  .monat-tag {
    display: block;
    min-height: 104px;
    padding: 6px 8px 8px;
    border-right: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    color: var(--text-dark);
    text-decoration: none;
    overflow: hidden;
  }

  .monat-tag:hover,
  .monat-tag:focus-visible { background: #f5f9fb; outline: none; }

  .monat-tag.anderer-monat { background: #f9fbfb; }
  .monat-tag.anderer-monat .tag-nummer { color: #b5c0c4; }

  .tag-nummer {
    display: inline-block;
    min-width: 24px;
    margin-bottom: 4px;
    padding: 2px 6px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 700;
    text-align: center;
  }

  .monat-tag.heute .tag-nummer {
    background: linear-gradient(90deg, var(--blue) 0%, var(--teal) 100%);
    color: #ffffff;
  }

  .eintrag {
    display: block;
    margin-top: 3px;
    padding: 2px 6px;
    border-radius: 4px;
    border-left: 3px solid;
    font-size: 11px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .eintrag.eigene { box-shadow: inset 0 0 0 1px var(--text-dark); font-weight: 600; }

  .weitere {
    display: block;
    margin-top: 4px;
    font-size: 11px;
    font-weight: 600;
    color: var(--blue);
  }

  /* ---- Legende ---- */
  .legende {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 18px;
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
    font-size: 12px;
    color: var(--text-muted);
  }

  .legende-eintrag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  .legende-farbe {
    display: inline-block;
    width: 16px;
    height: 12px;
    border-radius: 3px;
    border-left: 4px solid;
  }

  .legende-farbe.eigene { background: #ffffff; border-left: none; box-shadow: inset 0 0 0 2px var(--text-dark); }
  .legende-farbe.verdeckt {
    border-left-color: #c3ccd0;
    background: repeating-linear-gradient(45deg, #f0f3f4 0 3px, #e6eaec 3px 6px);
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

  .sidebar select {
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

  .sidebar select:focus {
    border-color: var(--blue);
    background: #ffffff;
  }

  .check-option {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: var(--text-dark);
    cursor: pointer;
    margin-bottom: 18px;
  }

  .check-option input[type="checkbox"] {
    accent-color: var(--blue);
    width: 16px;
    height: 16px;
  }

  .sidebar-links {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .link-zuruecksetzen {
    font-size: 13px;
    color: var(--text-muted);
    text-decoration: none;
  }
  .link-zuruecksetzen:hover { text-decoration: underline; }

  .link-ansicht {
    font-size: 13px;
    font-weight: 600;
    color: var(--blue);
    text-decoration: none;
  }
  .link-ansicht:hover { text-decoration: underline; }

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
    .kalender-titel { flex-basis: 100%; }
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
      <a href="belegung.php" class="tab active">Belegung</a>
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

    <section class="kalender-karte">

      <div class="werkzeugleiste">
        <h2 class="kalender-titel">
          <?php echo h($titel); ?>
          <?php if ($ansicht === 'woche' && $raumIdAnzeige > 0): ?>
          · <?php echo h($raumNamen[$raumIdAnzeige]); ?>
          <?php endif; ?>
        </h2>

        <nav class="umschalter" aria-label="Ansicht">
          <?php foreach (kalender_ansichten() as $wert => $beschriftung): ?>
          <a href="<?php echo h(kalender_url($param, array('ansicht' => $wert))); ?>"
             class="knopf<?php echo $wert === $ansicht ? ' active' : ''; ?>"><?php echo h($beschriftung); ?></a>
          <?php endforeach; ?>
        </nav>

        <nav class="blaettern" aria-label="Blättern">
          <a href="<?php echo h($urlZurueck); ?>" class="knopf" title="<?php echo h($beschriftungZurueck[$ansicht]); ?>">‹ zurück</a>
          <a href="<?php echo h($urlHeute); ?>" class="knopf">Heute</a>
          <a href="<?php echo h($urlWeiter); ?>" class="knopf" title="<?php echo h($beschriftungWeiter[$ansicht]); ?>">weiter ›</a>
        </nav>
      </div>

<?php if (empty($alleRaeume)): ?>
      <p class="empty-state">Es sind noch keine Räume angelegt.</p>

<?php elseif ($ansicht === 'monat'): ?>
      <div class="monat-raster">
        <?php for ($n = 1; $n <= 5; $n++): ?>
        <div class="kopf-zelle"><?php echo h(kalender_wochentag_kurz($n)); ?></div>
        <?php endfor; ?>

        <?php foreach ($zeitraum['tage'] as $tag): ?>
        <?php
            $tagObj      = DateTime::createFromFormat('!Y-m-d', $tag);
            $eintraege   = isset($jeTag[$tag]) ? $jeTag[$tag] : array();
            $anzahl      = count($eintraege);
            $zeigen      = $anzahl >= KALENDER_MONAT_MAX ? KALENDER_MONAT_MAX - 1 : $anzahl;
            $klassen     = 'monat-tag';
            if ($tagObj->format('Y-m') !== $datum->format('Y-m')) {
                $klassen .= ' anderer-monat';
            }
            if ($tag === $heute) {
                $klassen .= ' heute';
            }
        ?>
        <a href="<?php echo h(kalender_url($param, array('ansicht' => 'tag', 'datum' => $tag))); ?>"
           class="<?php echo $klassen; ?>"
           title="Tagesansicht <?php echo h($tagObj->format('d.m.Y')); ?>">
          <span class="tag-nummer"><?php echo h($tagObj->format('j')); ?></span>
          <?php for ($i = 0; $i < $zeigen; $i++): ?>
          <?php $buchung = $eintraege[$i]; ?>
          <span class="eintrag farbe-<?php echo kalender_kursfarbe($buchung['kurs_id']); ?><?php echo (int) $buchung['benutzer_id'] === (int) $meineId ? ' eigene' : ''; ?>"
                title="<?php echo h(buchung_tooltip($buchung)); ?>">
            <?php echo h(substr($buchung['start'], 11, 5) . '–' . substr($buchung['ende'], 11, 5)); ?>
            <?php echo h($buchung['kurs']); ?> (<?php echo h($buchung['raum']); ?>)
          </span>
          <?php endfor; ?>
          <?php if ($anzahl > $zeigen): ?>
          <span class="weitere">+<?php echo (int) ($anzahl - $zeigen); ?> weitere</span>
          <?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>

<?php else: ?>
      <div class="slot-scroll">
        <div class="slot-raster"
             style="grid-template-columns: 56px repeat(<?php echo count($spalten); ?>, minmax(<?php echo $ansicht === 'tag' ? 150 : 120; ?>px, 1fr));">

          <div class="kopf-zelle"></div>
          <?php foreach ($spalten as $s => $spalte): ?>
          <?php if ($ansicht === 'woche'): ?>
          <?php $tagObj = DateTime::createFromFormat('!Y-m-d', $spalte); ?>
          <div class="kopf-zelle<?php echo $spalte === $heute ? ' heute' : ''; ?>">
            <a href="<?php echo h(kalender_url($param, array('ansicht' => 'tag', 'datum' => $spalte))); ?>"
               title="Tagesansicht">
              <?php echo h(kalender_wochentag_kurz((int) $tagObj->format('N')) . ' ' . $tagObj->format('d.m.')); ?>
            </a>
          </div>
          <?php else: ?>
          <div class="kopf-zelle">
            <a href="<?php echo h(kalender_url($param, array('ansicht' => 'woche', 'raum_id' => (int) $spalte))); ?>"
               title="Wochenansicht dieses Raums">
              <?php echo h($raumNamen[(int) $spalte]); ?>
            </a>
          </div>
          <?php endif; ?>
          <?php endforeach; ?>

          <?php foreach ($slots as $i => $zeit): ?>
          <?php $volleStunde = substr($zeit, 3, 2) === '00'; ?>
          <div class="zeit-zelle<?php echo $volleStunde ? ' volle-stunde' : ''; ?>"
               style="grid-column: 1; grid-row: <?php echo $i + 2; ?>;"><?php echo $volleStunde ? h($zeit) : ''; ?></div>
          <?php endforeach; ?>

          <?php foreach ($spalten as $s => $spalte): ?>
          <?php
              $spaltenTag  = $ansicht === 'woche' ? $spalte : $datum->format('Y-m-d');
              $spaltenRaum = $ansicht === 'woche' ? $raumIdAnzeige : (int) $spalte;
          ?>
          <?php foreach ($raster[$spalte] as $i => $zelle): ?>
          <?php
              if ($zelle['typ'] === 'belegt') {
                  continue; // von einem Block darueber abgedeckt
              }
              $position = 'grid-column: ' . ($s + 2) . '; grid-row: ' . ($i + 2);
          ?>
          <?php if ($zelle['typ'] === 'frei'): ?>
          <?php
              $zeit     = $slots[$i];
              $klassen  = 'slot' . (substr($zeit, 3, 2) === '00' ? ' volle-stunde' : '');
              $buchbar  = kalender_slot_buchbar($spaltenTag, $zeit, $darfBuchen);
              if (buchung_ist_vergangen($spaltenTag . ' ' . $zeit . ':00')) {
                  $klassen .= ' vergangen';
              }
          ?>
          <?php if ($buchbar): ?>
          <a href="<?php echo h(buchen_url($spaltenRaum, $spaltenTag, $zeit, $param, $meineKursIds)); ?>"
             class="<?php echo $klassen; ?>" style="<?php echo $position; ?>;"
             title="<?php echo h($zeit); ?> – hier buchen">+</a>
          <?php else: ?>
          <div class="<?php echo $klassen; ?>" style="<?php echo $position; ?>;"></div>
          <?php endif; ?>

          <?php else: ?>
          <?php
              $buchung  = $zelle['buchung'];
              $position .= ' / span ' . (int) $zelle['slots'];
          ?>
          <?php if (!$zelle['sichtbar']): ?>
          <div class="block verdeckt" style="<?php echo $position; ?>;"
               title="belegt (durch Filter ausgeblendet)"></div>
          <?php else: ?>
          <?php
              $klassen = 'block farbe-' . kalender_kursfarbe($buchung['kurs_id']);
              if ((int) $buchung['benutzer_id'] === (int) $meineId) {
                  $klassen .= ' eigene';
              }
              if (buchung_ist_vergangen($buchung['start'])) {
                  $klassen .= ' vergangen';
              }
              // Massgeblich ist buchung_darf_bearbeiten() (setzt auf
              // buchung_darf_verwalten() auf): vergangene oder fremde
              // Buchungen bekommen keinen Link, nur den Tooltip.
              $darfBearbeiten = buchung_darf_bearbeiten($buchung, $meineId, $istAdmin);
              $tooltip = buchung_tooltip($buchung) . ($darfBearbeiten ? ' – klicken zum Bearbeiten' : '');
          ?>
          <?php if ($darfBearbeiten): ?>
          <a href="buchung_bearbeiten.php?id=<?php echo (int) $buchung['id']; ?>"
             class="<?php echo $klassen; ?>" style="<?php echo $position; ?>;" title="<?php echo h($tooltip); ?>">
          <?php else: ?>
          <div class="<?php echo $klassen; ?>" style="<?php echo $position; ?>;" title="<?php echo h($tooltip); ?>">
          <?php endif; ?>
            <strong><?php echo h($buchung['kurs']); ?></strong>
            <span><?php echo h(substr($buchung['start'], 11, 5) . '–' . substr($buchung['ende'], 11, 5)); ?></span>
            <span>gebucht von <?php echo h($buchung['gebucht_von']); ?></span>
          <?php echo $darfBearbeiten ? '</a>' : '</div>'; ?>
          <?php endif; ?>
          <?php endif; ?>
          <?php endforeach; ?>
          <?php endforeach; ?>

        </div>
      </div>
<?php endif; ?>

      <div class="legende">
        <?php foreach ($legendeKurse as $kursId => $kursTitel): ?>
        <span class="legende-eintrag">
          <span class="legende-farbe farbe-<?php echo kalender_kursfarbe($kursId); ?>"></span>
          <?php echo h($kursTitel); ?>
        </span>
        <?php endforeach; ?>
        <span class="legende-eintrag">
          <span class="legende-farbe eigene"></span> eigene Buchung
        </span>
        <?php if ($ansicht !== 'monat'): ?>
        <span class="legende-eintrag">
          <span class="legende-farbe verdeckt"></span> belegt, durch Filter ausgeblendet
        </span>
        <?php if ($darfBuchen): ?>
        <span class="legende-eintrag">Freien Slot anklicken, um zu buchen</span>
        <?php endif; ?>
        <?php else: ?>
        <span class="legende-eintrag">Tag anklicken für die Tagesansicht</span>
        <?php endif; ?>
      </div>

    </section>

    <aside class="sidebar">
      <form method="get" action="belegung.php">
        <input type="hidden" name="ansicht" value="<?php echo h($ansicht); ?>">
        <input type="hidden" name="datum" value="<?php echo h($datum->format('Y-m-d')); ?>">

        <p class="sidebar-label">Raum</p>
        <select name="raum_id" onchange="this.form.submit()">
          <?php if ($ansicht !== 'woche'): ?>
          <option value="0">Alle Räume</option>
          <?php endif; ?>
          <?php foreach ($alleRaeume as $raumOption): ?>
          <option value="<?php echo (int) $raumOption['id']; ?>"
            <?php echo ((int) $raumOption['id'] === $raumIdAnzeige) ? 'selected' : ''; ?>>
            <?php echo h($raumOption['name']); ?>
          </option>
          <?php endforeach; ?>
        </select>

        <p class="sidebar-label">Kurs</p>
        <select name="kurs_id" onchange="this.form.submit()">
          <option value="0">Alle Kurse</option>
          <?php foreach ($alleKurse as $kursOption): ?>
          <option value="<?php echo (int) $kursOption['id']; ?>"
            <?php echo ((int) $kursOption['id'] === $param['kurs_id']) ? 'selected' : ''; ?>>
            <?php echo h($kursOption['titel']); ?>
          </option>
          <?php endforeach; ?>
        </select>

        <label class="check-option">
          <input type="checkbox" name="nur_meine" value="1" onchange="this.form.submit()" <?php echo $param['nur_meine'] ? 'checked' : ''; ?>>
          Nur meine Buchungen
        </label>

        <div class="sidebar-links">
          <a class="link-ansicht" href="<?php echo h($urlListe); ?>">Listenansicht</a>
          <a class="link-zuruecksetzen"
             href="<?php echo h(kalender_url(array('ansicht' => $ansicht, 'datum' => $datum, 'raum_id' => 0, 'kurs_id' => 0, 'nur_meine' => false))); ?>">Filter zurücksetzen</a>
        </div>

        <button type="submit" class="filter-submit">Filtern</button>
      </form>

      <?php if ($darfBuchen): ?>
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
