<?php
/**
 * Zugangssystem: Anmeldung, Sessions, Freischaltcodes.
 *
 * Der Systemverwalter legt Benutzerkonten an, kennt aber nie ein Passwort.
 * Ablauf: Admin erzeugt Freischaltcode -> Mitarbeiter loest den Code in
 * passwort_setzen.php ein und vergibt sein eigenes Passwort -> normaler
 * Login ueber anmelden().
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

require_once __DIR__ . '/db.php';

/**
 * Startet die Session, falls noch keine laeuft.
 */
function session_starten()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/**
 * Prueft Benutzername und Passwort und meldet bei Erfolg an.
 *
 * Gibt bei falschem Benutzernamen, deaktiviertem Konto, fehlendem
 * Passwort (noch kein Code eingeloest) oder falschem Passwort jeweils
 * einfach false zurueck - der Aufrufer erfaehrt nicht, welcher Fall
 * vorlag.
 *
 * @param  string $name
 * @param  string $passwort
 * @return bool
 */
function anmelden($name, $passwort)
{
    session_starten();

    $zeile = abfrage(
        'SELECT id, passwort_hash, rolle, aktiv FROM benutzer WHERE name = ?',
        array($name)
    )->fetch();

    if (!$zeile) {
        return false;
    }
    if ((int) $zeile['aktiv'] !== 1) {
        return false;
    }
    if ($zeile['passwort_hash'] === null) {
        return false;
    }
    if (!password_verify($passwort, $zeile['passwort_hash'])) {
        return false;
    }

    // Neue Session-ID nach erfolgreichem Login (Schutz vor Session Fixation).
    session_regenerate_id(true);
    $_SESSION['benutzer_id'] = (int) $zeile['id'];
    $_SESSION['rolle']       = $zeile['rolle'];

    return true;
}

/**
 * Meldet den aktuellen Benutzer ab und loescht die Session vollstaendig.
 */
function abmelden()
{
    session_starten();

    $_SESSION = array();

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * @return bool
 */
function ist_eingeloggt()
{
    session_starten();
    return isset($_SESSION['benutzer_id']);
}

/**
 * @return bool
 */
function ist_admin()
{
    session_starten();
    return isset($_SESSION['rolle']) && $_SESSION['rolle'] === 'admin';
}

/**
 * @return int|null
 */
function benutzer_id()
{
    session_starten();
    return isset($_SESSION['benutzer_id']) ? $_SESSION['benutzer_id'] : null;
}

/**
 * Laedt den Datensatz des aktuell angemeldeten Benutzers frisch aus der
 * Datenbank (nicht aus der Session), damit z. B. ein zwischenzeitliches
 * Deaktivieren durch den Admin sofort sichtbar ist.
 *
 * @return array|null
 */
function aktueller_benutzer()
{
    session_starten();

    if (!isset($_SESSION['benutzer_id'])) {
        return null;
    }

    $zeile = abfrage(
        'SELECT id, name, email, rolle, aktiv FROM benutzer WHERE id = ?',
        array($_SESSION['benutzer_id'])
    )->fetch();

    return $zeile ? $zeile : null;
}

/**
 * Leitet auf die Login-Seite um, wenn niemand angemeldet ist.
 */
function erfordere_login()
{
    if (!ist_eingeloggt()) {
        header('Location: ' . BASE_URL . '/src/web/pages/login.php');
        exit;
    }
}

/**
 * Leitet mit einer Fehlermeldung zurueck, wenn kein Admin angemeldet ist.
 */
function erfordere_admin()
{
    erfordere_login();

    if (!ist_admin()) {
        $_SESSION['fehler'] = 'Diese Seite ist nur für den Systemverwalter zugänglich.';
        header('Location: ' . BASE_URL . '/src/web/pages/kurse.php');
        exit;
    }
}

/**
 * Prueft ein Passwort gegen die Regeln aus der Aufgabenstellung:
 * mindestens PW_MIN_LAENGE Zeichen, mindestens ein Kleinbuchstabe,
 * mindestens eine Ziffer.
 *
 * @param  string $pw
 * @return array leer bei OK, sonst deutsche Fehlermeldungen
 */
function pruefe_passwortregeln($pw)
{
    $fehler = array();

    if (strlen($pw) < PW_MIN_LAENGE) {
        $fehler[] = 'Das Passwort muss mindestens ' . PW_MIN_LAENGE . ' Zeichen lang sein.';
    }
    if (!preg_match('/[a-z]/', $pw)) {
        $fehler[] = 'Das Passwort muss mindestens einen Kleinbuchstaben enthalten.';
    }
    if (!preg_match('/[0-9]/', $pw)) {
        $fehler[] = 'Das Passwort muss mindestens eine Ziffer enthalten.';
    }

    return $fehler;
}

/**
 * Loest einen Freischaltcode ein und setzt damit erstmalig ein Passwort.
 *
 * Prueft NICHT die Passwortregeln - das muss der Aufrufer vorher mit
 * pruefe_passwortregeln() erledigen.
 *
 * @param  string $name
 * @param  string $code
 * @param  string $neuesPasswort
 * @return bool
 */
function code_einloesen($name, $code, $neuesPasswort)
{
    $zeile = abfrage(
        'SELECT id, freischaltcode, code_gueltig_bis FROM benutzer WHERE name = ?',
        array($name)
    )->fetch();

    if (!$zeile) {
        return false;
    }
    if ($zeile['freischaltcode'] === null || $code === '') {
        return false;
    }
    if (!hash_equals($zeile['freischaltcode'], $code)) {
        return false;
    }
    if ($zeile['code_gueltig_bis'] === null || strtotime($zeile['code_gueltig_bis']) < time()) {
        return false;
    }

    $hash = password_hash($neuesPasswort, PASSWORD_DEFAULT);

    abfrage(
        'UPDATE benutzer
         SET passwort_hash = ?, freischaltcode = NULL, code_gueltig_bis = NULL
         WHERE id = ?',
        array($hash, $zeile['id'])
    );

    return true;
}

/**
 * Erzeugt einen 8-stelligen Freischaltcode aus Großbuchstaben und Ziffern,
 * ohne leicht verwechselbare Zeichen (kein 0/O, kein 1/I/L).
 *
 * @return string
 */
function erzeuge_freischaltcode()
{
    $zeichen = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $letzterIndex = strlen($zeichen) - 1;
    $code = '';

    for ($i = 0; $i < 8; $i++) {
        $code .= $zeichen[mt_rand(0, $letzterIndex)];
    }

    return $code;
}
