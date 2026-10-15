<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// benutzer_bearbeiten (Aufruf: index.php?seite=benutzer_bearbeiten)
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

erfordere_admin();
csrf_pruefen();

$meineId = benutzer_id();

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
            'benutzer',
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
                    header('Location: ' . url('benutzer'));
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
                header('Location: ' . url('benutzer'));
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

$seitentitel   = $istBearbeiten ? 'Benutzer bearbeiten' : 'Neuen Benutzer anlegen';
$seitenCss     = array('formular.css');
$ohneKopfzeile = true;
$istIch        = $istBearbeiten && (int) $zielId === (int) $meineId;
?>
<?php formular_kopf($seitentitel); ?>

  <div class="inhalt">

<?php formular_reiter('benutzer'); ?>

    <?php session_fehler_anzeigen(); ?>

    <div class="karte">
      <h2 class="karte-titel"><?php echo h($seitentitel); ?></h2>

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

      <form method="post" action="<?php echo h(url('benutzer_bearbeiten', $istBearbeiten ? array('id' => (int) $zielId) : array())); ?>" novalidate>
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
          <a class="link-abbrechen" href="<?php echo h(url('benutzer')); ?>">Abbrechen</a>
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
