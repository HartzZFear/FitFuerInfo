<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// fehler - Hinweis bei einem unerwarteten technischen Fehler (z. B. die
// Datenbank meldet einen Fehler, den keine Funktion abfaengt). Wird nur ueber
// fehler_seite_zeigen() (lib/funktionen.php) angezeigt, deshalb nicht in
// ERLAUBTE_SEITEN.
//
// Besucher sehen nur einen verstaendlichen Text - keine Dateipfade und kein
// SQL. Die technischen Details stehen nur bei DEBUG = true darunter.
//
// $hinweis['details'] Fehlertext mit Stack-Trace, '' wenn DEBUG aus ist

http_response_code(500);

$seitentitel   = 'Fehler';
$seitenCss     = array('formular.css');
$ohneKopfzeile = true;
?>
<?php formular_kopf('Kurs- und Raumverwaltung'); ?>

  <div class="inhalt">
    <div class="karte">
      <h2 class="karte-titel">Es ist ein Fehler aufgetreten</h2>
      <p class="karte-text">
        Die Anfrage konnte wegen eines technischen Problems nicht ausgeführt werden.
        Bitte versuchen Sie es noch einmal. Tritt der Fehler wieder auf, wenden Sie sich
        bitte an den Systemverwalter.
      </p>
<?php if ($hinweis['details'] !== ''): ?>
      <p class="karte-text">Details (nur sichtbar, weil DEBUG eingeschaltet ist):</p>
      <pre class="fehler-details"><?php echo h($hinweis['details']); ?></pre>
<?php endif; ?>
      <a class="link-zurueck" href="<?php echo h(url('belegung')); ?>">Zur Startseite</a>
    </div>
  </div>
