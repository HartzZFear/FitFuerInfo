<?php
// benutzer.php
// Benutzerliste fuer den Systemverwalter. Gleicher visueller Stil wie
// raeume.php (Kopfzeile mit Tabs, Wellen-Banner, Kartenraster, Sidebar mit
// Suche und Filter).
//
// Nur der Admin hat Zugriff. Von hier aus wird ein Benutzer bearbeitet,
// gesperrt/entsperrt oder bekommt einen neuen Freischaltcode. Beides
// Letztere aendert Daten und laeuft deshalb per POST mit Sicherheitsabfrage,
// nie ueber einen GET-Link. Ein neu erzeugter Code wird danach genau einmal
// angezeigt (benutzer_code_anzeigen_falls_vorhanden()).
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../benutzer_logik.php';

erfordere_admin();
csrf_pruefen();

$meineId  = benutzer_id();
$istAdmin = ist_admin();

// --- Aktionen per POST ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aktion  = isset($_POST['aktion']) ? $_POST['aktion'] : '';
    $zielId  = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $ziel    = abfrage('SELECT id, name, rolle, aktiv FROM benutzer WHERE id = ?', array($zielId))->fetch();
    $meldung = '';
    $fehler  = array();

    if (!$ziel) {
        $fehler[] = 'Diesen Benutzer gibt es nicht.';
    } elseif ($aktion === 'code') {
        $code = benutzer_code_erzeugen($zielId);
        if ($code === false) {
            $fehler[] = 'Diesen Benutzer gibt es nicht.';
        } else {
            benutzer_code_merken($zielId, $code);
            header('Location: benutzer.php');
            exit;
        }
    } elseif ($aktion === 'sperren' || $aktion === 'entsperren') {
        $neuAktiv = $aktion === 'entsperren';
        $db = db();
        $db->beginTransaction();

        try {
            benutzer_admins_sperren();
            $fehler = benutzer_darf_aendern($zielId, $meineId, $ziel['rolle'], $neuAktiv);

            if (empty($fehler)) {
                abfrage('UPDATE benutzer SET aktiv = ? WHERE id = ?', array($neuAktiv ? 1 : 0, $zielId));
                $db->commit();
                $meldung = $neuAktiv
                    ? $ziel['name'] . ' wurde entsperrt.'
                    : $ziel['name'] . ' wurde gesperrt und ist ab dem nächsten Klick abgemeldet.';
            } else {
                $db->rollBack();
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $fehler[] = 'Die Änderung konnte nicht gespeichert werden. Bitte erneut versuchen.';
        }
    } else {
        $fehler[] = 'Unbekannte Aktion.';
    }

    // Post/Redirect/Get: Meldung merken und die Liste neu laden, damit ein
    // Neuladen der Seite die Aktion nicht wiederholt.
    $_SESSION['benutzer_meldung'] = $meldung;
    $_SESSION['benutzer_fehler']  = $fehler;
    header('Location: benutzer.php');
    exit;
}

// Wurde gerade ein Code erzeugt (hier oder beim Anlegen in
// benutzer_bearbeiten.php), zeigt diese Funktion ihn einmalig an und beendet
// die Seite.
benutzer_code_anzeigen_falls_vorhanden();

$meldung = isset($_SESSION['benutzer_meldung']) ? $_SESSION['benutzer_meldung'] : '';
$fehler  = isset($_SESSION['benutzer_fehler']) ? $_SESSION['benutzer_fehler'] : array();
unset($_SESSION['benutzer_meldung'], $_SESSION['benutzer_fehler']);

// --- Filter aus der URL ---------------------------------------------------
// Alle drei Filter sind frei kombinierbar und landen ausschliesslich als
// Parameter im Prepared Statement.
$suche        = isset($_GET['suche']) ? trim($_GET['suche']) : '';
$filterRolle  = isset($_GET['rolle']) ? $_GET['rolle'] : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';

if (!in_array($filterRolle, array('admin', 'mitarbeiter'), true)) {
    $filterRolle = '';
}
if (!in_array($filterStatus, array('aktiv', 'gesperrt', 'wartet'), true)) {
    $filterStatus = '';
}

$bedingungen = array();
$parameter   = array();

$sql = 'SELECT id, name, email, rolle, aktiv, passwort_hash, freischaltcode, code_gueltig_bis
        FROM benutzer';

if ($suche !== '') {
    $bedingungen[] = '(name LIKE ? OR email LIKE ?)';
    $parameter[]   = '%' . $suche . '%';
    $parameter[]   = '%' . $suche . '%';
}

if ($filterRolle !== '') {
    $bedingungen[] = 'rolle = ?';
    $parameter[]   = $filterRolle;
}

