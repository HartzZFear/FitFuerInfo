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

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/funktionen.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../templates/layout.php';
require_once __DIR__ . '/../lib/raum_rechte.php';

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
<link rel="stylesheet" href="<?php echo h(asset_url('css/style.css')); ?>">
<link rel="stylesheet" href="<?php echo h(asset_url('css/raeume.css')); ?>">
</head>
<body class="seite-raeume">

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
        <select name="software_id" data-auto-submit>
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
               value="<?php echo h($minPlaetze); ?>" data-auto-submit>

        <p class="sidebar-label">Zuständigkeit</p>
        <div class="radio-group">
          <label class="radio-option">
            <input type="radio" name="zustaendig" value="meine" data-auto-submit <?php echo $nurEigene ? 'checked' : ''; ?>>
            Nur meine
          </label>
          <label class="radio-option">
            <input type="radio" name="zustaendig" value="alle" data-auto-submit <?php echo $nurEigene ? '' : 'checked'; ?>>
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
