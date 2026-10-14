<?php
// profil.php
// Eigenes Profil: Stammdaten, ein paar Kennzahlen und "Passwort ändern".
// Gleicher visueller Stil wie kurse.php (Kopfzeile mit Tabs, Wellen-Banner,
// weisse Cards mit Schatten).
//
// Name, E-Mail und Rolle kann nur der Admin aendern (benutzer_bearbeiten.php).
// Hier aendert jeder nur sein eigenes Passwort - und muss dafuer das aktuelle
// kennen, damit niemand an einem kurz unbeaufsichtigten Rechner das Passwort
// eines anderen umstellen kann.
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../layout.php';

erfordere_login();
csrf_pruefen();

$meineId = benutzer_id();

// --- Passwort aendern (POST) ----------------------------------------------
$fehler = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $altesPasswort = isset($_POST['passwort_alt']) ? $_POST['passwort_alt'] : '';
    $passwort1     = isset($_POST['passwort1']) ? $_POST['passwort1'] : '';
    $passwort2     = isset($_POST['passwort2']) ? $_POST['passwort2'] : '';

    // Hash frisch aus der Datenbank, aktueller_benutzer() liefert ihn bewusst nicht.
    $hash = abfrage('SELECT passwort_hash FROM benutzer WHERE id = ?', array($meineId))->fetchColumn();

    $altesStimmt = false;
    if ($altesPasswort === '') {
        $fehler[] = 'Bitte das aktuelle Passwort eingeben.';
    } elseif (!is_string($hash) || !password_verify($altesPasswort, $hash)) {
        $fehler[] = 'Das aktuelle Passwort ist falsch.';
    } else {
        $altesStimmt = true;
    }

    // Wie in passwort_setzen.php, aber Regeln und Wiederholung werden
    // unabhaengig voneinander geprueft, damit alle Probleme auf einmal
    // angezeigt werden.
    if ($passwort1 === '' || $passwort2 === '') {
        $fehler[] = 'Bitte das neue Passwort zweimal eingeben.';
    } else {
        $fehler = array_merge($fehler, pruefe_passwortregeln($passwort1));

        if ($passwort1 !== $passwort2) {
            $fehler[] = 'Die beiden neuen Passwörter stimmen nicht überein.';
        }
        // Nur vergleichen, wenn das alte stimmt - sonst liesse sich ueber
        // diese Meldung das aktuelle Passwort erraten.
        if ($altesStimmt && $passwort1 === $altesPasswort) {
            $fehler[] = 'Das neue Passwort muss sich vom bisherigen unterscheiden.';
        }
    }

    if (empty($fehler)) {
        // Ein noch offener Freischaltcode ("Passwort vergessen") wird mit
        // verworfen: Wer sein Passwort gerade selbst gesetzt hat, braucht ihn
        // nicht mehr, und ein herumliegender Code koennte es sonst
        // ueberschreiben.
        abfrage(
            'UPDATE benutzer
             SET passwort_hash = ?, freischaltcode = NULL, code_gueltig_bis = NULL
             WHERE id = ?',
            array(password_hash($passwort1, PASSWORD_DEFAULT), $meineId)
        );

        // Neue Session-ID, damit eine evtl. abgegriffene alte nicht weiter gilt.
        session_regenerate_id(true);

        // Post/Redirect/Get: Neuladen schickt das Formular nicht erneut ab.
        $_SESSION['profil_meldung'] = 'Das Passwort wurde geändert. Ab der nächsten Anmeldung gilt nur noch das neue Passwort.';
        header('Location: profil.php');
        exit;
    }
}

$meldung = isset($_SESSION['profil_meldung']) ? $_SESSION['profil_meldung'] : '';
unset($_SESSION['profil_meldung']);

// --- Daten fuer die Anzeige -----------------------------------------------
$ich = aktueller_benutzer();

// Eigentuemer = Ersteller ODER Eintrag in kurs_eigentuemer (wie benutzer.php).
// UNION entfernt doppelte Kurse, wenn jemand beides ist.
$anzahlKurse = (int) abfrage(
    'SELECT COUNT(*) FROM (
         SELECT id AS kurs_id FROM kurs WHERE ersteller_id = ?
         UNION
         SELECT kurs_id FROM kurs_eigentuemer WHERE benutzer_id = ?
     ) AS eigentum',
    array($meineId, $meineId)
)->fetchColumn();

$anzahlRaeume = (int) abfrage(
    'SELECT COUNT(*) FROM raum_bearbeiter WHERE benutzer_id = ?',
    array($meineId)
)->fetchColumn();

$anzahlBuchungen = (int) abfrage(
    'SELECT COUNT(*) FROM buchung WHERE benutzer_id = ? AND start >= ?',
    array($meineId, date('Y-m-d H:i:s'))
)->fetchColumn();