// Gleiche Einteilung wie benutzer_status(): gesperrt geht vor "wartet".
if ($filterStatus === 'gesperrt') {
    $bedingungen[] = 'aktiv = 0';
} elseif ($filterStatus === 'wartet') {
    $bedingungen[] = 'aktiv = 1 AND passwort_hash IS NULL';
} elseif ($filterStatus === 'aktiv') {
    $bedingungen[] = 'aktiv = 1 AND passwort_hash IS NOT NULL';
}

if (!empty($bedingungen)) {
    $sql .= ' WHERE ' . implode(' AND ', $bedingungen);
}

$sql .= ' ORDER BY name';

$benutzerListe = abfrage($sql, $parameter)->fetchAll();

// Kennzahlen je Benutzer - je eine Abfrage fuer alle statt einer pro Karte.
$kurseJeBenutzer     = array();
$raeumeJeBenutzer    = array();
$buchungenJeBenutzer = array();

// Eigentuemer = Ersteller ODER Eintrag in kurs_eigentuemer. UNION entfernt
// doppelte Paare, wenn jemand beides ist.
$zeilen = abfrage(
    'SELECT benutzer_id, COUNT(*) AS anzahl
     FROM (
         SELECT ersteller_id AS benutzer_id, id AS kurs_id FROM kurs
         UNION
         SELECT benutzer_id, kurs_id FROM kurs_eigentuemer
     ) AS eigentum
     GROUP BY benutzer_id'
)->fetchAll();
foreach ($zeilen as $zeile) {
    $kurseJeBenutzer[$zeile['benutzer_id']] = (int) $zeile['anzahl'];
}

$zeilen = abfrage(
    'SELECT benutzer_id, COUNT(*) AS anzahl FROM raum_bearbeiter GROUP BY benutzer_id'
)->fetchAll();
foreach ($zeilen as $zeile) {
    $raeumeJeBenutzer[$zeile['benutzer_id']] = (int) $zeile['anzahl'];
}

$zeilen = abfrage(
    'SELECT benutzer_id, COUNT(*) AS anzahl FROM buchung WHERE start >= ? GROUP BY benutzer_id',
    array(date('Y-m-d H:i:s'))
)->fetchAll();
foreach ($zeilen as $zeile) {
    $buchungenJeBenutzer[$zeile['benutzer_id']] = (int) $zeile['anzahl'];
}

$statusTexte = array(
    'aktiv'    => 'aktiv',
    'gesperrt' => 'gesperrt',
    'wartet'   => 'wartet auf Freischaltung',
);

/**
 * Anzahl mit passender Einzahl/Mehrzahl, z. B. "1 Kurs" / "3 Kurse".
 */
