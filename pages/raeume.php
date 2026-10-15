<?php
// raeume.php
// Raumliste: welcher Raum hat wie viele Arbeitsplaetze und welche Software.
// Gleicher visueller Stil wie kurse.php (Kopfzeile mit Tabs, Wellen-Banner,
// Kartenraster, Sidebar mit Suche und Filter).
//
// Raeume anlegen und loeschen darf nur der Admin; bearbeiten duerfen sie
// zusaetzlich die in raum_bearbeiter eingetragenen Mitarbeiter. Die Buttons
// werden entsprechend ein- oder ausgeblendet - die massgebliche Pruefung
// steht serverseitig in raum_rechte.php und laeuft in raum_bearbeiten.php
// bzw. raum_loeschen.php noch einmal.
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../layout.php';
require_once __DIR__ . '/../raum_rechte.php';

erfordere_login();

$meineId  = benutzer_id();
$istAdmin = ist_admin();

// --- Filter aus der URL ---------------------------------------------------
// Alle vier Filter sind frei kombinierbar und stehen in einem gemeinsamen
// GET-Formular; sie landen ausschliesslich als Parameter im Prepared
// Statement, nie per Stringverkettung im SQL.
$suche     = isset($_GET['suche']) ? trim($_GET['suche']) : '';
$nurEigene = isset($_GET['zustaendig']) && $_GET['zustaendig'] === 'meine';

$filterSoftwareId = isset($_GET['software_id']) ? (int) $_GET['software_id'] : 0;

// Mindestanzahl: nur ganze Zahlen ab 1. Alles andere (leer, Text, 0, negativ)
// bedeutet "kein Filter" - das Feld bleibt dann leer.
$minPlaetze = isset($_GET['min_plaetze']) ? trim($_GET['min_plaetze']) : '';
if ($minPlaetze !== '' && (!ctype_digit($minPlaetze) || (int) $minPlaetze < 1)) {
    $minPlaetze = '';
}

$bedingungen = array();
$parameter   = array();

$sql = 'SELECT DISTINCT r.id, r.name, r.arbeitsplaetze
        FROM raum r
        LEFT JOIN raum_bearbeiter rb ON rb.raum_id = r.id';

if ($suche !== '') {
    $bedingungen[] = 'r.name LIKE ?';
    $parameter[]   = '%' . $suche . '%';
}

if ($nurEigene) {
    $bedingungen[] = 'rb.benutzer_id = ?';
    $parameter[]   = $meineId;
}

// Software ueber EXISTS statt ueber einen weiteren JOIN: so bleibt die
// Ergebnismenge eindeutig, auch wenn ein Raum viele Pakete hat.
if ($filterSoftwareId > 0) {
    $bedingungen[] = 'EXISTS (
            SELECT 1 FROM raum_software rs
            WHERE rs.raum_id = r.id AND rs.software_id = ?
        )';
    $parameter[]   = $filterSoftwareId;
}

if ($minPlaetze !== '') {
    $bedingungen[] = 'r.arbeitsplaetze >= ?';
    $parameter[]   = (int) $minPlaetze;
}

if (!empty($bedingungen)) {
    $sql .= ' WHERE ' . implode(' AND ', $bedingungen);
}

$sql .= ' ORDER BY r.name';

$raeume = abfrage($sql, $parameter)->fetchAll();

// Fuer das Software-Dropdown in der Sidebar.
$alleSoftware = abfrage('SELECT id, name FROM software ORDER BY name')->fetchAll();

// Software, Bearbeiter und kuenftige Buchungen je Raum nachladen - in je
// einer Abfrage fuer alle Raeume statt einer pro Karte.
$softwareJeRaum    = array();
$bearbeiterJeRaum  = array();
$bearbeiterNamen   = array();
$buchungenJeRaum   = array();

$raumIds = array();
foreach ($raeume as $r) {
    $raumIds[] = $r['id'];
}

