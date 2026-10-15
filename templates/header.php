<?php if (!defined('FFI_START')) { exit; } ?>
<?php
/**
 * Seitenanfang: <head> und - ausser bei $ohneKopfzeile - die Kopfzeile mit
 * Tabs, Wellen-Banner und Hilfe-Knopf.
 *
 * Eingebunden von seite_ausgeben() (lib/funktionen.php). Die Variablen setzt
 * die Seite selbst, Vorgaben stehen in seite_ausgeben():
 *   $seite         Seitenname -> body-Klasse "seite-<name>" fuer das CSS
 *   $seitentitel   Text im <title>
 *   $seitenCss     eigene CSS-Dateien der Seite, z. B. array('kurse.css')
 *   $aktiverTab    kurse, raeume, belegung, benutzer, profil oder hilfe
 *   $ohneKopfzeile true bei Login, Passwort setzen, Formular-/Hinweisseiten
 *
 * Unter 1024 px darf die Kopfzeile umbrechen (Logo und Knoepfe oben, Tabs
 * darunter) und scrollt dann mit der Seite - siehe style.css.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo h($seitentitel); ?> – FitFürInfo</title>
<link rel="stylesheet" href="<?php echo h(asset_url('css/style.css')); ?>">
<?php foreach ($seitenCss as $datei): ?>
<link rel="stylesheet" href="<?php echo h(asset_url('css/' . $datei)); ?>">
<?php endforeach; ?>
</head>
<body class="seite-<?php echo h($seite); ?>">
<?php if (!$ohneKopfzeile): ?>
<?php $tabs = navigation_tabs(); ?>

  <header class="header-bar">
    <div class="header-logo">
      <img src="<?php echo h(BASE_URL . '/assets/imgs/logo.png'); ?>" alt="">
      <span><span class="fit">FitFür</span><span class="info">Info</span></span>
    </div>

    <nav class="tab-group<?php echo count($tabs) > 3 ? ' vier' : ''; ?>">
      <?php foreach ($tabs as $tab => $beschriftung): ?>
      <a href="<?php echo h(url($tab)); ?>" class="tab<?php echo $aktiverTab === $tab ? ' active' : ''; ?>"<?php echo $aktiverTab === $tab ? ' aria-current="page"' : ''; ?>><?php echo h($beschriftung); ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="header-actions">
      <a href="<?php echo h(url('profil')); ?>" class="btn-header profile<?php echo $aktiverTab === 'profil' ? ' active' : ''; ?>"<?php echo $aktiverTab === 'profil' ? ' aria-current="page"' : ''; ?>>Profile</a>
      <a href="<?php echo h(url('logout')); ?>" class="btn-header logout">Log Out</a>
    </div>
  </header>

  <div class="banner-spacer"></div>

  <!-- Dekorativer Wellen-Streifen, identisch zur Login-Seite -->
  <div class="top-banner" id="waveBanner">
<?php welle_svg(); ?>
    <div class="help-region">
      <a href="<?php echo h(url('hilfe')); ?>" class="help-icon<?php echo $aktiverTab === 'hilfe' ? ' active' : ''; ?>" aria-label="Hilfe" title="Hilfe"<?php echo $aktiverTab === 'hilfe' ? ' aria-current="page"' : ''; ?>>?</a>
      <div class="lang-select" aria-hidden="true">🇩🇪 DE ▾</div>
    </div>
  </div>
<?php endif; ?>
