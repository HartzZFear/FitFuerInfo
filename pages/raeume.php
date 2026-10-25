<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// raeume (Aufruf: index.php?seite=raeume)
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

erfordere_login();

$meineId  = benutzer_id();
$istAdmin = ist_admin();

// --- Filter aus der URL ---------------------------------------------------
// Alle vier Filter sind frei kombinierbar und stehen in einem gemeinsamen
// GET-Formular. Das SQL dazu steht in raum_liste() (lib/raum_logik.php).
$suche     = eingabe_text($_GET, 'suche');
$nurEigene = isset($_GET['zustaendig']) && $_GET['zustaendig'] === 'meine';

$filterSoftwareId = isset($_GET['software_id']) ? (int) $_GET['software_id'] : 0;

// Mindestanzahl: dieselbe Regel wie fuer Arbeitsplaetze (ganze Zahl ab 1).
// Alles andere (leer, Text, 0, negativ) bedeutet "kein Filter" - das Feld
// bleibt dann leer.
$minPlaetze = eingabe_text($_GET, 'min_plaetze');
if (raum_arbeitsplaetze($minPlaetze) === null) {
    $minPlaetze = '';
}

$raeume = raum_liste($suche, $nurEigene, $filterSoftwareId, $minPlaetze, $meineId);

// Fuer das Software-Dropdown in der Sidebar.
$alleSoftware = software_alle();

// Software, Bearbeiter und kuenftige Buchungen je Raum nachladen - in je
// einer Abfrage fuer alle Raeume statt einer pro Karte.
$raumIds          = array_column($raeume, 'id');
$softwareJeRaum   = raum_software_je_raum($raumIds);
$bearbeiter       = raum_bearbeiter_je_raum($raumIds);
$bearbeiterJeRaum = $bearbeiter['ids'];
$bearbeiterNamen  = $bearbeiter['namen'];
$buchungenJeRaum  = raum_kommende_buchungen_je_raum($raumIds);

$seitentitel = 'Räume';
$aktiverTab  = 'raeume';
$seitenCss   = array('raeume.css');
?>
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

          <a class="link-belegung" href="<?php echo h(url('belegung', array('ansicht' => 'woche', 'raum_id' => (int) $raum['id']))); ?>">
            <?php
              $anzahl = isset($buchungenJeRaum[$raum['id']]) ? $buchungenJeRaum[$raum['id']] : 0;
              echo $anzahl === 1 ? '1 kommende Buchung' : (int) $anzahl . ' kommende Buchungen';
            ?>
            anzeigen
          </a>

          <?php if (raum_ist_meiner($raum, $bearbeiterJeRaum, $meineId, $istAdmin)): ?>
          <div class="raum-actions">
            <a href="<?php echo h(url('raum_bearbeiten', array('id' => (int) $raum['id']))); ?>" class="btn-edit">bearbeiten</a>
            <?php if (raum_darf_loeschen($istAdmin)): ?>
            <a href="<?php echo h(url('raum_loeschen', array('id' => (int) $raum['id']))); ?>" class="btn-delete">löschen</a>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </article>
  <?php endforeach; ?>
<?php endif; ?>

    </section>

    <aside class="sidebar">
      <form method="get" action="<?php echo h(url('raeume')); ?>">
        <?php echo seite_feld('raeume'); ?>
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
      <?php echo knopf_hinzufuegen('raum_bearbeiten', 'Raum hinzufügen'); ?>
      <?php endif; ?>
    </aside>

  </div>
