<?php
/**
 * Wiederkehrende Layout-Teile als Funktionen.
 *
 * Wird von index.php eingebunden und gibt dabei nichts aus - erst der
 * Aufruf einer Funktion erzeugt HTML. Verwendet von templates/header.php
 * und von Seiten ohne Kopfzeile (Login, Formular- und Hinweisseiten).
 *
 * Das CSS dazu steht in assets/css/style.css (Banner, Login-Welle) und
 * assets/css/formular.css (.kopf, .reiter-gruppe).
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

/**
 * Gibt die dekorative Welle als SVG aus.
 *
 * Die Farben des Verlaufs entsprechen den CSS-Variablen --teal, --blue und
 * --orange in assets/css/style.css. In SVG-Attributen wie stop-color lassen
 * sich keine CSS-Variablen verwenden, deshalb stehen sie hier als Farbcode.
 *
 * @param string $klasse      CSS-Klasse des SVG (Formularkopf: 'welle')
 * @param bool   $zweiteWelle zweite, halb durchsichtige Welle dahinter
 *                            (Banner und Login ja, Formularkopf nein)
 */
function welle_svg($klasse = '', $zweiteWelle = true)
{
?>
    <svg<?php echo $klasse !== '' ? ' class="' . h($klasse) . '"' : ''; ?> viewBox="0 0 1440 200" preserveAspectRatio="none" aria-hidden="true">
      <defs>
        <linearGradient id="waveGradient" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%"  stop-color="#1a8f9c"/>
          <stop offset="45%" stop-color="#2f6fb0"/>
          <stop offset="100%" stop-color="#e8792e"/>
        </linearGradient>
      </defs>
      <path fill="url(#waveGradient)"
            d="M0,80 C240,160 480,0 720,60 C960,120 1200,20 1440,90 L1440,0 L0,0 Z"/>
<?php if ($zweiteWelle): ?>
      <path fill="url(#waveGradient)" opacity="0.55"
            d="M0,120 C280,60 520,180 780,110 C1040,40 1260,140 1440,100 L1440,0 L0,0 Z"/>
<?php endif; ?>
    </svg>
<?php
}

/**
 * Gibt den Seitenkopf der Formular- und Hinweisseiten aus: Verlauf mit
 * Name der Anwendung, Untertitel und Welle.
 *
 * @param string $untertitel z. B. 'Kurs löschen'
 */
function formular_kopf($untertitel)
{
?>
  <div class="kopf">
    <h1>FitFürInfo</h1>
    <p><?php echo h($untertitel); ?></p>
<?php welle_svg('welle', false); ?>
  </div>
<?php
}

/**
 * Gibt die Reiter der Formularseiten aus (gleiche Tabs wie die Kopfzeile).
 *
 * @param string $aktiverTab z. B. 'raeume'
 */
function formular_reiter($aktiverTab)
{
?>
    <nav class="reiter-gruppe">
<?php foreach (navigation_tabs() as $tab => $beschriftung): ?>
      <a href="<?php echo h(url($tab)); ?>" class="reiter<?php echo $aktiverTab === $tab ? ' active' : ''; ?>"><?php echo h($beschriftung); ?></a>
<?php endforeach; ?>
    </nav>
<?php
}
