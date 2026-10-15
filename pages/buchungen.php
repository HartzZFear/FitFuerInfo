<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// buchungen (Aufruf: index.php?seite=buchungen)
// Belegungsliste: welcher Kurs liegt wann in welchem Raum.
// Gleicher visueller Stil wie kurse.php (Kopfzeile mit Tabs, Wellen-Banner,
// Sidebar mit Filtern, "+"-Knopf unten rechts in der Sidebar).
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

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

// Gegenstueck zum Link "Listenansicht" in belegung.php: dieselben Filter,
// der Kalender startet beim "ab"-Datum (Standard: Wochenansicht).
$kalenderParameter = array('datum' => $abDatum);
if ($filterRaumId > 0) {
    $kalenderParameter['raum_id'] = $filterRaumId;
}
if ($filterKursId > 0) {
    $kalenderParameter['kurs_id'] = $filterKursId;
}
if ($nurMeine) {
    $kalenderParameter['nur_meine'] = 1;
}
$urlKalender = url('belegung', $kalenderParameter);

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

$seitentitel = 'Belegung';
$aktiverTab  = 'belegung';
$seitenCss   = array('belegung.css');
?>
  <div class="main-area">

    <?php session_fehler_anzeigen(); ?>

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
                <a href="<?php echo h(url('buchung_bearbeiten', array('id' => (int) $zeile['id']))); ?>" class="btn-edit">bearbeiten</a>
                <?php endif; ?>
                <?php if ($darfVerwalten && (!$istVergangen || $istAdmin)): ?>
                <a href="<?php echo h(url('buchung_loeschen', array('id' => (int) $zeile['id']))); ?>" class="btn-delete">löschen</a>
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
      <form method="get" action="<?php echo h(url('buchungen')); ?>">
        <?php echo seite_feld('buchungen'); ?>
        <p class="sidebar-label">Raum</p>
        <select name="raum_id" data-auto-submit>
          <option value="0">Alle Räume</option>
          <?php foreach ($alleRaeume as $raumOption): ?>
          <option value="<?php echo (int) $raumOption['id']; ?>"
            <?php echo ((int) $raumOption['id'] === $filterRaumId) ? 'selected' : ''; ?>>
            <?php echo h($raumOption['name']); ?>
          </option>
          <?php endforeach; ?>
        </select>

        <p class="sidebar-label">Kurs</p>
        <select name="kurs_id" data-auto-submit>
          <option value="0">Alle Kurse</option>
          <?php foreach ($alleKurse as $kursOption): ?>
          <option value="<?php echo (int) $kursOption['id']; ?>"
            <?php echo ((int) $kursOption['id'] === $filterKursId) ? 'selected' : ''; ?>>
            <?php echo h($kursOption['titel']); ?>
          </option>
          <?php endforeach; ?>
        </select>

        <p class="sidebar-label">Ab Datum</p>
        <input type="date" name="ab" value="<?php echo h($abDatum); ?>" data-auto-submit>

        <p class="sidebar-label">Wer</p>
        <div class="radio-group">
          <label class="radio-option">
            <input type="radio" name="wer" value="meine" data-auto-submit <?php echo $nurMeine ? 'checked' : ''; ?>>
            Nur meine Buchungen
          </label>
          <label class="radio-option">
            <input type="radio" name="wer" value="alle" data-auto-submit <?php echo $nurMeine ? '' : 'checked'; ?>>
            Alle
          </label>
        </div>

        <a class="link-ansicht" href="<?php echo h($urlKalender); ?>">Kalenderansicht</a>
        <a class="link-zuruecksetzen" href="<?php echo h(url('buchungen')); ?>">Filter zurücksetzen</a>

        <button type="submit" class="filter-submit">Filtern</button>
      </form>

      <?php if (!empty($meineKurse)): ?>
      <a href="<?php echo h(url('buchung_bearbeiten')); ?>" class="btn-add" aria-label="Buchung hinzufügen">+</a>
      <?php endif; ?>
    </aside>

  </div>
