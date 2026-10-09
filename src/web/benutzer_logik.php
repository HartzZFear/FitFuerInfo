<?php
/**
 * Gemeinsame Funktionen fuer die Benutzerverwaltung.
 *
 * Benutzer anlegen, bearbeiten und sperren darf nur der Admin. Ein Passwort
 * vergibt er dabei NIE: Er erzeugt einen Freischaltcode, und der Mitarbeiter
 * setzt sein Passwort damit selbst in passwort_setzen.php (siehe auth.php).
 *
 * Geloescht werden Benutzer bewusst nicht - kurs.ersteller_id und
 * buchung.benutzer_id verweisen per RESTRICT auf sie. Stattdessen wird das
 * Konto gesperrt (aktiv = 0); erfordere_login() beendet dann auch eine noch
 * laufende Session.
 *
 * Damit sich niemand aussperrt, gilt:
 *   - der letzte aktive Admin darf weder gesperrt noch zum Mitarbeiter
 *     gemacht werden
 *   - der Admin darf sich nicht selbst sperren
 * Diese Pruefung steht in benutzer_darf_aendern() und laeuft IMMER
 * serverseitig, nicht nur ueber das Ein-/Ausblenden der Buttons.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/**
 * So lange ist ein neu erzeugter Freischaltcode gueltig.
 */
define('FREISCHALTCODE_GUELTIG_TAGE', 7);

/**
 * Erzeugt einen neuen Freischaltcode fuer den Benutzer und speichert ihn
 * mit einer Gueltigkeit von FREISCHALTCODE_GUELTIG_TAGE Tagen. Ein noch
 * offener alter Code wird dadurch ungueltig.
 *
 * passwort_hash bleibt unveraendert: Ein bestehendes Passwort gilt weiter,
 * bis der neue Code in passwort_setzen.php eingeloest wird ("Passwort
 * vergessen"). Erst code_einloesen() ueberschreibt es.
 *
 * Der Code wird im Klartext zurueckgegeben, damit der Aufrufer ihn genau
 * EINMAL anzeigen kann.
 *
 * @param  int $benutzerId
 * @return string|false false, wenn es den Benutzer nicht gibt
 */
function benutzer_code_erzeugen($benutzerId)
{
    $vorhanden = abfrage('SELECT id FROM benutzer WHERE id = ?', array((int) $benutzerId))->fetch();
    if (!$vorhanden) {
        return false;
    }

    $code       = erzeuge_freischaltcode();
    $gueltigBis = date('Y-m-d H:i:s', strtotime('+' . FREISCHALTCODE_GUELTIG_TAGE . ' days'));

    abfrage(
        'UPDATE benutzer SET freischaltcode = ?, code_gueltig_bis = ? WHERE id = ?',
        array($code, $gueltigBis, (int) $benutzerId)
    );

    return $code;
}

/**
 * Anzahl der Admins, die nicht gesperrt sind.
 *
 * @return int
 */
function benutzer_anzahl_aktive_admins()
{
    return (int) abfrage(
        'SELECT COUNT(*) FROM benutzer WHERE rolle = ? AND aktiv = 1',
        array('admin')
    )->fetchColumn();
}

/**
 * Sperrt (SELECT ... FOR UPDATE) die Zeilen aller aktiven Admins bis zum
 * Ende der laufenden Transaktion. So koennen zwei Admins nicht gleichzeitig
 * je den anderen sperren und damit keinen aktiven Admin uebrig lassen.
 * Gehoert deshalb vor benutzer_darf_aendern() in dieselbe Transaktion.
 */
function benutzer_admins_sperren()
{
    abfrage('SELECT id FROM benutzer WHERE rolle = ? AND aktiv = 1 FOR UPDATE', array('admin'));
}

/**
 * Prueft, ob eine Aenderung an Rolle oder Status eines bestehenden Benutzers
 * erlaubt ist, und sammelt - wie buchung_pruefen() - alle Verstoesse ein.
 *
 * Abgelehnt wird, wenn
 *   - der Admin sich selbst sperren wuerde, oder
 *   - der Benutzer der letzte aktive Admin ist und gesperrt oder zum
 *     Mitarbeiter gemacht werden soll.
 *
 * @param  int    $zielId    der zu aendernde Benutzer
 * @param  int    $eigeneId  der angemeldete Admin
 * @param  string $neueRolle 'admin' oder 'mitarbeiter'
 * @param  bool   $neuAktiv
 * @return array leer = erlaubt, sonst deutsche Fehlermeldungen
 */
