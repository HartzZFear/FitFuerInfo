<?php if (!defined('FFI_START')) { exit; } ?>
<?php
// anfrage_abgelehnt - Hinweis, wenn das CSRF-Token eines Formulars fehlt
// oder nicht passt. Wird nur ueber seite_wechseln() aus csrf_pruefen()
// (lib/auth.php) angezeigt, deshalb nicht in ERLAUBTE_SEITEN.
//
// $hinweis['neu_laden']: Adresse der abgelehnten Seite zum Neuladen

http_response_code(400);

$seitentitel   = 'Anfrage abgelehnt';
$seitenCss     = array('formular.css');
$ohneKopfzeile = true;
?>
<?php formular_kopf('Kurs- und Raumverwaltung'); ?>
  <div class="inhalt">
    <div class="karte">
      <h2 class="karte-titel">Anfrage abgelehnt</h2>
      <p class="karte-text">
        Das Formular konnte nicht verarbeitet werden, weil sein Sicherheitsschlüssel fehlt
        oder nicht passt. Das passiert zum Beispiel, wenn die Seite sehr lange offen war
        oder Sie sich zwischendurch ab- und wieder angemeldet haben.
        Es wurde nichts gespeichert. Bitte die Seite neu laden und die Eingabe wiederholen.
      </p>
      <a class="link-zurueck" href="<?php echo h($hinweis['neu_laden']); ?>">Seite neu laden</a>
    </div>
  </div>
