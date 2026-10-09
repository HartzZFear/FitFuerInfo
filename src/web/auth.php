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

    // session_regenerate_id() uebernimmt die Session-Daten, also auch das
    // CSRF-Token von der Login-Seite. Fuer die angemeldete Sitzung bewusst
    // ein neues erzeugen lassen (beim naechsten csrf_token()).
    unset($_SESSION['csrf_token']);

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
 *
 * Zusaetzlich wird das Konto bei jedem Seitenaufruf frisch aus der
 * Datenbank gelesen: Sperrt der Admin einen Benutzer, endet dessen
 * laufende Session beim naechsten Klick (Hinweis auf der Login-Seite).
 * Die Rolle in der Session wird dabei ebenfalls nachgezogen, damit ein
 * zum Mitarbeiter gemachter Admin seine Rechte sofort verliert.
 */
function erfordere_login()
{
    if (!ist_eingeloggt()) {
        header('Location: ' . BASE_URL . '/src/web/pages/login.php');
        exit;
    }

    $benutzer = aktueller_benutzer();

    if ($benutzer === null || (int) $benutzer['aktiv'] !== 1) {
        abmelden();
        header('Location: ' . BASE_URL . '/src/web/pages/login.php?gesperrt=1');
        exit;
    }

    $_SESSION['rolle'] = $benutzer['rolle'];
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
 * Die Zufallsbytes kommen aus openssl_random_pseudo_bytes(), weil mt_rand()
 * nicht kryptografisch sicher ist und ein Code sonst vorhersagbar waere.
 * Bytes ab $grenze werden verworfen, damit jedes der 31 Zeichen gleich
 * wahrscheinlich ist (256 ist kein Vielfaches von 31).
 *
 * @return string
 */
function erzeuge_freischaltcode()
{
    $zeichen       = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $anzahlZeichen = strlen($zeichen);
    $grenze        = 256 - (256 % $anzahlZeichen);
    $code          = '';

    while (strlen($code) < 8) {
        $stark = false;
        $bytes = openssl_random_pseudo_bytes(16, $stark);

        if ($bytes === false || !$stark) {
            throw new RuntimeException('Es steht kein sicherer Zufallsgenerator zur Verfügung.');
        }

        for ($i = 0; $i < strlen($bytes) && strlen($code) < 8; $i++) {
            $wert = ord($bytes[$i]);
            if ($wert < $grenze) {
                $code .= $zeichen[$wert % $anzahlZeichen];
            }
        }
    }

    return $code;
}

/**
 * Gibt eine von erfordere_admin() hinterlegte Meldung ($_SESSION['fehler'])
 * einmal aus und loescht sie danach aus der Session. Jede Seite mit
 * Tab-Leiste ruft das oben im Inhaltsbereich auf. Ohne Meldung wird nichts
 * ausgegeben.
 *
 * Die Optik entspricht den .fehler-Kaesten der Formularseiten; die Angaben
 * stehen direkt im style-Attribut, weil nicht jede Seite diese Klasse
 * definiert.
 */
function session_fehler_anzeigen()
{
    session_starten();

    if (!isset($_SESSION['fehler']) || $_SESSION['fehler'] === '') {
        return;
    }

    $meldung = $_SESSION['fehler'];
    unset($_SESSION['fehler']);

    echo '<div class="session-fehler" role="alert" style="margin: 0 0 20px; padding: 10px 14px;'
        . ' border-radius: 8px; background: #fdeceb; border: 1px solid #f0b3ae; color: #a13a2f;'
        . ' font-size: 13px;">' . h($meldung) . '</div>';
}

/**
 * CSRF-Schutz: Liefert das Token der aktuellen Session und erzeugt es beim
 * ersten Aufruf (32 Zufallsbytes, hex-kodiert = 64 Zeichen).
 *
 * Eine fremde Webseite kann den Browser zwar dazu bringen, ein Formular an
 * uns zu schicken (mitsamt Session-Cookie), kennt aber dieses Token nicht.
 *
 * @return string
 */
function csrf_token()
{
    session_starten();

    if (empty($_SESSION['csrf_token'])) {
        $bytes = openssl_random_pseudo_bytes(32, $stark);
        if ($bytes === false || !$stark) {
            throw new RuntimeException('Es steht kein sicherer Zufallsgenerator zur Verfügung.');
        }
        $_SESSION['csrf_token'] = bin2hex($bytes);
    }

    return $_SESSION['csrf_token'];
}

/**
 * Gibt das versteckte Formularfeld mit dem Token aus. Gehoert in JEDES
 * Formular mit method="post".
 */
function csrf_feld()
{
    echo '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

/**
 * Prueft bei einem POST-Aufruf das mitgeschickte Token gegen das der
 * Session. Fehlt es oder passt es nicht, endet die Seite mit HTTP 400 und
 * einer verstaendlichen Meldung - es wird dann nichts verarbeitet.
 *
 * Bei GET tut die Funktion nichts. Deshalb kann (und soll) jede Seite mit
 * POST-Formular sie gleich oben aufrufen, direkt nach der Anmeldepruefung
 * und vor jeder Verarbeitung von $_POST.
 */
function csrf_pruefen()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    session_starten();

    $erwartet = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    $gesendet = isset($_POST['csrf']) ? $_POST['csrf'] : '';

    if (is_string($erwartet) && $erwartet !== '' && is_string($gesendet)
        && hash_equals($erwartet, $gesendet)) {
        return;
    }

    http_response_code(400);
    $neuLaden = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : BASE_URL . '/src/web/';
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Anfrage abgelehnt – FitFürInfo</title>
<style>
  :root {
    --teal: #1a8f9c;
    --blue: #2f6fb0;
    --orange: #e8792e;
    --card-bg: #ffffff;
    --page-bg: #eef3f4;
    --text-dark: #2b3a42;
    --text-muted: #7c8a91;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    min-height: 100vh;
    font-family: "Segoe UI", Roboto, Arial, sans-serif;
    background: var(--page-bg);
  }
  .kopf {
    position: relative;
    background: linear-gradient(135deg, var(--teal), var(--blue));
    color: #fff;
    padding: 40px 20px 70px;
    text-align: center;
  }
  .kopf h1 { margin: 0 0 4px; font-size: 24px; font-weight: 700; }
  .kopf p { margin: 0; opacity: .9; font-size: 14px; }
  .kopf .welle { position: absolute; left: 0; bottom: -1px; width: 100%; height: 60px; display: block; }
  .inhalt { max-width: 520px; margin: 24px auto 40px; padding: 0 16px; }
  .karte {
    background: var(--card-bg);
    border-radius: 14px;
    box-shadow: 0 12px 28px rgba(20, 40, 50, .12);
    padding: 28px 28px 32px;
    text-align: center;
  }
  .karte-titel { margin: 0 0 12px; font-size: 20px; color: var(--text-dark); }
  .karte-text { margin: 0 0 20px; font-size: 14px; color: var(--text-muted); line-height: 1.5; }
  .link-zurueck {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 8px;
    background: linear-gradient(90deg, var(--blue) 0%, var(--teal) 100%);
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
  }
  .link-zurueck:hover { filter: brightness(1.05); }
</style>
</head>
<body>
  <div class="kopf">
    <h1>FitFürInfo</h1>
    <p>Kurs- und Raumverwaltung</p>
    <svg class="welle" viewBox="0 0 1440 200" preserveAspectRatio="none">
      <defs>
        <linearGradient id="waveGradient" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stop-color="#1a8f9c"/>
          <stop offset="45%" stop-color="#2f6fb0"/>
          <stop offset="100%" stop-color="#e8792e"/>
        </linearGradient>
      </defs>
      <path fill="url(#waveGradient)" d="M0,80 C240,160 480,0 720,60 C960,120 1200,20 1440,90 L1440,0 L0,0 Z"/>
    </svg>
  </div>
  <div class="inhalt">
    <div class="karte">
      <h2 class="karte-titel">Anfrage abgelehnt</h2>
      <p class="karte-text">
        Das Formular konnte nicht verarbeitet werden, weil sein Sicherheitsschlüssel fehlt
        oder nicht passt. Das passiert zum Beispiel, wenn die Seite sehr lange offen war
        oder Sie sich zwischendurch ab- und wieder angemeldet haben.
        Es wurde nichts gespeichert. Bitte die Seite neu laden und die Eingabe wiederholen.
      </p>
      <a class="link-zurueck" href="<?php echo h($neuLaden); ?>">Seite neu laden</a>
    </div>
  </div>
</body>
</html>
<?php
    exit;
}
