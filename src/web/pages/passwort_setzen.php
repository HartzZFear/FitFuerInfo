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

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

session_starten();

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
<style>
:root {
  --teal: #1a8f9c;
  --blue: #2f6fb0;
  --orange: #e8792e;
}
* { box-sizing: border-box; }
body {
  margin: 0;
  font-family: Arial, Helvetica, sans-serif;
  color: #222;
  background: #f2f5f7;
}
.kopf {
  position: relative;
  background: linear-gradient(135deg, var(--teal), var(--blue));
  color: #fff;
  padding: 56px 20px 100px;
  text-align: center;
}
.kopf h1 {
  margin: 0 0 6px;
  font-size: 26px;
  font-weight: normal;
}
.kopf p {
  margin: 0;
  opacity: .9;
  font-size: 14px;
}
.kopf .welle {
  position: absolute;
  left: 0;
  bottom: -1px;
  width: 100%;
  height: 70px;
  display: block;
}
.inhalt {
  max-width: 380px;
  margin: -64px auto 40px;
  padding: 0 16px;
}
.karte {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 12px 30px rgba(20, 40, 60, .15);
  padding: 32px 28px;
}
.karte label {
  display: block;
  font-size: 13px;
  font-weight: bold;
  color: #444;
  margin: 16px 0 6px;
}
.karte label:first-of-type {
  margin-top: 0;
}
.karte input[type="text"],
.karte input[type="password"] {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #ccd3d8;
  border-radius: 6px;
  font-size: 14px;
}
.karte input[type="text"]:focus,
.karte input[type="password"]:focus {
  outline: none;
  border-color: var(--teal);
}
.hinweis {
  margin-top: 14px;
  padding: 10px 12px;
  border-radius: 6px;
  font-size: 12.5px;
  background: #eef6f7;
  border: 1px solid #cfe6e9;
  color: #3a6b72;
}
.knopf {
  width: 100%;
  margin-top: 20px;
  padding: 11px 12px;
  border: none;
  border-radius: 6px;
  background: var(--orange);
  color: #fff;
  font-size: 15px;
  font-weight: bold;
  cursor: pointer;
}
.knopf:hover { opacity: .92; }
.meldung {
  margin-top: 16px;
  padding: 10px 12px;
  border-radius: 6px;
  font-size: 13px;
  background: #fdeceb;
  border: 1px solid #f0b3ae;
  color: #a13a2f;
}
.meldung ul {
  margin: 0;
  padding-left: 18px;
}
.meldung-erfolg {
  margin-top: 16px;
  padding: 10px 12px;
  border-radius: 6px;
  font-size: 13px;
  background: #e6f4ea;
  border: 1px solid #a8d5b5;
  color: #205c33;
}
.fuss {
  margin-top: 20px;
  text-align: center;
  font-size: 13px;
}
.fuss a {
  color: var(--blue);
  text-decoration: none;
}
.fuss a:hover { text-decoration: underline; }
</style>
</head>
<body>

<div class="kopf">
  <h1>FitFuerInfo</h1>
  <p>Passwort setzen</p>
  <svg class="welle" viewBox="0 0 500 70" preserveAspectRatio="none">
    <path d="M0,40 C125,80 375,0 500,40 L500,70 L0,70 Z" fill="#f2f5f7"></path>
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