function benutzer_darf_aendern($zielId, $eigeneId, $neueRolle, $neuAktiv)
{
    $fehler = array();

    $ziel = abfrage('SELECT rolle, aktiv FROM benutzer WHERE id = ?', array((int) $zielId))->fetch();
    if (!$ziel) {
        $fehler[] = 'Diesen Benutzer gibt es nicht.';
        return $fehler;
    }

    if ((int) $zielId === (int) $eigeneId && !$neuAktiv) {
        $fehler[] = 'Sie können Ihr eigenes Konto nicht sperren.';
    }

    $istAktiverAdmin    = $ziel['rolle'] === 'admin' && (int) $ziel['aktiv'] === 1;
    $bleibtAktiverAdmin = $neueRolle === 'admin' && $neuAktiv;

    if ($istAktiverAdmin && !$bleibtAktiverAdmin && benutzer_anzahl_aktive_admins() <= 1) {
        $fehler[] = 'Das ist der letzte aktive Admin. Er kann weder gesperrt noch zum Mitarbeiter '
            . 'gemacht werden - sonst könnte niemand mehr Benutzer verwalten.';
    }

    return $fehler;
}

/**
 * Status eines Benutzers fuer Anzeige und Filter.
 *
 * @param  array $benutzer Zeile mit aktiv und passwort_hash
 * @return string 'gesperrt', 'wartet' oder 'aktiv'
 */
function benutzer_status($benutzer)
{
    if ((int) $benutzer['aktiv'] !== 1) {
        return 'gesperrt';
    }
    if ($benutzer['passwort_hash'] === null) {
        return 'wartet';
    }
    return 'aktiv';
}

/**
 * Legt einen frisch erzeugten Code fuer die einmalige Anzeige in der
 * Session ab. benutzer.php zeigt ihn beim naechsten Aufruf genau einmal an
 * und loescht ihn sofort wieder (Post/Redirect/Get - ein Neuladen der Seite
 * erzeugt so keinen weiteren Code und zeigt den alten nicht noch einmal).
 *
 * @param int    $benutzerId
 * @param string $code
 */
function benutzer_code_merken($benutzerId, $code)
{
    session_starten();

    $zeile = abfrage(
        'SELECT name, code_gueltig_bis FROM benutzer WHERE id = ?',
        array((int) $benutzerId)
    )->fetch();

    $_SESSION['code_anzeige'] = array(
        'name'        => $zeile['name'],
        'code'        => $code,
        'gueltig_bis' => $zeile['code_gueltig_bis'],
    );
}

/**
 * Zeigt den gemerkten Freischaltcode einmalig auf einer eigenen Seite an,
 * entfernt ihn aus der Session und beendet das Skript. Ist nichts gemerkt,
 * kehrt die Funktion einfach zurueck.
 */
