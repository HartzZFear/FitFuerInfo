<?php if (!defined('FFI_START')) { exit; } ?>
<?php
/**
 * logout (Aufruf: index.php?seite=logout)
 * Abmelden und zurueck zur Anmeldung. Gibt selbst nichts aus.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

abmelden();

header('Location: ' . url('login'));
exit;