if (!empty($raumIds)) {
    $platzhalter = implode(',', array_fill(0, count($raumIds), '?'));

    $zeilen = abfrage(
        'SELECT rs.raum_id, s.name
         FROM raum_software rs
         JOIN software s ON s.id = rs.software_id
         WHERE rs.raum_id IN (' . $platzhalter . ')
         ORDER BY s.name',
        $raumIds
    )->fetchAll();
    foreach ($zeilen as $zeile) {
        $softwareJeRaum[$zeile['raum_id']][] = $zeile['name'];
    }

    $zeilen = abfrage(
        'SELECT rb.raum_id, rb.benutzer_id, b.name
         FROM raum_bearbeiter rb
         JOIN benutzer b ON b.id = rb.benutzer_id
         WHERE rb.raum_id IN (' . $platzhalter . ')
         ORDER BY b.name',
        $raumIds
    )->fetchAll();
    foreach ($zeilen as $zeile) {
        $bearbeiterJeRaum[$zeile['raum_id']][] = (int) $zeile['benutzer_id'];
        $bearbeiterNamen[$zeile['raum_id']][]  = $zeile['name'];
    }

    $zeilen = abfrage(
        'SELECT raum_id, COUNT(*) AS anzahl
         FROM buchung
         WHERE start >= ? AND raum_id IN (' . $platzhalter . ')
         GROUP BY raum_id',
        array_merge(array(date('Y-m-d H:i:s')), $raumIds)
    )->fetchAll();
    foreach ($zeilen as $zeile) {
        $buchungenJeRaum[$zeile['raum_id']] = (int) $zeile['anzahl'];
    }
}

/**
 * Nur fuer die Anzeige der Buttons: ist der eingeloggte Benutzer fuer diesen
 * Raum eingetragen? Die Daten liegen durch die Abfrage oben schon vor,
 * deshalb hier ohne eigene Abfrage. Die massgebliche Pruefung steht in
 * raum_darf_bearbeiten() (raum_rechte.php).
 */
