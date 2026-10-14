<?php
// benutzer_bearbeiten.php
// Benutzer anlegen (ohne ?id=) oder bearbeiten (mit ?id=N). Nur fuer den Admin.
//
// Es gibt hier bewusst KEIN Passwortfeld: Der Admin darf kein Passwort eines
// Mitarbeiters kennen. Ein neuer Benutzer wird ohne Passwort gespeichert und
// bekommt einen Freischaltcode, der danach genau einmal angezeigt wird; das
// Passwort setzt der Mitarbeiter selbst in passwort_setzen.php.
//
// Beim Bearbeiten gelten die Regeln aus benutzer_darf_aendern(): Der letzte
// aktive Admin bleibt Admin und aktiv, und niemand sperrt sich selbst.
//
// Loeschen gibt es nicht - kurs.ersteller_id und buchung.benutzer_id stehen
// auf RESTRICT. Stattdessen wird gesperrt.
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../kurs_rechte.php';
require_once __DIR__ . '/../benutzer_logik.php';

erfordere_admin();
csrf_pruefen();

$meineId  = benutzer_id();
$istAdmin = ist_admin();

$istBearbeiten = isset($_GET['id']) && $_GET['id'] !== '';
$zielId        = $istBearbeiten ? (int) $_GET['id'] : null;

$benutzer = null;

if ($istBearbeiten) {
    $benutzer = abfrage(
        'SELECT id, name, email, rolle, aktiv, passwort_hash, freischaltcode, code_gueltig_bis
         FROM benutzer WHERE id = ?',
        array($zielId)
    )->fetch();

    if (!$benutzer) {
        zugriff_verweigert_seite(
            'Diesen Benutzer gibt es nicht.',
            'benutzer.php',
            'Zurück zur Benutzerliste'
        );
    }
}

// --- Vorbelegung der Felder ---------------------------------------------
$name  = $istBearbeiten ? $benutzer['name'] : '';
$email = $istBearbeiten && $benutzer['email'] !== null ? $benutzer['email'] : '';
$rolle = $istBearbeiten ? $benutzer['rolle'] : 'mitarbeiter';
$aktiv = $istBearbeiten ? (int) $benutzer['aktiv'] === 1 : true;

$fehler = array();

