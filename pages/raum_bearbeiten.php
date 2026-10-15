<?php
// raum_bearbeiten.php
// Raum anlegen (ohne ?id=) oder bearbeiten (mit ?id=N).
//
// Anlegen darf nur der Admin. Bearbeiten duerfen der Admin und die in
// raum_bearbeiter eingetragenen Mitarbeiter; die Bearbeiterliste selbst
// aendert nur der Admin - sonst koennte man sich gegenseitig aussperren
// oder sich selbst Zustaendigkeiten geben.
//
// Wer Arbeitsplaetze reduziert oder Software entfernt, kann damit bereits
// bestehende Buchungen ungueltig machen (Buchungsregeln 4 und 5). Das wird
// vor dem Commit geprueft - siehe raum_konflikte_mit_buchungen().
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/funktionen.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/raum_rechte.php';

erfordere_login();
csrf_pruefen();

$meineId  = benutzer_id();
$istAdmin = ist_admin();

$istBearbeiten = isset($_GET['id']) && $_GET['id'] !== '';
$raumId        = $istBearbeiten ? (int) $_GET['id'] : null;

$raum = null;

if ($istBearbeiten) {
    $raum = abfrage('SELECT * FROM raum WHERE id = ?', array($raumId))->fetch();

    if (!$raum) {
        zugriff_verweigert_seite(
            'Dieser Raum existiert nicht oder wurde bereits gelöscht.',
            'raeume.php',
            'Zurück zu den Räumen'
        );
    }
    // Rechtepruefung serverseitig - nicht nur durch Ausblenden der Buttons
    // in raeume.php.
    if (!raum_darf_bearbeiten($raumId, $meineId, $istAdmin)) {
        zugriff_verweigert_seite(
            'Sie sind für diesen Raum nicht als Bearbeiter eingetragen und können ihn deshalb nicht bearbeiten.',
            'raeume.php',
            'Zurück zu den Räumen'
        );
    }
} elseif (!raum_darf_anlegen($istAdmin)) {
    zugriff_verweigert_seite(
        'Neue Räume kann nur der Systemverwalter anlegen.',
        'raeume.php',
        'Zurück zu den Räumen'
    );
}

$alleSoftware = abfrage('SELECT id, name FROM software ORDER BY name')->fetchAll();
$alleBenutzer = $istAdmin
    ? abfrage('SELECT id, name FROM benutzer WHERE aktiv = 1 ORDER BY name')->fetchAll()
    : array();

// --- Vorbelegung der Felder ---------------------------------------------
$name                     = $istBearbeiten ? $raum['name'] : '';
$arbeitsplaetze           = $istBearbeiten ? $raum['arbeitsplaetze'] : '';
$ausgewaehlteSoftwareIds  = array();
$ausgewaehlteBearbeiterIds = array();

if ($istBearbeiten) {
    $zeilen = abfrage('SELECT software_id FROM raum_software WHERE raum_id = ?', array($raumId))->fetchAll();
    foreach ($zeilen as $zeile) {
        $ausgewaehlteSoftwareIds[] = (int) $zeile['software_id'];
    }

    if ($istAdmin) {
        $ausgewaehlteBearbeiterIds = raum_bearbeiter_ids($raumId);
    }
}

$fehler        = array();
$hatKonflikte  = false;