function ist_mein_raum($raum, $bearbeiterJeRaum, $benutzerId, $istAdmin)
{
    if ($istAdmin) {
        return true;
    }
    return isset($bearbeiterJeRaum[$raum['id']])
        && in_array((int) $benutzerId, $bearbeiterJeRaum[$raum['id']], true);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Räume – FitFürInfo</title>
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
  }

  /* ---- Hauptbereich ---- */
  .main-area {
    max-width: 1600px;
    width: 92%;
    margin: 20px auto 40px;
    padding: 0 16px 16px;
    padding-right: 292px; /* Platz für die fest positionierte Sidebar */
  }

  .raum-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
  }

  .raum-card {
    background: var(--card-bg);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 12px 28px rgba(20, 40, 50, 0.1);
    display: flex;
    flex-direction: column;
  }

  .raum-body {
    padding: 16px 18px 18px;
    flex: 1;
    display: flex;
    flex-direction: column;
  }

  .raum-titel {
    margin: 0 0 8px;
    font-size: 19px;
    font-weight: 700;
    color: var(--text-dark);
  }

  .raum-meta {
    margin: 0 0 8px;
    font-size: 12px;
    color: var(--text-muted);
  }

  .raum-bearbeiter {
    margin: 0;
    font-size: 12px;
    color: var(--text-muted);
    line-height: 1.4;
    flex: 1;
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

  .link-belegung {
    display: inline-block;
    margin-top: 12px;
    font-size: 12px;
    color: var(--blue);
    text-decoration: none;
  }
  .link-belegung:hover { text-decoration: underline; }

  .raum-actions {
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
    text-decoration: none;
    display: inline-block;
    color: #ffffff;
    border: none;
  }

  .btn-edit { background: linear-gradient(90deg, var(--blue) 0%, var(--teal) 100%); }
  .btn-edit:hover { filter: brightness(1.05); }

  .btn-delete { background: linear-gradient(90deg, var(--orange) 0%, #d9534f 100%); }
  .btn-delete:hover { filter: brightness(1.05); }

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

  /* Filterfelder in derselben Optik wie das Suchfeld ---- */
  .sidebar select,
  .sidebar input[type="number"] {
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

  .sidebar select:focus,
  .sidebar input[type="number"]:focus {
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
    .raum-grid { grid-template-columns: repeat(4, 1fr); }
  }

  @media (max-width: 700px) {
    .main-area { padding-right: 16px; }
    .raum-grid { grid-template-columns: 1fr; }
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

  <?php kopfzeile('raeume'); ?>

  <div class="main-area">

    <?php session_fehler_anzeigen(); ?>

    <section class="raum-grid">

<?php if (empty($raeume)): ?>
      <p class="empty-state">Keine Räume gefunden. Andere Suche oder anderen Filter probieren.</p>
<?php else: ?>
  <?php foreach ($raeume as $raum): ?>
      <article class="raum-card">
        <div class="raum-body">
          <h3 class="raum-titel"><?php echo h($raum['name']); ?></h3>
          <p class="raum-meta"><?php echo (int) $raum['arbeitsplaetze']; ?> Arbeitsplätze</p>

          <?php if (!empty($bearbeiterNamen[$raum['id']])): ?>
          <p class="raum-bearbeiter">
            Bearbeiter: <?php echo h(implode(', ', $bearbeiterNamen[$raum['id']])); ?>
          </p>
          <?php else: ?>
          <p class="raum-bearbeiter">Kein Bearbeiter eingetragen.</p>
          <?php endif; ?>

          <?php if (!empty($softwareJeRaum[$raum['id']])): ?>
          <div class="software-chips">
            <?php foreach ($softwareJeRaum[$raum['id']] as $softwareName): ?>
            <span class="chip"><?php echo h($softwareName); ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <a class="link-belegung" href="belegung.php?ansicht=woche&amp;raum_id=<?php echo (int) $raum['id']; ?>">
            <?php
              $anzahl = isset($buchungenJeRaum[$raum['id']]) ? $buchungenJeRaum[$raum['id']] : 0;
              echo $anzahl === 1 ? '1 kommende Buchung' : (int) $anzahl . ' kommende Buchungen';
            ?>
            anzeigen
          </a>

          <?php if (ist_mein_raum($raum, $bearbeiterJeRaum, $meineId, $istAdmin)): ?>
          <div class="raum-actions">
            <a href="raum_bearbeiten.php?id=<?php echo (int) $raum['id']; ?>" class="btn-edit">bearbeiten</a>
            <?php if (raum_darf_loeschen($istAdmin)): ?>
            <a href="raum_loeschen.php?id=<?php echo (int) $raum['id']; ?>" class="btn-delete">löschen</a>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </article>
  <?php endforeach; ?>
<?php endif; ?>

    </section>

    <aside class="sidebar">
      <form method="get" action="raeume.php">
        <p class="sidebar-label">Suche</p>
        <input type="text" name="suche" class="search-input" placeholder="Raumname....." value="<?php echo h($suche); ?>">

        <p class="sidebar-label">Software</p>
        <select name="software_id" onchange="this.form.submit()">
          <option value="0">Alle</option>
          <?php foreach ($alleSoftware as $softwareOption): ?>
          <option value="<?php echo (int) $softwareOption['id']; ?>"
            <?php echo ((int) $softwareOption['id'] === $filterSoftwareId) ? 'selected' : ''; ?>>
            <?php echo h($softwareOption['name']); ?>
          </option>
          <?php endforeach; ?>
        </select>

        <p class="sidebar-label">Mindestens Arbeitsplätze</p>
        <input type="number" name="min_plaetze" min="1" placeholder="beliebig"
               value="<?php echo h($minPlaetze); ?>" onchange="this.form.submit()">

        <p class="sidebar-label">Zuständigkeit</p>
        <div class="radio-group">
          <label class="radio-option">
            <input type="radio" name="zustaendig" value="meine" onchange="this.form.submit()" <?php echo $nurEigene ? 'checked' : ''; ?>>
            Nur meine
          </label>
          <label class="radio-option">
            <input type="radio" name="zustaendig" value="alle" onchange="this.form.submit()" <?php echo $nurEigene ? '' : 'checked'; ?>>
            Alle
          </label>
        </div>

        <button type="submit" class="filter-submit">Suchen</button>
      </form>

      <?php if (raum_darf_anlegen($istAdmin)): ?>
      <a href="raum_bearbeiten.php" class="btn-add" aria-label="Raum hinzufügen">+</a>
      <?php endif; ?>
    </aside>

  </div>

</body>
</html>
