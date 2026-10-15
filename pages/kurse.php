<?php
// kurse.php
// Reine Darstellungsseite ohne Funktionalität, im gleichen visuellen Stil wie login.php
// (Wellen-Header, Farbpalette Türkis/Blau/Orange, weiße Cards mit Schatten).

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../layout.php';
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

  .link-belegung {
    display: inline-block;
    margin-top: 12px;
    font-size: 12px;
    color: var(--blue);
    text-decoration: none;
  }
  .link-belegung:hover { text-decoration: underline; }

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


  <?php kopfzeile('kurse'); ?>

  <div class="main-area">

    <?php session_fehler_anzeigen(); ?>

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
          <a class="link-belegung" href="belegung.php?ansicht=monat&amp;kurs_id=<?php echo (int) $kurs['id']; ?>">Termine</a>

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

</body>
</html>