// --- Speichern ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name                 = isset($_POST['name']) ? trim($_POST['name']) : '';
    $arbeitsplaetzeEingabe = isset($_POST['arbeitsplaetze']) ? trim($_POST['arbeitsplaetze']) : '';

    $ausgewaehlteSoftwareIds = array();
    if (isset($_POST['software']) && is_array($_POST['software'])) {
        foreach ($_POST['software'] as $sid) {
            $ausgewaehlteSoftwareIds[] = (int) $sid;
        }
    }

    // Die Bearbeiterliste kommt nur vom Admin - ein manipuliertes Formular
    // eines Mitarbeiters wird hier schlicht ignoriert.
    if ($istAdmin) {
        $ausgewaehlteBearbeiterIds = array();
        if (isset($_POST['bearbeiter']) && is_array($_POST['bearbeiter'])) {
            foreach ($_POST['bearbeiter'] as $bid) {
                $ausgewaehlteBearbeiterIds[] = (int) $bid;
            }
        }
    }

    if ($name === '') {
        $fehler[] = 'Bitte einen Raumnamen eingeben.';
    } elseif (strlen($name) > 50) {
        $fehler[] = 'Der Raumname darf höchstens 50 Zeichen lang sein.';
    }

    if ($arbeitsplaetzeEingabe === '' || !ctype_digit($arbeitsplaetzeEingabe) || (int) $arbeitsplaetzeEingabe < 1) {
        $fehler[] = 'Die Anzahl der Arbeitsplätze muss eine ganze Zahl von mindestens 1 sein.';
    } else {
        $arbeitsplaetze = (int) $arbeitsplaetzeEingabe;
    }

    if (empty($fehler)) {
        $db = db();
        $db->beginTransaction();

        try {
            // Die Zeile des Raums sperren, bevor geprueft und geschrieben
            // wird. Dieselbe Sperre nimmt buchung_bearbeiten.php vor dem
            // Speichern einer Buchung - dadurch kann niemand einen Termin
            // in den Raum legen, waehrend hier gerade seine Plaetze oder
            // seine Software geaendert werden.
            if ($istBearbeiten) {
                abfrage('SELECT id FROM raum WHERE id = ? FOR UPDATE', array($raumId));
            }

            // Der Raumname ist in der Datenbank eindeutig (uk_raum_name).
            // Vorher pruefen, damit der Benutzer eine verstaendliche
            // Meldung bekommt statt eines Datenbankfehlers.
            $sql       = 'SELECT id FROM raum WHERE name = ?';
            $parameter = array($name);
            if ($istBearbeiten) {
                $sql .= ' AND id <> ?';
                $parameter[] = $raumId;
            }

            if (abfrage($sql, $parameter)->fetch()) {
                $fehler[] = 'Es gibt bereits einen Raum mit dem Namen "' . $name . '".';
            }

            if (empty($fehler)) {
                if ($istBearbeiten) {
                    abfrage(
                        'UPDATE raum SET name = ?, arbeitsplaetze = ? WHERE id = ?',
                        array($name, $arbeitsplaetze, $raumId)
                    );
                } else {
                    abfrage(
                        'INSERT INTO raum (name, arbeitsplaetze) VALUES (?, ?)',
                        array($name, $arbeitsplaetze)
                    );
                    $raumId = (int) $db->lastInsertId();
                }

                abfrage('DELETE FROM raum_software WHERE raum_id = ?', array($raumId));
                foreach ($ausgewaehlteSoftwareIds as $sid) {
                    abfrage('INSERT INTO raum_software (raum_id, software_id) VALUES (?, ?)', array($raumId, $sid));
                }

                if ($istAdmin) {
                    abfrage('DELETE FROM raum_bearbeiter WHERE raum_id = ?', array($raumId));
                    foreach ($ausgewaehlteBearbeiterIds as $bid) {
                        abfrage('INSERT INTO raum_bearbeiter (raum_id, benutzer_id) VALUES (?, ?)', array($raumId, $bid));
                    }
                }

                // Jetzt steht der neue Stand in der Transaktion - genau so
                // sieht ihn die Pruefung. Passt er nicht zu den kuenftigen
                // Buchungen, macht das rollBack() unten alles rueckgaengig.
                $konflikte = raum_konflikte_mit_buchungen($raumId);
                if (!empty($konflikte)) {
                    $hatKonflikte = true;
                }
                foreach ($konflikte as $meldung) {
                    $fehler[] = $meldung;
                }
            }

            if (empty($fehler)) {
                $db->commit();
                header('Location: ' . url('raeume'));
                exit;
            }

            $db->rollBack();

            // Beim Anlegen gibt es die ID nach dem rollBack nicht mehr.
            if (!$istBearbeiten) {
                $raumId = null;
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if (!$istBearbeiten) {
                $raumId = null;
            }
            $fehler[] = 'Der Raum konnte nicht gespeichert werden. Bitte erneut versuchen.';
        }
    }
}

