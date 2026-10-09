<?php
// buchungen.php
// Belegungsliste: welcher Kurs liegt wann in welchem Raum.
// Gleicher visueller Stil wie kurse.php (Kopfzeile mit Tabs, Wellen-Banner,
// Sidebar mit Filtern, "+"-Knopf unten rechts in der Sidebar).
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../layout.php';
require_once __DIR__ . '/../kurs_rechte.php';
require_once __DIR__ . '/../buchung_logik.php';

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
$urlKalender = 'belegung.php?' . http_build_query($kalenderParameter);

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

  .liste-karte {
    background: var(--card-bg);
    border-radius: 14px;
    box-shadow: 0 12px 28px rgba(20, 40, 50, 0.1);
    padding: 8px 4px;
    overflow-x: auto;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
    color: var(--text-dark);
  }

  th {
    text-align: left;
    padding: 14px 16px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    color: var(--blue);
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
  }

  td {
    padding: 14px 16px;
    border-bottom: 1px solid #f0f4f5;
    vertical-align: middle;
  }

  tr:last-child td { border-bottom: none; }

  .spalte-kurs { font-weight: 600; }
  .spalte-zeit { white-space: nowrap; }

  .zeile-vergangen td { color: var(--text-muted); }

  .marke-vergangen {
    display: inline-block;
    margin-left: 8px;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
    background: #f0f4f5;
    border: 1px solid var(--border);
  }

  .zeilen-aktionen {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
  }

  .btn-edit,
  .btn-delete {
    display: inline-block;
    border-radius: 8px;
    padding: 7px 14px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    color: #ffffff;
    border: none;
  }

  .btn-edit { background: linear-gradient(90deg, var(--blue) 0%, var(--teal) 100%); }
  .btn-edit:hover { filter: brightness(1.05); }

  .btn-delete { background: linear-gradient(90deg, var(--orange) 0%, #d9534f 100%); }
  .btn-delete:hover { filter: brightness(1.05); }

  .empty-state {
    text-align: center;
    color: var(--text-muted);
    font-size: 14px;
    padding: 40px 20px;
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

  .sidebar select,
  .sidebar input[type="date"] {
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

  .sidebar select:focus,
  .sidebar input[type="date"]:focus {
    border-color: var(--blue);
    background: #ffffff;
  }

  .radio-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 18px;
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

  .link-zuruecksetzen {
    font-size: 13px;
    color: var(--text-muted);
    text-decoration: none;
  }
  .link-zuruecksetzen:hover { text-decoration: underline; }

  .link-ansicht {
    display: block;
    margin-bottom: 8px;
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
  }
</style>
</head>
<body>

  <?php kopfzeile('belegung'); ?>

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
                <a href="buchung_bearbeiten.php?id=<?php echo (int) $zeile['id']; ?>" class="btn-edit">bearbeiten</a>
                <?php endif; ?>
                <?php if ($darfVerwalten && (!$istVergangen || $istAdmin)): ?>
                <a href="buchung_loeschen.php?id=<?php echo (int) $zeile['id']; ?>" class="btn-delete">löschen</a>
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
      <form method="get" action="buchungen.php">
        <p class="sidebar-label">Raum</p>
        <select name="raum_id" onchange="this.form.submit()">
          <option value="0">Alle Räume</option>
          <?php foreach ($alleRaeume as $raumOption): ?>
          <option value="<?php echo (int) $raumOption['id']; ?>"
            <?php echo ((int) $raumOption['id'] === $filterRaumId) ? 'selected' : ''; ?>>
            <?php echo h($raumOption['name']); ?>
          </option>
          <?php endforeach; ?>
        </select>

        <p class="sidebar-label">Kurs</p>
        <select name="kurs_id" onchange="this.form.submit()">
          <option value="0">Alle Kurse</option>
          <?php foreach ($alleKurse as $kursOption): ?>
          <option value="<?php echo (int) $kursOption['id']; ?>"
            <?php echo ((int) $kursOption['id'] === $filterKursId) ? 'selected' : ''; ?>>
            <?php echo h($kursOption['titel']); ?>
          </option>
          <?php endforeach; ?>
        </select>

        <p class="sidebar-label">Ab Datum</p>
        <input type="date" name="ab" value="<?php echo h($abDatum); ?>" onchange="this.form.submit()">

        <p class="sidebar-label">Wer</p>
        <div class="radio-group">
          <label class="radio-option">
            <input type="radio" name="wer" value="meine" onchange="this.form.submit()" <?php echo $nurMeine ? 'checked' : ''; ?>>
            Nur meine Buchungen
          </label>
          <label class="radio-option">
            <input type="radio" name="wer" value="alle" onchange="this.form.submit()" <?php echo $nurMeine ? '' : 'checked'; ?>>
            Alle
          </label>
        </div>

        <a class="link-ansicht" href="<?php echo h($urlKalender); ?>">Kalenderansicht</a>
        <a class="link-zuruecksetzen" href="buchungen.php">Filter zurücksetzen</a>

        <button type="submit" class="filter-submit">Filtern</button>
      </form>

      <?php if (!empty($meineKurse)): ?>
      <a href="buchung_bearbeiten.php" class="btn-add" aria-label="Buchung hinzufügen">+</a>
      <?php endif; ?>
    </aside>

  </div>

</body>
</html>
