<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// freischaltcode - zeigt einen frisch erzeugten Freischaltcode genau einmal.
// Wird nur ueber seite_wechseln() aus benutzer_code_anzeigen_falls_vorhanden()
// (lib/benutzer_logik.php) angezeigt, deshalb nicht in ERLAUBTE_SEITEN.
// Erreichbar nur fuer den Admin, weil benutzer.php erfordere_admin() aufruft.
//
// $hinweis['name']        Benutzername
// $hinweis['code']        der Code im Klartext
// $hinweis['gueltig_bis'] fertig formatiert, z. B. '22.10.2026, 17:30 Uhr'
// $hinweis['link']        vollstaendige Adresse von passwort_setzen

$seitentitel   = 'Freischaltcode';
$seitenCss     = array('formular.css');
$ohneKopfzeile = true;
?>
<?php formular_kopf($seitentitel); ?>
  <div class="inhalt">

<?php formular_reiter('benutzer'); ?>

    <?php session_fehler_anzeigen(); ?>

    <div class="karte">
      <h2 class="karte-titel">Code für <?php echo h($hinweis['name']); ?>:</h2>
      <div class="code"><?php echo h($hinweis['code']); ?></div>
      <p class="karte-text">gültig bis <?php echo h($hinweis['gueltig_bis']); ?></p>

      <p class="einloesen">
        Einlösen unter:<br>
        <a href="<?php echo h($hinweis['link']); ?>"><?php echo h($hinweis['link']); ?></a>
      </p>

      <div class="warnung">
        Geben Sie den Code persönlich weiter. Er wird nur dieses eine Mal angezeigt
        und ist danach nirgends mehr abrufbar. Ein bestehendes Passwort bleibt gültig,
        bis der Code eingelöst wird.
      </div>

      <a class="link-zurueck" href="<?php echo h(url('benutzer')); ?>">Zurück zur Benutzerliste</a>
    </div>
  </div>
