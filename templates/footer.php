<?php if (!defined('FFI_START')) { exit; } ?>
<?php
/**
 * Seitenende: gemeinsames Skript, Skripte der Seite ($seitenJs, z. B.
 * array('belegung.js')) und die schliessenden Tags.
 *
 * Die Skripte stehen am Ende, damit die Elemente der Seite beim Ausfuehren
 * schon existieren.
 */
?>
  <script src="<?php echo h(asset_url('js/main.js')); ?>"></script>
<?php foreach ($seitenJs as $datei): ?>
  <script src="<?php echo h(asset_url('js/' . $datei)); ?>"></script>
<?php endforeach; ?>
</body>
</html>