$seitenTitel = $istBearbeiten ? 'Raum bearbeiten' : 'Neuen Raum anlegen';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo h($seitenTitel); ?> – FitFürInfo</title>
<link rel="stylesheet" href="<?php echo h(asset_url('css/style.css')); ?>">
<link rel="stylesheet" href="<?php echo h(asset_url('css/formular.css')); ?>">
</head>
<body class="seite-raum_bearbeiten">

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

    <nav class="reiter-gruppe">
      <a href="<?php echo h(url('kurse')); ?>" class="reiter">Kurse</a>
      <a href="<?php echo h(url('raeume')); ?>" class="reiter active">Räume</a>
      <a href="<?php echo h(url('belegung')); ?>" class="reiter">Belegung</a>
      <?php if ($istAdmin): ?>
      <a href="<?php echo h(url('benutzer')); ?>" class="reiter">Benutzer</a>
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

      <?php /* Bei Konflikten mit bestehenden Buchungen hilft der Blick in die
               Belegung des Raums - deshalb dann den Link dorthin einblenden. */ ?>
      <?php if ($hatKonflikte && $raumId !== null): ?>
      <div class="hinweis">
        Es wurde nichts gespeichert. Wer die Änderung trotzdem braucht, muss zuerst die
        betroffenen Buchungen anpassen oder löschen:
        <a href="<?php echo h(url('buchungen', array('raum_id' => (int) $raumId))); ?>">Belegung dieses Raums anzeigen</a>.
      </div>
      <?php endif; ?>

      <form method="post" action="raum_bearbeiten.php<?php echo $istBearbeiten ? '?id=' . (int) $raumId : ''; ?>">
        <?php csrf_feld(); ?>
        <label for="name">Name</label>
        <input type="text" id="name" name="name" maxlength="50" value="<?php echo h($name); ?>" required>

        <label for="arbeitsplaetze">Arbeitsplätze</label>
        <input type="number" id="arbeitsplaetze" name="arbeitsplaetze" min="1"
               value="<?php echo h($arbeitsplaetze); ?>" required>

        <fieldset>
          <legend>Softwarepakete</legend>
          <div class="checkbox-liste">
            <?php foreach ($alleSoftware as $software): ?>
            <label class="checkbox-item">
              <input type="checkbox" name="software[]" value="<?php echo (int) $software['id']; ?>"
                <?php echo in_array((int) $software['id'], $ausgewaehlteSoftwareIds, true) ? 'checked' : ''; ?>>
              <?php echo h($software['name']); ?>
            </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <?php if ($istAdmin): ?>
        <fieldset>
          <legend>Bearbeiter</legend>
          <div class="checkbox-liste">
            <?php foreach ($alleBenutzer as $benutzer): ?>
            <label class="checkbox-item">
              <input type="checkbox" name="bearbeiter[]" value="<?php echo (int) $benutzer['id']; ?>"
                <?php echo in_array((int) $benutzer['id'], $ausgewaehlteBearbeiterIds, true) ? 'checked' : ''; ?>>
              <?php echo h($benutzer['name']); ?>
            </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <?php endif; ?>

        <div class="knopf-reihe">
          <button type="submit" class="knopf">Speichern</button>
          <a class="link-abbrechen" href="<?php echo h(url('raeume')); ?>">Abbrechen</a>
        </div>
      </form>

      <p class="regel-hinweis">
        Weniger Arbeitsplätze oder entfernte Software können bestehende Buchungen ungültig
        machen: ein Kurs braucht einen Raum mit genug Plätzen und mit seiner Software.
        Deshalb wird die Änderung abgelehnt, solange noch ein kommender Termin in diesem
        Raum davon betroffen wäre. Vergangene Buchungen bleiben als Historie unberührt.
      </p>
    </div>
  </div>

</body>
</html>
