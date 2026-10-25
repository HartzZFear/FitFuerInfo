<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// benutzer (Aufruf: index.php?seite=benutzer)
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

erfordere_admin();
csrf_pruefen();

// --- Aktionen per POST ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aktion  = isset($_POST['aktion']) ? $_POST['aktion'] : '';
    $zielId  = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $ziel    = benutzer_laden($zielId);
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
            header('Location: ' . url('benutzer'));
            exit;
        }
    } elseif ($aktion === 'sperren' || $aktion === 'entsperren') {
        $neuAktiv = $aktion === 'entsperren';
        $ergebnis = benutzer_aktiv_setzen($ziel, $neuAktiv);

        if ($ergebnis === true) {
            $meldung = $neuAktiv
                ? $ziel['name'] . ' wurde entsperrt.'
                : $ziel['name'] . ' wurde gesperrt und ist ab dem nächsten Klick abgemeldet.';
        } else {
            $fehler = $ergebnis;
        }
    } else {
        $fehler[] = 'Unbekannte Aktion.';
    }

    // Post/Redirect/Get: Meldung merken und die Liste neu laden, damit ein
    // Neuladen der Seite die Aktion nicht wiederholt.
    $_SESSION['benutzer_meldung'] = $meldung;
    $_SESSION['benutzer_fehler']  = $fehler;
    header('Location: ' . url('benutzer'));
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
// Alle drei Filter sind frei kombinierbar. Das SQL dazu steht in
// benutzer_liste() (lib/benutzer_logik.php).
$suche        = eingabe_text($_GET, 'suche');
$filterRolle  = isset($_GET['rolle']) ? $_GET['rolle'] : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';

if (!in_array($filterRolle, array('admin', 'mitarbeiter'), true)) {
    $filterRolle = '';
}
if (!in_array($filterStatus, array('aktiv', 'gesperrt', 'wartet'), true)) {
    $filterStatus = '';
}

$benutzerListe = benutzer_liste($suche, $filterRolle, $filterStatus);

// Kennzahlen je Benutzer - je eine Abfrage fuer alle statt einer pro Karte.
$kennzahlen          = benutzer_kennzahlen();
$kurseJeBenutzer     = $kennzahlen['kurse'];
$raeumeJeBenutzer    = $kennzahlen['raeume'];
$buchungenJeBenutzer = $kennzahlen['buchungen'];

$statusTexte = array(
    'aktiv'    => 'aktiv',
    'gesperrt' => 'gesperrt',
    'wartet'   => 'wartet auf Freischaltung',
);

$seitentitel = 'Benutzer';
$aktiverTab  = 'benutzer';
$seitenCss   = array('benutzer.css');
?>
  <div class="main-area">

    <?php session_fehler_anzeigen(); ?>

<?php if ($meldung !== ''): ?>
    <?php echo meldung($meldung, 'meldung'); ?>
<?php endif; ?>

    <?php echo fehler_liste($fehler); ?>

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
            <a href="<?php echo h(url('benutzer_bearbeiten', array('id' => $id))); ?>" class="btn-edit">bearbeiten</a>

            <?php if ($status === 'gesperrt'): ?>
            <form method="post" action="<?php echo h(url('benutzer')); ?>"
                  data-frage="<?php echo h($benutzer['name'] . ' wieder entsperren?'); ?>">
              <?php csrf_feld(); ?>
              <input type="hidden" name="aktion" value="entsperren">
              <input type="hidden" name="id" value="<?php echo $id; ?>">
              <button type="submit" class="btn-entsperren">entsperren</button>
            </form>
            <?php elseif ($benutzer['rolle'] !== 'admin'): ?>
            <?php /* Das Admin-Konto kann nicht gesperrt werden (benutzer_sperre_pruefen()) -
                     deshalb gibt es den Knopf auf seiner Karte gar nicht. */ ?>
            <form method="post" action="<?php echo h(url('benutzer')); ?>"
                  data-frage="<?php echo h($benutzer['name'] . ' wirklich sperren? Eine laufende Sitzung endet beim nächsten Klick.'); ?>">
              <?php csrf_feld(); ?>
              <input type="hidden" name="aktion" value="sperren">
              <input type="hidden" name="id" value="<?php echo $id; ?>">
              <button type="submit" class="btn-sperren">sperren</button>
            </form>
            <?php endif; ?>
          </div>

          <div class="benutzer-actions">
            <form method="post" action="<?php echo h(url('benutzer')); ?>"
                  data-frage="<?php echo h('Neuen Freischaltcode für ' . $benutzer['name'] . ' erzeugen? Ein noch offener Code wird damit ungültig; ein bestehendes Passwort gilt weiter, bis der neue Code eingelöst wird.'); ?>">
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
      <form method="get" action="<?php echo h(url('benutzer')); ?>">
        <?php echo seite_feld('benutzer'); ?>
        <p class="sidebar-label">Suche</p>
        <input type="text" name="suche" class="search-input" placeholder="Name oder E-Mail....." value="<?php echo h($suche); ?>">

        <p class="sidebar-label">Rolle</p>
        <select name="rolle" data-auto-submit>
          <option value="">Alle</option>
          <option value="admin" <?php echo $filterRolle === 'admin' ? 'selected' : ''; ?>>Admin</option>
          <option value="mitarbeiter" <?php echo $filterRolle === 'mitarbeiter' ? 'selected' : ''; ?>>Mitarbeiter</option>
        </select>

        <p class="sidebar-label">Status</p>
        <select name="status" data-auto-submit>
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

      <?php echo knopf_hinzufuegen('benutzer_bearbeiten', 'Benutzer hinzufügen'); ?>
    </aside>

  </div>
