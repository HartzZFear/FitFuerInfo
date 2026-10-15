<?php
/**
 * Gemeinsame Funktionen fuer die Kursverwaltung.
 *
 * Eigentuemer eines Kurses sind der Ersteller (kurs.ersteller_id) UND alle
 * Eintraege in kurs_eigentuemer. Bearbeiten/Loeschen duerfen nur Eigentuemer
 * oder der Admin - diese Pruefung gehoert IMMER serverseitig hierher, nicht
 * nur in die Anzeige der Buttons.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/funktionen.php';

/**
 * Liefert die Benutzer-IDs aller Eigentuemer eines Kurses.
 *
 * @param  int $kursId
 * @return int[]
 */
function kurs_eigentuemer_ids($kursId)
{
    $ids = array();

    $ersteller = abfrage('SELECT ersteller_id FROM kurs WHERE id = ?', array($kursId))->fetchColumn();
    if ($ersteller !== false) {
        $ids[] = (int) $ersteller;
    }

    $zeilen = abfrage('SELECT benutzer_id FROM kurs_eigentuemer WHERE kurs_id = ?', array($kursId))->fetchAll();
    foreach ($zeilen as $zeile) {
        $ids[] = (int) $zeile['benutzer_id'];
    }

    return array_unique($ids);
}

/**
 * Darf der angegebene Benutzer den Kurs bearbeiten oder loeschen?
 * Der Admin darf immer, sonst nur ein Eigentuemer.
 *
 * @param  int  $kursId
 * @param  int  $benutzerId
 * @param  bool $istAdmin
 * @return bool
 */
function kurs_darf_verwalten($kursId, $benutzerId, $istAdmin)
{
    if ($istAdmin) {
        return true;
    }

    return in_array((int) $benutzerId, kurs_eigentuemer_ids($kursId), true);
}

/**
 * Zeigt eine Seite im Design der Anwendung mit einer verstaendlichen
 * Fehlermeldung und einem Link zurueck zur passenden Liste, und beendet
 * danach das Skript. Wird verwendet, wenn eine serverseitige Rechtepruefung
 * fehlschlaegt (z. B. Direktaufruf einer fremden Kurs-ID).
 *
 * Die Funktion wird inzwischen aus allen drei Bereichen aufgerufen. Damit
 * der Zurueck-Link dorthin fuehrt, wo der Benutzer hergekommen ist, geben
 * die Buchungs- und Raumseiten ihr Ziel selbst mit; ohne Angabe bleibt es
 * bei der Kursliste.
 *
 * @param string $nachricht
 * @param string $zurueckUrl  Ziel des Zurueck-Links (Dateiname im selben Ordner,
 *                            ohne Angabe die Kursliste)
 * @param string $zurueckText Beschriftung des Zurueck-Links
 */
function zugriff_verweigert_seite($nachricht, $zurueckUrl = null, $zurueckText = 'Zurück zu den Kursen')
{
    if ($zurueckUrl === null) {
        $zurueckUrl = url('kurse');
    }
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Zugriff verweigert – FitFürInfo</title>
<link rel="stylesheet" href="<?php echo h(asset_url('css/style.css')); ?>">
<link rel="stylesheet" href="<?php echo h(asset_url('css/formular.css')); ?>">
</head>
<body class="seite-zugriff_verweigert">
  <div class="kopf">
    <h1>FitFürInfo</h1>
    <p>Kurs- und Raumverwaltung</p>
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
    <div class="karte">
      <h2 class="karte-titel">Zugriff verweigert</h2>
      <p class="karte-text"><?php echo h($nachricht); ?></p>
      <a class="link-zurueck" href="<?php echo h($zurueckUrl); ?>"><?php echo h($zurueckText); ?></a>
    </div>
  </div>
</body>
</html>
<?php
    exit;
}
