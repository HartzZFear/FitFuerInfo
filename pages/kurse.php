<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// kurse - Kursliste mit Suche und Filter "Nur eigene / Alle".
// Aufruf: index.php?seite=kurse

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
 * fuer Bearbeiten/Loeschen selbst steht in kurs_darf_verwalten() (lib/kurs_rechte.php).
 */
function ist_eigene_karte($kurs, $eigentuemerJeKurs, $benutzerId)
{
    if ((int) $kurs['ersteller_id'] === (int) $benutzerId) {
        return true;
    }
    return isset($eigentuemerJeKurs[$kurs['id']])
        && in_array((int) $benutzerId, $eigentuemerJeKurs[$kurs['id']], true);
}

$seitentitel = 'Kurse';
$aktiverTab  = 'kurse';
$seitenCss   = array('kurse.css');
?>
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
          <a class="link-belegung" href="<?php echo h(url('belegung', array('ansicht' => 'monat', 'kurs_id' => (int) $kurs['id']))); ?>">Termine</a>

          <?php if ($istAdmin || ist_eigene_karte($kurs, $eigentuemerJeKurs, $meineId)): ?>
          <div class="course-actions">
            <a href="<?php echo h(url('kurs_bearbeiten', array('id' => (int) $kurs['id']))); ?>" class="btn-edit">bearbeiten</a>
            <a href="<?php echo h(url('kurs_loeschen', array('id' => (int) $kurs['id']))); ?>" class="btn-delete">löschen</a>
          </div>
          <?php endif; ?>
        </div>
      </article>
  <?php endforeach; ?>
<?php endif; ?>

    </section>

    <aside class="sidebar">
      <form method="get" action="<?php echo h(url('kurse')); ?>">
        <?php echo seite_feld('kurse'); ?>
        <p class="sidebar-label">Suche</p>
        <input type="text" name="suche" class="search-input" placeholder="Kursname....." value="<?php echo h($suche); ?>">

        <p class="sidebar-label">Eigentümerschaft</p>
        <div class="radio-group">
          <label class="radio-option">
            <input type="radio" name="ownership" value="own" data-auto-submit <?php echo $nurEigene ? 'checked' : ''; ?>>
            Nur eigene
          </label>
          <label class="radio-option">
            <input type="radio" name="ownership" value="all" data-auto-submit <?php echo $nurEigene ? '' : 'checked'; ?>>
            Alle
          </label>
        </div>

        <button type="submit" class="filter-submit">Suchen</button>
      </form>

      <a href="<?php echo h(url('kurs_bearbeiten')); ?>" class="btn-add" aria-label="Kurs hinzufügen">+</a>
    </aside>

  </div>
