<?php
/**
 * Allgemeine Helfer, die nicht zu einem Fachbereich gehoeren.
 *
 * Voraussetzung: config/config.php und config/app.php sind geladen (passiert
 * ueber lib/db.php), weil die Funktionen BASE_URL und ASSET_VERSION verwenden.
 *
 * Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.
 */

/**
 * Baut die URL einer Datei in assets/ mit angehaengter Versionsnummer.
 *
 * Der Browser darf CSS und JS zwischenspeichern. Wird ASSET_VERSION in
 * config/app.php erhoeht, aendert sich die URL - der Browser laedt die Datei
 * dann neu, statt eine veraltete Fassung aus dem Cache zu nehmen.
 *
 * @param  string $pfad Pfad unterhalb von assets/, z. B. 'css/style.css'
 * @return string       BASE_URL . '/assets/css/style.css?v=1'
 */
function asset_url($pfad)
{
    return BASE_URL . '/assets/' . $pfad . '?v=' . ASSET_VERSION;
}

/**
 * Baut den Link auf eine Seite. Alle Seiten laufen ueber index.php.
 *
 * Beispiel: url('kurs_bearbeiten', array('id' => 5))
 *        -> BASE_URL/index.php?seite=kurs_bearbeiten&id=5
 *
 * Die Rueckgabe ist nicht fuer HTML maskiert: im HTML mit h() ausgeben
 * (macht aus & korrekt &amp;), in header('Location: ...') ohne h().
 *
 * @param  string $seite     Seitenname aus ERLAUBTE_SEITEN, z. B. 'kurse'
 * @param  array  $parameter weitere GET-Parameter
 * @return string
 */
function url($seite, $parameter = array())
{
    $url = BASE_URL . '/index.php?seite=' . urlencode($seite);
    if (!empty($parameter)) {
        $url .= '&' . http_build_query($parameter, '', '&');
    }
    return $url;
}

/**
 * Verstecktes Feld mit dem Seitennamen fuer Formulare mit method="get".
 *
 * Grund: Bei GET-Formularen ersetzt der Browser den Teil nach "?" in der
 * action durch die Formularfelder - ?seite=... aus url() ginge verloren.
 * Als eigenes Feld wird der Seitenname mitgeschickt.
 *
 * @param  string $seite
 * @return string HTML
 */
function seite_feld($seite)
{
    return '<input type="hidden" name="seite" value="' . h($seite) . '">';
}

/**
 * Die Tabs der Navigation (Kopfzeile und Reiter der Formularseiten).
 * Der Tab "Benutzer" erscheint nur fuer den Admin.
 *
 * @return array Seitenname => Beschriftung
 */
function navigation_tabs()
{
    $tabs = array(
        'kurse'    => 'Kurse',
        'raeume'   => 'Räume',
        'belegung' => 'Belegung',
    );
    if (ist_admin()) {
        $tabs['benutzer'] = 'Benutzer';
    }
    return $tabs;
}

/**
 * Fuehrt eine Seite aus pages/ aus und gibt sie mit templates/header.php und
 * templates/footer.php aus.
 *
 * Die Seite laeuft zuerst in einen Puffer (ob_start). So kann sie erst ihre
 * Logik ausfuehren, $seitentitel usw. setzen oder per header('Location: ...')
 * weiterleiten, bevor irgendetwas an den Browser geht.
 *
 * Die Variablen unten sind Vorgaben; die Seite ueberschreibt, was sie braucht.
 * Weil Seite und Templates innerhalb dieser Funktion eingebunden werden,
 * sehen die Templates genau diese Variablen.
 *
 * @param string $seite   Name der Datei in pages/ (ohne .php), vorher geprueft
 * @param array  $hinweis Daten fuer Hinweisseiten (z. B. die Fehlermeldung)
 */
function seite_ausgeben($seite, $hinweis = array())
{
    $seitentitel   = '';
    $aktiverTab    = '';
    $seitenCss     = array();
    $seitenJs      = array();
    $ohneKopfzeile = false;

    ob_start();
    include __DIR__ . '/../pages/' . $seite . '.php';
    $inhalt = ob_get_clean();

    include __DIR__ . '/../templates/header.php';
    echo $inhalt;
    include __DIR__ . '/../templates/footer.php';
}

/**
 * Bricht die laufende Seite ab und zeigt stattdessen eine Hinweisseite
 * (zugriff_verweigert, anfrage_abgelehnt, freischaltcode). Wird von
 * Funktionen in lib/ aufgerufen, die mitten in einer Seite feststellen,
 * dass es nicht weitergehen darf.
 *
 * Ablauf: Was die Seite bisher ausgegeben hat, liegt noch im Puffer von
 * seite_ausgeben() und ist nicht beim Browser. ob_end_clean() wirft es weg,
 * dann wird die Hinweisseite ganz normal mit Kopf und Fuss ausgegeben, und
 * exit beendet das Skript - der Rest der alten Seite laeuft nicht mehr.
 *
 * @param string $seite   Name der Hinweisseite in pages/
 * @param array  $hinweis Daten fuer die Hinweisseite, z. B. die Fehlermeldung
 */