// --- Speichern ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $rolle = isset($_POST['rolle']) ? $_POST['rolle'] : '';
    $aktiv = isset($_POST['aktiv']) && $_POST['aktiv'] === '1';

    if ($name === '') {
        $fehler[] = 'Bitte einen Benutzernamen eingeben.';
    } elseif (mb_strlen($name, 'UTF-8') > 50) {
        $fehler[] = 'Der Benutzername darf höchstens 50 Zeichen lang sein.';
    }

    // E-Mail ist optional. Leer wird als NULL gespeichert.
    if ($email !== '') {
        if (mb_strlen($email, 'UTF-8') > 100) {
            $fehler[] = 'Die E-Mail-Adresse darf höchstens 100 Zeichen lang sein.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $fehler[] = 'Die E-Mail-Adresse "' . $email . '" hat kein gültiges Format (Beispiel: name@schule.de).';
        }
    }

    $rolleGueltig = in_array($rolle, array('admin', 'mitarbeiter'), true);
    if (!$rolleGueltig) {
        $fehler[] = 'Bitte eine gültige Rolle auswählen.';
        $rolle    = 'mitarbeiter';
    }

    // Auch bei Formatfehlern weiterpruefen, damit alle Meldungen (z. B.
    // ungueltige E-Mail UND doppelter Name) auf einmal erscheinen.
    // Gespeichert wird weiter unten nur, wenn $fehler leer bleibt.
    if ($rolleGueltig) {
        $db = db();
        $db->beginTransaction();

        try {
            if ($istBearbeiten) {
                // Admin-Zeilen sperren, bevor geprueft wird - sonst koennten
                // zwei Admins gleichzeitig den jeweils anderen entmachten.
                benutzer_admins_sperren();
                $fehler = array_merge($fehler, benutzer_darf_aendern($zielId, $meineId, $rolle, $aktiv));
            }

            // Der Name ist in der Datenbank eindeutig (uk_benutzer_name).
            // Vorher pruefen, damit der Admin eine verstaendliche Meldung
            // bekommt statt eines Datenbankfehlers.
            $sql       = 'SELECT id FROM benutzer WHERE name = ?';
            $parameter = array($name);
            if ($istBearbeiten) {
                $sql .= ' AND id <> ?';
                $parameter[] = $zielId;
            }

            if ($name !== '' && abfrage($sql, $parameter)->fetch()) {
                $fehler[] = 'Es gibt bereits einen Benutzer mit dem Namen "' . $name . '". '
                    . 'Groß- und Kleinschreibung zählt dabei nicht.';
            }

            if (empty($fehler)) {
                $emailWert = $email === '' ? null : $email;

                if ($istBearbeiten) {
                    abfrage(
                        'UPDATE benutzer SET name = ?, email = ?, rolle = ?, aktiv = ? WHERE id = ?',
                        array($name, $emailWert, $rolle, $aktiv ? 1 : 0, $zielId)
                    );
                    $db->commit();

                    $_SESSION['benutzer_meldung'] = 'Die Änderungen an ' . $name . ' wurden gespeichert.';
                    $_SESSION['benutzer_fehler']  = array();
                    header('Location: benutzer.php');
                    exit;
                }

                // Neuer Benutzer: ohne Passwort speichern (passwort_hash
                // bleibt NULL) und gleich einen Freischaltcode erzeugen.
                abfrage(
                    'INSERT INTO benutzer (name, email, passwort_hash, rolle, aktiv) VALUES (?, ?, NULL, ?, ?)',
                    array($name, $emailWert, $rolle, $aktiv ? 1 : 0)
                );
                $neueId = (int) $db->lastInsertId();
                $code   = benutzer_code_erzeugen($neueId);
                $db->commit();

                // Einmalige Anzeige uebernimmt benutzer.php.
                benutzer_code_merken($neueId, $code);
                header('Location: benutzer.php');
                exit;
            }

            $db->rollBack();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            // Faengt auch den RuntimeException aus erzeuge_freischaltcode().
            // 23000 = Verletzung eines UNIQUE-Schluessels, z. B. wenn
            // jemand denselben Namen gleichzeitig angelegt hat.
            if ($e instanceof PDOException && $e->getCode() === '23000') {
                $fehler[] = 'Es gibt bereits einen Benutzer mit dem Namen "' . $name . '".';
            } else {
                $fehler[] = 'Der Benutzer konnte nicht gespeichert werden. Bitte erneut versuchen.';
            }
        }
    }
}

$seitenTitel = $istBearbeiten ? 'Benutzer bearbeiten' : 'Neuen Benutzer anlegen';
$istIch      = $istBearbeiten && (int) $zielId === (int) $meineId;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo h($seitenTitel); ?> – FitFürInfo</title>
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
  .inhalt { max-width: 640px; margin: 24px auto 40px; padding: 0 16px; }

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
  }
  .karte-titel { margin: 0 0 18px; font-size: 20px; color: var(--text-dark); }
  label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-dark);
    margin: 16px 0 6px;
  }
  label:first-of-type { margin-top: 0; }
  input[type="text"],
  input[type="email"],
  select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    color: var(--text-dark);
    background: #fbfcfc;
  }
  input:focus,
  select:focus {
    outline: none;
    border-color: var(--blue);
    background: #ffffff;
  }
  .optional { font-weight: normal; color: var(--text-muted); }
  .checkbox-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: normal;
    color: var(--text-dark);
    margin: 18px 0 0;
  }
  .checkbox-item input { width: auto; accent-color: var(--blue); }
  .knopf-reihe {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-top: 26px;
  }
  .knopf {
    padding: 11px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    color: #ffffff;
    cursor: pointer;
    background: linear-gradient(90deg, var(--blue) 0%, var(--teal) 100%);
  }
  .knopf:hover { filter: brightness(1.05); }
  .link-abbrechen {
    font-size: 14px;
    color: var(--text-muted);
    text-decoration: none;
  }
  .link-abbrechen:hover { text-decoration: underline; }
  .fehler {
    margin: 0 0 18px;
    padding: 10px 14px;
    border-radius: 8px;
    background: #fdeceb;
    border: 1px solid #f0b3ae;
    color: #a13a2f;
    font-size: 13px;
  }
  .fehler ul { margin: 0; padding-left: 18px; }
  .fehler li + li { margin-top: 4px; }
  .hinweis {
    margin: 0 0 18px;
    padding: 10px 14px;
    border-radius: 8px;
    background: #eaf4f5;
    border: 1px solid #b9dde1;
    color: #17636c;
    font-size: 13px;
    line-height: 1.5;
  }
  .regel-hinweis {
    margin: 18px 0 0;
    font-size: 12px;
    color: var(--text-muted);
    line-height: 1.5;
  }