function anzahl_text($anzahl, $einzahl, $mehrzahl)
{
    return (int) $anzahl . ' ' . ((int) $anzahl === 1 ? $einzahl : $mehrzahl);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Benutzer – FitFürInfo</title>
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

  /* ---- Dekorativer Wellen-Header, identisch zu raeume.php ---- */
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

  .meldung,
  .fehler {
    margin: 0 0 20px;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
  }

  .meldung {
    background: #e6f4ea;
    border: 1px solid #a8d5b5;
    color: #205c33;
  }

  .fehler {
    background: #fdeceb;
    border: 1px solid #f0b3ae;
    color: #a13a2f;
  }

  .fehler ul { margin: 0; padding-left: 18px; }
  .fehler li + li { margin-top: 4px; }

  .benutzer-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
  }

  .benutzer-card {
    background: var(--card-bg);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 12px 28px rgba(20, 40, 50, 0.1);
    display: flex;
    flex-direction: column;
  }

  .benutzer-body {
    padding: 16px 18px 18px;
    flex: 1;
    display: flex;
    flex-direction: column;
  }

  .benutzer-titel {
    margin: 0 0 4px;
    font-size: 19px;
    font-weight: 700;
    color: var(--text-dark);
    word-break: break-word;
  }

  .benutzer-email {
    margin: 0 0 10px;
    font-size: 12px;
    color: var(--text-muted);
    word-break: break-all;
  }

  .chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: 0 0 10px;
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

  .chip.rolle-admin {
    color: var(--blue);
    background: rgba(47, 111, 176, 0.1);
    border-color: rgba(47, 111, 176, 0.25);
  }

  .chip.status-gesperrt {
    color: #a13a2f;
    background: #fdeceb;
    border-color: #f0b3ae;
  }

  .chip.status-wartet {
    color: #8a4b16;
    background: #fff4e8;
    border-color: #f3c79e;
  }

  .benutzer-zahlen {
    margin: 0;
    padding: 0;
    list-style: none;
    font-size: 12px;
    color: var(--text-muted);
    line-height: 1.6;
    flex: 1;
  }

  .code-hinweis {
    margin: 8px 0 0;
    font-size: 12px;
    color: #8a4b16;
  }

  .benutzer-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 16px;
  }

  .benutzer-actions form {
    flex: 1;
    display: flex;
    margin: 0;
  }

  .btn-edit,
  .btn-code,
  .btn-sperren,
  .btn-entsperren {
    flex: 1;
    border-radius: 8px;
    padding: 9px 6px;
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    display: inline-block;
    color: #ffffff;
    border: none;
    white-space: nowrap;
  }

  .btn-edit { background: linear-gradient(90deg, var(--blue) 0%, var(--teal) 100%); }
  .btn-entsperren { background: linear-gradient(90deg, var(--teal) 0%, var(--blue) 100%); }
  .btn-sperren { background: linear-gradient(90deg, var(--orange) 0%, #d9534f 100%); }

  .btn-code {
    color: var(--blue);
    background: #f2f7fb;
    border: 1px solid #d7e6f2;
  }

  .btn-edit:hover,
  .btn-entsperren:hover,
  .btn-sperren:hover { filter: brightness(1.05); }
  .btn-code:hover { background: #e6f0f9; }

  .empty-state {
    grid-column: 1 / -1;
    text-align: center;
    color: var(--text-muted);
    font-size: 14px;
    padding: 40px 20px;
  }

  /* ---- Sidebar im Card-Look der Login-Seite ---- */
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

  .search-input::placeholder { color: #a7b2b6; }

  .search-input:focus {
    border-color: var(--blue);
    background: #ffffff;
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
    margin-bottom: 26px;
  }

  .sidebar select:focus {
    border-color: var(--blue);
    background: #ffffff;
  }

  .sidebar-hinweis {
    margin: 0;
    font-size: 12px;
    color: var(--text-muted);
    line-height: 1.5;
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

  @media (min-width: 1400px) {
    .benutzer-grid { grid-template-columns: repeat(4, 1fr); }
  }

  @media (max-width: 700px) {
    .main-area { padding-right: 16px; }
    .benutzer-grid { grid-template-columns: 1fr; }
    .sidebar {
      position: static;
      width: 100%;
      order: -1;
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
      <a href="belegung.php" class="tab">Belegung</a>
      <?php if ($istAdmin): ?>
      <a href="benutzer.php" class="tab active">Benutzer</a>
      <?php endif; ?>
    </nav>

    <div class="header-actions">
      <a href="#" class="btn-header profile">Profile</a>
      <a href="logout.php" class="btn-header logout">Log Out</a>
    </div>
  </header>

  <div class="banner-spacer"></div>

  <!-- Dekorativer Wellen-Streifen, identisch zu raeume.php -->
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

    <?php session_fehler_anzeigen(); ?>

<?php if ($meldung !== ''): ?>
    <div class="meldung"><?php echo h($meldung); ?></div>
<?php endif; ?>

<?php if (!empty($fehler)): ?>
    <div class="fehler">
      <ul>
        <?php foreach ($fehler as $einzelnerFehler): ?>
        <li><?php echo h($einzelnerFehler); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
<?php endif; ?>

    <section class="benutzer-grid">

<?php if (empty($benutzerListe)): ?>
      <p class="empty-state">Keine Benutzer gefunden. Andere Suche oder anderen Filter probieren.</p>
<?php else: ?>
  <?php foreach ($benutzerListe as $benutzer): ?>
    <?php
      $id       = (int) $benutzer['id'];
      $status   = benutzer_status($benutzer);
      $anzKurse = isset($kurseJeBenutzer[$id]) ? $kurseJeBenutzer[$id] : 0;
      $anzRaeume = isset($raeumeJeBenutzer[$id]) ? $raeumeJeBenutzer[$id] : 0;
      $anzBuchungen = isset($buchungenJeBenutzer[$id]) ? $buchungenJeBenutzer[$id] : 0;
    ?>
      <article class="benutzer-card">
        <div class="benutzer-body">
          <h3 class="benutzer-titel"><?php echo h($benutzer['name']); ?></h3>
          <p class="benutzer-email">
            <?php echo $benutzer['email'] !== null && $benutzer['email'] !== '' ? h($benutzer['email']) : 'keine E-Mail hinterlegt'; ?>
          </p>

          <div class="chips">
            <span class="chip rolle-<?php echo h($benutzer['rolle']); ?>">
              <?php echo $benutzer['rolle'] === 'admin' ? 'Admin' : 'Mitarbeiter'; ?>
            </span>
            <span class="chip status-<?php echo h($status); ?>"><?php echo h($statusTexte[$status]); ?></span>
          </div>

          <ul class="benutzer-zahlen">
            <li><?php echo h(anzahl_text($anzKurse, 'Kurs', 'Kurse')); ?> als Eigentümer</li>
            <li><?php echo h(anzahl_text($anzRaeume, 'Raum', 'Räume')); ?> als Bearbeiter</li>
            <li><?php echo h(anzahl_text($anzBuchungen, 'kommende Buchung', 'kommende Buchungen')); ?></li>
          </ul>

          <?php /* Nur ob und bis wann ein Code offen ist - der Code selbst
                   wird nach der einmaligen Anzeige nie wieder ausgegeben. */ ?>
          <?php if ($benutzer['freischaltcode'] !== null && $benutzer['code_gueltig_bis'] !== null): ?>
            <?php if (strtotime($benutzer['code_gueltig_bis']) >= time()): ?>
          <p class="code-hinweis">Freischaltcode offen bis <?php echo h(date('d.m.Y, H:i', strtotime($benutzer['code_gueltig_bis']))); ?> Uhr</p>
            <?php else: ?>
          <p class="code-hinweis">Freischaltcode abgelaufen – bei Bedarf neuen erzeugen.</p>
            <?php endif; ?>
          <?php endif; ?>

          <div class="benutzer-actions">
            <a href="benutzer_bearbeiten.php?id=<?php echo $id; ?>" class="btn-edit">bearbeiten</a>

            <?php if ($status === 'gesperrt'): ?>
            <form method="post" action="benutzer.php"
                  data-frage="<?php echo h($benutzer['name'] . ' wieder entsperren?'); ?>"
                  onsubmit="return confirm(this.getAttribute('data-frage'));">
              <?php csrf_feld(); ?>
              <input type="hidden" name="aktion" value="entsperren">
              <input type="hidden" name="id" value="<?php echo $id; ?>">
              <button type="submit" class="btn-entsperren">entsperren</button>
            </form>
            <?php elseif ($id !== (int) $meineId): ?>
            <?php /* Sich selbst sperren geht nicht (benutzer_darf_aendern()) -
                     deshalb gibt es den Knopf auf der eigenen Karte gar nicht. */ ?>
            <form method="post" action="benutzer.php"
                  data-frage="<?php echo h($benutzer['name'] . ' wirklich sperren? Eine laufende Sitzung endet beim nächsten Klick.'); ?>"
                  onsubmit="return confirm(this.getAttribute('data-frage'));">
              <?php csrf_feld(); ?>
              <input type="hidden" name="aktion" value="sperren">
              <input type="hidden" name="id" value="<?php echo $id; ?>">
              <button type="submit" class="btn-sperren">sperren</button>
            </form>
            <?php endif; ?>
          </div>

          <div class="benutzer-actions">
            <form method="post" action="benutzer.php"
                  data-frage="<?php echo h('Neuen Freischaltcode für ' . $benutzer['name'] . ' erzeugen? Ein noch offener Code wird damit ungültig; ein bestehendes Passwort gilt weiter, bis der neue Code eingelöst wird.'); ?>"
                  onsubmit="return confirm(this.getAttribute('data-frage'));">
              <?php csrf_feld(); ?>
              <input type="hidden" name="aktion" value="code">
              <input type="hidden" name="id" value="<?php echo $id; ?>">
              <button type="submit" class="btn-code">Neuer Freischaltcode</button>
            </form>
          </div>
        </div>
      </article>
  <?php endforeach; ?>
<?php endif; ?>

    </section>

    <aside class="sidebar">
      <form method="get" action="benutzer.php">
        <p class="sidebar-label">Suche</p>
        <input type="text" name="suche" class="search-input" placeholder="Name oder E-Mail....." value="<?php echo h($suche); ?>">

        <p class="sidebar-label">Rolle</p>
        <select name="rolle" onchange="this.form.submit()">
          <option value="">Alle</option>
          <option value="admin" <?php echo $filterRolle === 'admin' ? 'selected' : ''; ?>>Admin</option>
          <option value="mitarbeiter" <?php echo $filterRolle === 'mitarbeiter' ? 'selected' : ''; ?>>Mitarbeiter</option>
        </select>

        <p class="sidebar-label">Status</p>
        <select name="status" onchange="this.form.submit()">
          <option value="">Alle</option>
          <?php foreach ($statusTexte as $wert => $text): ?>
          <option value="<?php echo h($wert); ?>" <?php echo $filterStatus === $wert ? 'selected' : ''; ?>><?php echo h($text); ?></option>
          <?php endforeach; ?>
        </select>

        <p class="sidebar-hinweis">
          Benutzer werden nicht gelöscht, sondern gesperrt – an ihnen hängen Kurse und Buchungen.
        </p>

        <button type="submit" class="filter-submit">Suchen</button>
      </form>

      <a href="benutzer_bearbeiten.php" class="btn-add" aria-label="Benutzer hinzufügen">+</a>
    </aside>

  </div>

  <script>
    // Welle beim Scrollen sanft nach oben schieben und langsam ausblenden
    // (gleiches Verhalten wie in raeume.php).
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
