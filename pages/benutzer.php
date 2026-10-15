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
require_once __DIR__ . '/../layout.php';
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
  }

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

  <?php kopfzeile('benutzer'); ?>

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

</body>
</html>