function benutzer_code_anzeigen_falls_vorhanden()
{
    session_starten();

    if (!isset($_SESSION['code_anzeige'])) {
        return;
    }

    $anzeige = $_SESSION['code_anzeige'];
    unset($_SESSION['code_anzeige']);

    $schema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $link   = $schema . '://' . $host . BASE_URL . '/src/web/pages/passwort_setzen.php';

    $gueltigBis = date('d.m.Y, H:i', strtotime($anzeige['gueltig_bis'])) . ' Uhr';

    // Der Code soll nicht ueber den Browser-Cache wieder auftauchen.
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Freischaltcode – FitFürInfo</title>
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
  .kopf {
    position: relative;
    background: linear-gradient(135deg, var(--teal), var(--blue));
    color: #fff;
    padding: 40px 20px 70px;
    text-align: center;
  }
  .kopf h1 { margin: 0 0 4px; font-size: 24px; font-weight: 700; }
  .kopf p { margin: 0; opacity: .9; font-size: 14px; }
  .kopf .welle { position: absolute; left: 0; bottom: -1px; width: 100%; height: 60px; display: block; }
  .inhalt { max-width: 560px; margin: 24px auto 40px; padding: 0 16px; }

  /* ---- Tab-Leiste: gleiche Optik wie die Tabs in kurse.php ---- */
  .tab-group {
    display: flex;
    gap: 16px;
    max-width: 380px;
    margin: 0 auto 20px;
  }
  .tab {
    flex: 1;
    text-align: center;
    padding: 10px 0;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-muted);
    text-decoration: none;
    cursor: pointer;
    border: 1px solid var(--border);
    background: #fbfcfc;
  }
  .tab:hover,
  .tab:focus-visible { border-color: var(--blue); color: var(--blue); }
  .tab.active {
    background: linear-gradient(90deg, var(--blue) 0%, var(--teal) 45%, var(--orange) 100%);
    color: #ffffff;
    border-color: transparent;
  }

  .karte {
    background: var(--card-bg);
    border-radius: 14px;
    box-shadow: 0 12px 28px rgba(20, 40, 50, .12);
    padding: 28px 28px 32px;
    text-align: center;
  }
  .karte-titel { margin: 0 0 6px; font-size: 20px; color: var(--text-dark); }
  .karte-text { margin: 0 0 14px; font-size: 14px; color: var(--text-muted); line-height: 1.5; }
  .code {
    margin: 10px 0 14px;
    padding: 18px 10px;
    border-radius: 12px;
    border: 2px dashed var(--teal);
    background: #f2f9fa;
    font-family: Consolas, "Courier New", monospace;
    font-size: 42px;
    font-weight: 700;
    letter-spacing: 8px;
    color: var(--text-dark);
  }
  .einloesen {
    margin: 0 0 22px;
    font-size: 13px;
    color: var(--text-dark);
    word-break: break-all;
  }
  .einloesen a { color: var(--blue); }
  .warnung {
    margin: 0 0 22px;
    padding: 10px 14px;
    border-radius: 8px;
    background: #fff4e8;
    border: 1px solid #f3c79e;
    color: #8a4b16;
    font-size: 13px;
    text-align: left;
    line-height: 1.5;
  }
  .link-zurueck {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 8px;
    background: linear-gradient(90deg, var(--blue) 0%, var(--teal) 100%);
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
  }
  .link-zurueck:hover { filter: brightness(1.05); }
</style>
</head>
<body>
  <div class="kopf">
    <h1>FitFürInfo</h1>
    <p>Freischaltcode</p>
    <svg class="welle" viewBox="0 0 1440 200" preserveAspectRatio="none">
      <defs>
        <linearGradient id="waveGradient" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stop-color="#1a8f9c"/>
          <stop offset="45%" stop-color="#2f6fb0"/>
          <stop offset="100%" stop-color="#e8792e"/>
        </linearGradient>
      </defs>
      <path fill="url(#waveGradient)" d="M0,80 C240,160 480,0 720,60 C960,120 1200,20 1440,90 L1440,0 L0,0 Z"/>
    </svg>
  </div>
  <div class="inhalt">

    <nav class="tab-group">
      <a href="kurse.php" class="tab">Kurse</a>
      <a href="raeume.php" class="tab">Räume</a>
      <a href="belegung.php" class="tab">Belegung</a>
      <a href="benutzer.php" class="tab active">Benutzer</a>
    </nav>

    <?php session_fehler_anzeigen(); ?>

    <div class="karte">
      <h2 class="karte-titel">Code für <?php echo h($anzeige['name']); ?>:</h2>
      <div class="code"><?php echo h($anzeige['code']); ?></div>
      <p class="karte-text">gültig bis <?php echo h($gueltigBis); ?></p>

      <p class="einloesen">
        Einlösen unter:<br>
        <a href="<?php echo h($link); ?>"><?php echo h($link); ?></a>
      </p>

      <div class="warnung">
        Geben Sie den Code persönlich weiter. Er wird nur dieses eine Mal angezeigt
        und ist danach nirgends mehr abrufbar. Ein bestehendes Passwort bleibt gültig,
        bis der Code eingelöst wird.
      </div>

      <a class="link-zurueck" href="benutzer.php">Zurück zur Benutzerliste</a>
    </div>
  </div>
</body>
</html>
<?php
    exit;
}