</style>
</head>
<body>

  <div class="kopf">
    <h1>FitFürInfo</h1>
    <p><?php echo h($seitenTitel); ?></p>
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
      <?php if ($istAdmin): ?>
      <a href="benutzer.php" class="tab active">Benutzer</a>
      <?php endif; ?>
    </nav>

    <?php session_fehler_anzeigen(); ?>

    <div class="karte">
      <h2 class="karte-titel"><?php echo h($seitenTitel); ?></h2>

      <?php if (!empty($fehler)): ?>
      <div class="fehler">
        <ul>
          <?php foreach ($fehler as $einzelnerFehler): ?>
          <li><?php echo h($einzelnerFehler); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <?php if ($istBearbeiten && benutzer_status($benutzer) === 'wartet'): ?>
      <div class="hinweis">
        Dieser Benutzer hat noch kein Passwort gesetzt.
        <?php if ($benutzer['freischaltcode'] !== null && strtotime($benutzer['code_gueltig_bis']) >= time()): ?>
        Sein Freischaltcode ist gültig bis <?php echo h(date('d.m.Y, H:i', strtotime($benutzer['code_gueltig_bis']))); ?> Uhr.
        <?php else: ?>
        Sein Freischaltcode ist abgelaufen – in der Benutzerliste einen neuen erzeugen.
        <?php endif; ?>
      </div>
      <?php elseif (!$istBearbeiten): ?>
      <div class="hinweis">
        Ein Passwort wird hier nicht vergeben. Nach dem Speichern erscheint einmalig ein
        Freischaltcode (<?php echo (int) FREISCHALTCODE_GUELTIG_TAGE; ?> Tage gültig), mit dem der
        Mitarbeiter sein Passwort selbst setzt.
      </div>
      <?php endif; ?>

      <form method="post" action="benutzer_bearbeiten.php<?php echo $istBearbeiten ? '?id=' . (int) $zielId : ''; ?>" novalidate>
        <?php csrf_feld(); ?>
        <label for="name">Benutzername</label>
        <input type="text" id="name" name="name" maxlength="50" value="<?php echo h($name); ?>" required>

        <label for="email">E-Mail <span class="optional">(optional)</span></label>
        <input type="email" id="email" name="email" maxlength="100" value="<?php echo h($email); ?>">

        <label for="rolle">Rolle</label>
        <select id="rolle" name="rolle">
          <option value="mitarbeiter" <?php echo $rolle === 'mitarbeiter' ? 'selected' : ''; ?>>Mitarbeiter</option>
          <option value="admin" <?php echo $rolle === 'admin' ? 'selected' : ''; ?>>Admin</option>
        </select>

        <label class="checkbox-item">
          <input type="checkbox" name="aktiv" value="1" <?php echo $aktiv ? 'checked' : ''; ?>>
          aktiv (darf sich anmelden)
        </label>

        <div class="knopf-reihe">
          <button type="submit" class="knopf">Speichern</button>
          <a class="link-abbrechen" href="benutzer.php">Abbrechen</a>
        </div>
      </form>

      <p class="regel-hinweis">
        Benutzer können nicht gelöscht werden, weil an ihnen Kurse und Buchungen hängen.
        Wer keinen Zugang mehr haben soll, wird gesperrt („aktiv“ abwählen) – eine laufende
        Sitzung endet dann beim nächsten Klick.
        <?php if ($istIch): ?>
        Das eigene Konto kann nicht gesperrt werden.
        <?php endif; ?>
        Der letzte aktive Admin bleibt immer Admin und aktiv.
      </p>
    </div>
  </div>

</body>
</html>
