<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// zugriff_verweigert - Hinweis, wenn eine serverseitige Rechtepruefung
// fehlschlaegt. Wird nur ueber seite_wechseln() aus
// zugriff_verweigert_seite() (lib/funktionen.php) angezeigt, deshalb nicht
// in ERLAUBTE_SEITEN.
//
// $hinweis['nachricht']     Fehlermeldung
// $hinweis['zurueck_seite'] Seitenname fuer den Zurueck-Link
// $hinweis['zurueck_text']  Beschriftung des Zurueck-Links

$seitentitel   = 'Zugriff verweigert';
$seitenCss     = array('formular.css');
$ohneKopfzeile = true;
?>
<?php formular_kopf('Kurs- und Raumverwaltung'); ?>
  <div class="inhalt">
    <div class="karte">
      <h2 class="karte-titel">Zugriff verweigert</h2>
      <p class="karte-text"><?php echo h($hinweis['nachricht']); ?></p>
      <a class="link-zurueck" href="<?php echo h(url($hinweis['zurueck_seite'])); ?>"><?php echo h($hinweis['zurueck_text']); ?></a>
    </div>
  </div>
