<?php
/**
 * Abmelden.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

abmelden();

header('Location: login.php');
exit;
