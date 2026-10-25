<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// belegung (Aufruf: index.php?seite=belegung)
// Belegungskalender in drei Ansichten: Monat, Woche (pro Raum), Tag (alle
// Raeume). Gleicher visueller Stil wie kurse.php und buchungen.php
// (Kopfzeile mit Tabs, Wellen-Banner, Sidebar mit Filtern).
//
// Die Logik (Parameter, Zeitraum, Verteilung auf Slots, Blaettern) steht in
// kalender_logik.php - diese Seite gibt nur aus.
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

erfordere_login();

$meineId  = benutzer_id();
$istAdmin = ist_admin();

// Fuer die Filter-Dropdowns und die Pruefung der IDs aus der URL.
$alleRaeume = raum_alle();
$alleKurse  = kurs_alle();

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

$legendeKurse = kalender_legende($buchungen, $param, $meineId);

// Blaettern und Umschalten - die Filter bleiben ueber kalender_url() erhalten.
$urlZurueck = kalender_url($param, array('datum' => kalender_blaettern($ansicht, $datum, -1)));
$urlWeiter  = kalender_url($param, array('datum' => kalender_blaettern($ansicht, $datum, 1)));
$urlHeute   = kalender_url($param, array('datum' => kalender_heute()));

$beschriftungZurueck = array('monat' => 'Vormonat', 'woche' => 'Vorwoche', 'tag' => 'Vortag');
$beschriftungWeiter  = array('monat' => 'Folgemonat', 'woche' => 'Folgewoche', 'tag' => 'Folgetag');

// Listenansicht mit denselben Filtern, ab dem ersten sichtbaren Tag.
$urlListe = kalender_listen_url($param, $zeitraum, $raumIdAnzeige);

$seitentitel = 'Belegung';
$aktiverTab  = 'belegung';
$seitenCss   = array('belegung.css');
$seitenJs    = array('belegung.js');
?>
  <div class="main-area">

    <?php session_fehler_anzeigen(); ?>

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
      <p class="scroll-hinweis" id="scrollHinweis" hidden>Weitere Spalten rechts – waagerecht scrollen →</p>
      <div class="slot-scroll" id="slotScroll">
        <div class="slot-raster"
             style="grid-template-columns: 48px repeat(<?php echo count($spalten); ?>, minmax(<?php echo $ansicht === 'tag' ? 110 : 84; ?>px, 1fr)); grid-template-rows: auto repeat(<?php echo count($slots); ?>, var(--slot-hoehe));">

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
          <a href="<?php echo h(kalender_buchen_url($spaltenRaum, $spaltenTag, $zeit, $param, $meineKursIds)); ?>"
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
          <a href="<?php echo h(url('buchung_bearbeiten', array('id' => (int) $buchung['id']))); ?>"
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
      <form method="get" action="<?php echo h(url('belegung')); ?>">
        <?php echo seite_feld('belegung'); ?>
        <input type="hidden" name="ansicht" value="<?php echo h($ansicht); ?>">
        <input type="hidden" name="datum" value="<?php echo h($datum->format('Y-m-d')); ?>">

        <p class="sidebar-label">Raum</p>
        <select name="raum_id" data-auto-submit>
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
        <select name="kurs_id" data-auto-submit>
          <option value="0">Alle Kurse</option>
          <?php foreach ($alleKurse as $kursOption): ?>
          <option value="<?php echo (int) $kursOption['id']; ?>"
            <?php echo ((int) $kursOption['id'] === $param['kurs_id']) ? 'selected' : ''; ?>>
            <?php echo h($kursOption['titel']); ?>
          </option>
          <?php endforeach; ?>
        </select>

        <label class="check-option">
          <input type="checkbox" name="nur_meine" value="1" data-auto-submit <?php echo $param['nur_meine'] ? 'checked' : ''; ?>>
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
      <?php echo knopf_hinzufuegen('buchung_bearbeiten', 'Buchung hinzufügen'); ?>
      <?php endif; ?>
    </aside>

  </div>
