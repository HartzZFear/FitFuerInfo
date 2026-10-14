<?php
/**
 * Startseite: leitet nur weiter.
 *
 * Nicht angemeldet -> Login, angemeldet -> Belegungskalender. Ob das Konto
 * inzwischen gesperrt wurde, prueft erfordere_login() auf der Zielseite.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

if (ist_eingeloggt()) {
    header('Location: ' . BASE_URL . '/src/web/pages/belegung.php');
} else {
    header('Location: ' . BASE_URL . '/src/web/pages/login.php');
}
exit;