function seite_wechseln($seite, $hinweis = array())
{
    ob_end_clean();
    seite_ausgeben($seite, $hinweis);
    exit;
}

/**
 * Zeigt statt der aktuellen Seite die Hinweisseite "Zugriff verweigert"
 * (pages/zugriff_verweigert.php) mit einer verstaendlichen Fehlermeldung
 * und einem Link zurueck zur passenden Liste, und beendet danach das
 * Skript. Wird verwendet, wenn eine serverseitige Rechtepruefung
 * fehlschlaegt (z. B. Direktaufruf einer fremden Kurs-ID).
 *
 * Die Funktion wird aus allen Bereichen aufgerufen. Damit der Zurueck-Link
 * dorthin fuehrt, wo der Benutzer hergekommen ist, geben die Buchungs-,
 * Raum- und Benutzerseiten ihr Ziel selbst mit; ohne Angabe bleibt es bei
 * der Kursliste.
 *
 * @param string $nachricht
 * @param string $zurueckSeite Ziel des Zurueck-Links (Seitenname, z. B. 'raeume')
 * @param string $zurueckText  Beschriftung des Zurueck-Links
 */
function zugriff_verweigert_seite($nachricht, $zurueckSeite = 'kurse', $zurueckText = 'Zurück zu den Kursen')
{
    seite_wechseln('zugriff_verweigert', array(
        'nachricht'     => $nachricht,
        'zurueck_seite' => $zurueckSeite,
        'zurueck_text'  => $zurueckText,
    ));
}

/**
 * Liest die angekreuzten IDs einer Checkbox-Liste aus dem Formular,
 * z. B. software[] -> formular_ids('software'). Jeder Wert wird zur Zahl;
 * fehlt das Feld oder ist es keine Liste, kommt ein leeres Array zurueck.
 *
 * @param  string $feld Name des Feldes ohne []
 * @return int[]
 */
function formular_ids($feld)
{
    $ids = array();
    if (isset($_POST[$feld]) && is_array($_POST[$feld])) {
        foreach ($_POST[$feld] as $wert) {
            $ids[] = (int) $wert;
        }
    }
    return $ids;
}

/**
 * UI-Baustein: Liste von Fehlermeldungen im roten Kasten (.fehler).
 * Ohne Fehler kommt ein leerer String zurueck, die Seite braucht also kein
 * eigenes if.
 *
 * @param  string[] $fehler
 * @return string HTML
 */
function fehler_liste($fehler)
{
    if (empty($fehler)) {
        return '';
    }

    $html = '<div class="fehler"><ul>';
    foreach ($fehler as $text) {
        $html .= '<li>' . h($text) . '</li>';
    }
    return $html . '</ul></div>';
}

/**
 * UI-Baustein: eine einzelne Meldung in einem Kasten.
 *
 * @param  string $text
 * @param  string $klasse CSS-Klasse des Kastens: 'fehler' (rot) oder 'meldung'
 * @return string HTML
 */
function meldung($text, $klasse = 'fehler')
{
    return '<div class="' . h($klasse) . '">' . h($text) . '</div>';
}

/**
 * UI-Baustein: Knopfzeile unter einem Formular - Absende-Knopf und Link
 * "Abbrechen" zurueck zur Liste.
 *
 * @param  string $knopfText      z. B. 'Speichern'
 * @param  string $abbrechenSeite Seitenname fuer "Abbrechen", z. B. 'kurse'
 * @param  bool   $loeschen       true = roter Loesch-Knopf (.knopf-loeschen)
 * @return string HTML
 */
function knopf_reihe($knopfText, $abbrechenSeite, $loeschen = false)
{
    $klasse = $loeschen ? 'knopf knopf-loeschen' : 'knopf';

    return '<div class="knopf-reihe">'
        . '<button type="submit" class="' . $klasse . '">' . h($knopfText) . '</button>'
        . '<a class="link-abbrechen" href="' . h(url($abbrechenSeite)) . '">Abbrechen</a>'
        . '</div>';
}

/**
 * UI-Baustein: runder "+"-Knopf unten in der Sidebar der Listenseiten.
 *
 * @param  string $seite        Formularseite zum Anlegen, z. B. 'kurs_bearbeiten'
 * @param  string $beschriftung Text fuer Screenreader, z. B. 'Kurs hinzufügen'
 * @return string HTML
 */
function knopf_hinzufuegen($seite, $beschriftung)
{
    return '<a href="' . h(url($seite)) . '" class="btn-add" aria-label="' . h($beschriftung) . '">+</a>';
}