$rollenTexte = array(
    'admin'       => 'Admin (Systemverwalter)',
    'mitarbeiter' => 'Mitarbeiter',
);
$rolleText = isset($rollenTexte[$ich['rolle']]) ? $rollenTexte[$ich['rolle']] : $ich['rolle'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profil – FitFürInfo</title>
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

  /* ---- Hauptbereich: zwei Cards nebeneinander, schmal untereinander ---- */
  .main-area {
    max-width: 1100px;
    width: 92%;
    margin: 20px auto 40px;
    padding: 0 16px 16px;
  }

  .profil-raster {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    align-items: start;
  }

  .karte {
    background: var(--card-bg);
    border-radius: 14px;
    box-shadow: 0 12px 28px rgba(20, 40, 50, 0.1);
    padding: 22px 24px 26px;
  }

  .karte-titel {
    margin: 0 0 16px;
    font-size: 19px;
    font-weight: 700;
    color: var(--text-dark);
  }

  .daten {
    margin: 0;
    display: grid;
    grid-template-columns: max-content 1fr;
    gap: 10px 18px;
    font-size: 14px;
  }

  .daten dt {
    color: var(--text-muted);
    font-weight: 600;
  }

  .daten dd {
    margin: 0;
    color: var(--text-dark);
    overflow-wrap: anywhere;
  }

  .daten dd a {
    color: var(--blue);
    text-decoration: none;
    font-size: 12px;
    margin-left: 6px;
  }
  .daten dd a:hover { text-decoration: underline; }

  .leer { color: var(--text-muted); font-style: italic; }

  .hinweis {
    margin: 18px 0 0;
    padding: 10px 12px;
    border-radius: 8px;
    font-size: 12.5px;
    line-height: 1.45;
    background: #eef6f7;
    border: 1px solid #cfe6e9;
    color: #3a6b72;
  }

  .karte label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    color: var(--blue);
    margin: 16px 0 6px;
  }

  .karte label:first-of-type { margin-top: 0; }

  .karte input[type="password"] {
    width: 100%;
    padding: 11px 14px;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 14px;
    color: var(--text-dark);
    background: #fbfcfc;
    outline: none;
  }

  .karte input[type="password"]:focus {
    border-color: var(--blue);
    background: #ffffff;
  }

  .knopf {
    margin-top: 20px;
    padding: 11px 24px;
    border: none;
    border-radius: 8px;
    background: linear-gradient(90deg, var(--blue) 0%, var(--teal) 100%);
    color: #ffffff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
  }
  .knopf:hover { filter: brightness(1.05); }

  .meldung {
    margin: 0 0 16px;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    background: #fdeceb;
    border: 1px solid #f0b3ae;
    color: #a13a2f;
  }
  .meldung ul { margin: 0; padding-left: 18px; }

  .meldung-erfolg {
    margin: 0 0 20px;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    background: #e6f4ea;
    border: 1px solid #a8d5b5;
    color: #205c33;
  }

  @media (max-width: 900px) {
    .profil-raster { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

  <?php kopfzeile('profil'); ?>

  <div class="main-area">

    <?php session_fehler_anzeigen(); ?>

    <?php if ($meldung !== ''): ?>
    <div class="meldung-erfolg" role="status"><?php echo h($meldung); ?></div>
    <?php endif; ?>

    <div class="profil-raster">

      <section class="karte">
        <h2 class="karte-titel">Mein Profil</h2>
        <dl class="daten">
          <dt>Benutzername</dt>
          <dd><?php echo h($ich['name']); ?></dd>

          <dt>E-Mail</dt>
          <?php if ($ich['email'] !== null && $ich['email'] !== ''): ?>
          <dd><?php echo h($ich['email']); ?></dd>
          <?php else: ?>
          <dd class="leer">keine E-Mail hinterlegt</dd>
          <?php endif; ?>

          <dt>Rolle</dt>
          <dd><?php echo h($rolleText); ?></dd>

          <dt>Eigene Kurse</dt>
          <dd><?php echo (int) $anzahlKurse; ?><?php if ($anzahlKurse > 0): ?> <a href="kurse.php?ownership=own">anzeigen</a><?php endif; ?></dd>

          <dt>Räume als Bearbeiter</dt>
          <dd><?php echo (int) $anzahlRaeume; ?><?php if ($anzahlRaeume > 0): ?> <a href="raeume.php?zustaendig=meine">anzeigen</a><?php endif; ?></dd>

          <dt>Kommende Buchungen</dt>
          <dd><?php echo (int) $anzahlBuchungen; ?><?php if ($anzahlBuchungen > 0): ?> <a href="buchungen.php?wer=meine">anzeigen</a><?php endif; ?></dd>
        </dl>

        <p class="hinweis">
          Benutzername, E-Mail und Rolle können Sie hier nicht selbst ändern.
          <?php if (ist_admin()): ?>
          Als Admin ändern Sie diese Angaben im Tab <a href="benutzer.php">Benutzer</a>.
          <?php else: ?>
          Wenden Sie sich dafür an den Systemverwalter (Admin).
          <?php endif; ?>
        </p>
      </section>

      <section class="karte">
        <h2 class="karte-titel">Passwort ändern</h2>

        <?php if (!empty($fehler)): ?>
        <div class="meldung" role="alert">
          <ul>
            <?php foreach ($fehler as $einzelnerFehler): ?>
            <li><?php echo h($einzelnerFehler); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <form method="post" action="profil.php" novalidate>
          <?php csrf_feld(); ?>

          <label for="passwort_alt">Aktuelles Passwort</label>
          <input type="password" id="passwort_alt" name="passwort_alt" autocomplete="current-password">

          <label for="passwort1">Neues Passwort</label>
          <input type="password" id="passwort1" name="passwort1" autocomplete="new-password">

          <label for="passwort2">Neues Passwort wiederholen</label>
          <input type="password" id="passwort2" name="passwort2" autocomplete="new-password">

          <p class="hinweis">
            Das Passwort muss mindestens <?php echo (int) PW_MIN_LAENGE; ?> Zeichen lang sein
            und mindestens einen Kleinbuchstaben sowie eine Ziffer enthalten.
          </p>

          <button type="submit" class="knopf">Passwort ändern</button>
        </form>
      </section>

    </div>
  </div>

</body>
</html>
