<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// nicht_gefunden - Ziel von index.php, wenn ?seite=... nicht in der
// Whitelist steht. Bewusst NICHT in ERLAUBTE_SEITEN: Diese Seite wird nie
// ueber ihren Namen aufgerufen.

http_response_code(404);

$seitentitel   = 'Seite nicht gefunden';
$seitenCss     = array('formular.css');
$ohneKopfzeile = true;
?>
<?php formular_kopf('Kurs- und Raumverwaltung'); ?>

  <div class="inhalt">
    <div class="karte">
      <h2 class="karte-titel">Seite nicht gefunden</h2>
      <p class="karte-text">Diese Seite gibt es nicht. Bitte über die Startseite weiter navigieren.</p>
      <a class="link-zurueck" href="<?php echo h(url('belegung')); ?>">Zur Startseite</a>
    </div>
  </div>
