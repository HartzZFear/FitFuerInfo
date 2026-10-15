<?php
/**
 * Freischaltcode einloesen und eigenes Passwort setzen.
 *
 * Der Admin legt Konten ohne Passwort an und zeigt dem Mitarbeiter nur
 * einen einmaligen Freischaltcode. Hier waehlt der Mitarbeiter sein
 * Passwort selbst - der Admin bekommt es nie zu sehen.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/funktionen.php';
require_once __DIR__ . '/../lib/auth.php';

session_starten();
csrf_pruefen();

$fehler       = array();
$erfolg       = false;
$benutzername = '';
$code         = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $benutzername = isset($_POST['username']) ? trim($_POST['username']) : '';
    $code         = isset($_POST['code']) ? trim($_POST['code']) : '';
    $passwort1    = isset($_POST['passwort1']) ? $_POST['passwort1'] : '';
    $passwort2    = isset($_POST['passwort2']) ? $_POST['passwort2'] : '';

    if ($benutzername === '' || $code === '') {
        $fehler[] = 'Bitte Benutzername und Freischaltcode eingeben.';
    }

    if ($passwort1 === '' || $passwort2 === '') {
        $fehler[] = 'Bitte das neue Passwort zweimal eingeben.';
    } elseif ($passwort1 !== $passwort2) {
        $fehler[] = 'Die beiden Passwörter stimmen nicht überein.';
    } else {
        $fehler = array_merge($fehler, pruefe_passwortregeln($passwort1));
    }

    if (empty($fehler)) {
        if (code_einloesen($benutzername, $code, $passwort1)) {
            $erfolg = true;
        } else {
            $fehler[] = 'Benutzername oder Freischaltcode ist ungültig oder abgelaufen.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Passwort setzen – FitFuerInfo</title>
<link rel="stylesheet" href="<?php echo h(asset_url('css/style.css')); ?>">
<link rel="stylesheet" href="<?php echo h(asset_url('css/passwort_setzen.css')); ?>">
</head>
<body class="seite-passwort_setzen">

<div class="kopf">
  <h1>FitFuerInfo</h1>
  <p>Passwort setzen</p>
  <svg class="welle" viewBox="0 0 500 70" preserveAspectRatio="none">
    <path d="M0,40 C125,80 375,0 500,40 L500,70 L0,70 Z"></path>
  </svg>
</div>

<div class="inhalt">
  <div class="karte">

    <?php if ($erfolg): ?>

      <div class="meldung-erfolg">
        Das Passwort wurde gespeichert. Die Anmeldung ist jetzt möglich.
      </div>
      <div class="fuss">
        <a href="login.php">Zur Anmeldung</a>
      </div>

    <?php else: ?>

      <form method="post" action="passwort_setzen.php" novalidate>
        <?php csrf_feld(); ?>
        <label for="username">Benutzername</label>
        <input type="text" id="username" name="username" placeholder="Benutzername"
               value="<?php echo h($benutzername); ?>" autofocus>

        <label for="code">Freischaltcode</label>
        <input type="text" id="code" name="code" placeholder="z. B. AB3DEF7H"
               value="<?php echo h($code); ?>">

        <label for="passwort1">Neues Passwort</label>
        <input type="password" id="passwort1" name="passwort1" placeholder="Neues Passwort">

        <label for="passwort2">Passwort wiederholen</label>
        <input type="password" id="passwort2" name="passwort2" placeholder="Passwort wiederholen">

        <div class="hinweis">
          Das Passwort muss mindestens <?php echo (int) PW_MIN_LAENGE; ?> Zeichen lang sein
          und mindestens einen Kleinbuchstaben sowie eine Ziffer enthalten.
        </div>

        <button type="submit" class="knopf">Passwort speichern</button>

        <?php if (!empty($fehler)): ?>
        <div class="meldung">
          <ul>
            <?php foreach ($fehler as $einzelnerFehler): ?>
            <li><?php echo h($einzelnerFehler); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      </form>

      <div class="fuss">
        <a href="login.php">Zur Anmeldung</a>
      </div>

    <?php endif; ?>

  </div>
</div>

</body>
</html>
