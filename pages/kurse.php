<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// kurse - Kursliste mit Suche und Filter "Nur eigene / Alle".
// Aufruf: index.php?seite=kurse

erfordere_login();

$meineId  = benutzer_id();
$istAdmin = ist_admin();

$suche     = eingabe_text($_GET, 'suche');
$nurEigene = isset($_GET['ownership']) && $_GET['ownership'] === 'own';

$kurse = kurs_liste($suche, $nurEigene, $meineId);

// Eigentuemer und Software je Kurs nachladen (fuer Berechtigungen und Chips).
$kursIds           = array_column($kurse, 'id');
$eigentuemerJeKurs = kurs_eigentuemer_je_kurs($kursIds);
$softwareJeKurs    = kurs_software_je_kurs($kursIds);

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

          <?php if ($istAdmin || kurs_ist_eigene_karte($kurs, $eigentuemerJeKurs, $meineId)): ?>
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

      <?php echo knopf_hinzufuegen('kurs_bearbeiten', 'Kurs hinzufügen'); ?>
    </aside>

  </div>
