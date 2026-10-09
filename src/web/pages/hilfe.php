<?php
// hilfe.php
// Kurze Bedienungshilfe fuer alle angemeldeten Benutzer. Gleicher visueller
// Stil wie kurse.php (Kopfzeile mit Tabs, Wellen-Banner, weisse Cards).
//
// Die Texte beschreiben nur, was der Code wirklich tut. Zahlen, die aus
// Konstanten kommen (Buchungszeiten, Passwortlaenge, Code-Gueltigkeit),
// werden direkt aus diesen Konstanten ausgegeben, damit die Hilfe bei einer
// Aenderung nicht veraltet.
//
// Bewusst PHP-5.6-Syntax, damit es auf dem Schulrechner laeuft.

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../layout.php';
require_once __DIR__ . '/../buchung_logik.php';
require_once __DIR__ . '/../benutzer_logik.php';

erfordere_login();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hilfe – FitFürInfo</title>
<style>
  :root {
    --teal: #1a8f9c;
    --blue: #2f6fb0;
    --orange: #e8792e;
    --card-bg: #ffffff;
    --page-bg: #eef3f4;
    --text-dark: #2b3a42;
    --text-muted: #7c8a91;
    --border: #dfe6e8;
  }

  * { box-sizing: border-box; }

  body {
    margin: 0;
    min-height: 100vh;
    font-family: "Segoe UI", Roboto, Arial, sans-serif;
    background: var(--page-bg);
    color: var(--text-dark);
  }

  .main-area {
    max-width: 960px;
    width: 92%;
    margin: 20px auto 40px;
    padding: 0 16px 16px;
  }

  /* ---- Inhaltsverzeichnis ---- */
  .inhalt-liste {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0 0 20px;
    padding: 0;
    list-style: none;
  }

  .inhalt-liste a {
    display: inline-block;
    padding: 7px 14px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 600;
    color: var(--blue);
    background: #f2f7fb;
    border: 1px solid #d7e6f2;
    text-decoration: none;
  }
  .inhalt-liste a:hover { background: #e6f0f9; }

  .karte {
    background: var(--card-bg);
    border-radius: 14px;
    box-shadow: 0 12px 28px rgba(20, 40, 50, 0.1);
    padding: 22px 26px 24px;
    margin-bottom: 20px;
    scroll-margin-top: 96px; /* Sprungziel nicht unter der fixierten Kopfzeile */
  }

  .karte h2 {
    margin: 0 0 12px;
    font-size: 19px;
    font-weight: 700;
  }

  .karte h2 .nr {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    margin-right: 8px;
    border-radius: 50%;
    font-size: 13px;
    color: #ffffff;
    background: linear-gradient(90deg, var(--blue) 0%, var(--teal) 100%);
    vertical-align: 2px;
  }

  .karte h3 {
    margin: 18px 0 6px;
    font-size: 14px;
    color: var(--blue);
  }

  .karte p,
  .karte li {
    font-size: 14px;
    line-height: 1.55;
  }

  .karte p { margin: 0 0 10px; }
  .karte ul,
  .karte ol { margin: 0 0 10px; padding-left: 22px; }
  .karte li { margin-bottom: 4px; }

  .rechte {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
    margin: 4px 0 10px;
  }

  .rechte th,
  .rechte td {
    text-align: left;
    vertical-align: top;
    padding: 8px 10px;
    border-bottom: 1px solid var(--border);
  }

  .rechte th {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: var(--blue);
  }

  .rechte td:first-child { font-weight: 600; white-space: nowrap; }

  .hinweis {
    margin: 12px 0 0;
    padding: 10px 12px;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.45;
    background: #eef6f7;
    border: 1px solid #cfe6e9;
    color: #3a6b72;
  }

  .karte a { color: var(--blue); }

  @media (max-width: 600px) {
    .rechte td:first-child { white-space: normal; }
  }
</style>
</head>
<body>

  <?php kopfzeile('hilfe'); ?>

  <div class="main-area">

    <?php session_fehler_anzeigen(); ?>

    <ul class="inhalt-liste">
      <li><a href="#rollen">Rollen</a></li>
      <li><a href="#kurse">Kurse</a></li>
      <li><a href="#buchen">Raum buchen</a></li>
      <li><a href="#kalender">Kalender</a></li>
      <li><a href="#login">Erstes Login</a></li>
      <li><a href="#passwort">Passwortregeln</a></li>
    </ul>

    <section class="karte" id="rollen">
      <h2><span class="nr">1</span>Rollen: Wer darf was?</h2>
      <p>
        Es gibt zwei Rollen: <strong>Mitarbeiter</strong> und <strong>Admin</strong>
        (Systemverwalter). Ansehen dürfen alle angemeldeten Benutzer alles – Kurse, Räume
        und die Belegung. Unterschiede gibt es nur beim Ändern:
      </p>
      <table class="rechte">
        <thead>
          <tr><th>Bereich</th><th>Mitarbeiter</th><th>Admin</th></tr>
        </thead>
        <tbody>
          <tr>
            <td>Kurse</td>
            <td>neue Kurse anlegen; eigene Kurse bearbeiten und löschen</td>
            <td>alle Kurse bearbeiten und löschen; weitere Eigentümer eintragen</td>
          </tr>
          <tr>
            <td>Räume</td>
            <td>bei Räumen, für die man als Bearbeiter eingetragen ist: Name, Arbeitsplätze und Software ändern</td>
            <td>Räume anlegen, bearbeiten (Name, Arbeitsplätze, Software), löschen und Bearbeiter festlegen</td>
          </tr>
          <tr>
            <td>Buchungen</td>
            <td>für eigene Kurse buchen; eigene Buchungen ändern und löschen</td>
            <td>für alle Kurse buchen; alle Buchungen ändern und löschen</td>
          </tr>
          <tr>
            <td>Vergangene Buchungen</td>
            <td>nicht mehr änderbar und nicht löschbar</td>
            <td>nicht mehr änderbar, aber löschbar</td>
          </tr>
          <tr>
            <td>Benutzer</td>
            <td>nur das eigene Passwort im <a href="profil.php">Profil</a></td>
            <td>Benutzer anlegen, bearbeiten, sperren/entsperren, Freischaltcodes erzeugen (Tab „Benutzer“)</td>
          </tr>
        </tbody>
      </table>
      <p>
        Wer einen Raum bearbeiten darf, hat dadurch <strong>keine</strong> Rechte an den
        Buchungen in diesem Raum. Benutzer werden nicht gelöscht, sondern gesperrt; ein
        gesperrter Benutzer wird beim nächsten Klick abgemeldet. Der letzte aktive Admin
        kann weder gesperrt noch zum Mitarbeiter gemacht werden, und niemand kann sich
        selbst sperren.
      </p>
    </section>

    <section class="karte" id="kurse">
      <h2><span class="nr">2</span>Kurse anlegen und Eigentümer</h2>
      <ol>
        <li>Im Tab <a href="kurse.php">Kurse</a> rechts in der Seitenleiste auf <strong>+</strong> klicken.</li>
        <li><strong>Titel</strong> und <strong>maximale Teilnehmerzahl</strong> (ganze Zahl, mindestens 1)
          eintragen. Die Beschreibung ist freiwillig.</li>
        <li>Die <strong>Software</strong> ankreuzen, die der Kurs braucht. Gebucht werden kann der Kurs
          später nur in Räumen, die all diese Software haben.</li>
        <li>Speichern.</li>
      </ol>
      <p>
        <strong>Eigentümer</strong> eines Kurses sind der, der ihn angelegt hat, und alle Mitarbeiter,
        die der Admin zusätzlich als Eigentümer einträgt. Nur Eigentümer und der Admin sehen bei
        einem Kurs die Knöpfe „bearbeiten“ und „löschen“ und dürfen für ihn Räume buchen.
        Mit „Nur eigene“ in der Seitenleiste zeigt die Liste nur Ihre Kurse.
      </p>
      <p class="hinweis">
        Ein Kurs, für den es noch Buchungen gibt, lässt sich nicht löschen. Die Seite sagt dann,
        wie viele Buchungen betroffen sind – erst diese löschen.
      </p>
    </section>

    <section class="karte" id="buchen">
      <h2><span class="nr">3</span>Raum buchen</h2>
      <p>
        Eine Buchung belegt <strong>einen Raum für einen Kurs</strong> an einem Tag von einer
        Start- bis zu einer Endzeit. Anlegen lässt sie sich über <strong>+</strong> im Tab
        <a href="belegung.php">Belegung</a> oder durch Klick auf einen freien Platz im Kalender.
        Gespeichert wird nur, wenn alle diese Regeln erfüllt sind:
      </p>
      <ol>
        <li><strong>Zeit:</strong> nur Montag bis Freitag, frühestens <?php echo h(BUCHUNG_TAG_START); ?> Uhr,
          spätestens bis <?php echo h(BUCHUNG_TAG_ENDE); ?> Uhr, immer zur vollen oder halben Stunde.
          Start und Ende am selben Tag, das Ende nach dem Start, und der Beginn darf nicht in der
          Vergangenheit liegen.</li>
        <li><strong>Raum frei:</strong> Der Raum ist in dieser Zeit nicht schon belegt.</li>
        <li><strong>Kurs frei:</strong> Der Kurs ist in dieser Zeit nicht schon in einem anderen Raum gebucht.</li>
        <li><strong>Software vorhanden:</strong> Der Raum hat jede Software, die der Kurs braucht.</li>
        <li><strong>Genug Plätze:</strong> Der Raum hat mindestens so viele Arbeitsplätze, wie der Kurs
          Teilnehmer haben kann.</li>
      </ol>
      <p>
        Verstößt eine Buchung gegen mehrere Regeln, werden alle Probleme auf einmal angezeigt.
        Fehlen Software oder Plätze, schlägt das Formular zusätzlich Räume vor, die für den Kurs passen.
      </p>
      <p class="hinweis">
        Eine Buchung über mehrere Tage gibt es nicht – dafür mehrere Buchungen anlegen.
        Wird an einem Raum später die Ausstattung verringert, prüft das System alle kommenden
        Buchungen dieses Raums und lehnt die Änderung ab, wenn eine davon dadurch ungültig würde.
      </p>
    </section>

    <section class="karte" id="kalender">
      <h2><span class="nr">4</span>Kalender bedienen</h2>
      <p>Der Tab <a href="belegung.php">Belegung</a> zeigt den Kalender. Oben schalten Sie die Ansicht um:</p>
      <ul>
        <li><strong>Monat:</strong> Montag bis Freitag des ganzen Monats. Pro Tag stehen bis zu drei
          Buchungen; gibt es mehr, erscheint „+N weitere“. Klick auf einen Tag öffnet die Tagesansicht.</li>
        <li><strong>Woche</strong> (Standard): ein Raum, Montag bis Freitag in halben Stunden von
          <?php echo h(BUCHUNG_TAG_START); ?> bis <?php echo h(BUCHUNG_TAG_ENDE); ?> Uhr. Ohne Raumfilter
          wird der erste Raum gezeigt. Klick auf einen Wochentag öffnet die Tagesansicht.</li>
        <li><strong>Tag:</strong> alle Räume eines Tages nebeneinander. Klick auf einen Raumnamen öffnet
          die Wochenansicht dieses Raums.</li>
      </ul>
      <h3>Blättern und Filter</h3>
      <p>
        Mit „‹ zurück“, „Heute“ und „weiter ›“ blättern Sie (am Wochenende springt „Heute“ auf den
        nächsten Montag). In der Seitenleiste filtern Sie nach <strong>Raum</strong>, <strong>Kurs</strong>
        und <strong>„Nur meine Buchungen“</strong>; die Filter bleiben beim Blättern erhalten.
        „Listenansicht“ zeigt dieselben Buchungen als Liste, „Filter zurücksetzen“ hebt alle Filter auf.
      </p>
      <h3>Klicken im Kalender</h3>
      <ul>
        <li><strong>Freier Platz</strong> (Woche und Tag, „+“ beim Darüberfahren): öffnet das
          Buchungsformular mit Raum, Datum und Startzeit schon ausgefüllt. Das geht nur, wenn Sie
          mindestens einen Kurs buchen dürfen und die Zeit noch nicht vorbei ist.</li>
        <li><strong>Buchung:</strong> Darüberfahren zeigt Kurs, Zeit, Raum und wer gebucht hat.
          Anklicken öffnet die Buchung zum Bearbeiten – nur bei eigenen Buchungen (Admin: allen),
          die noch nicht begonnen haben.</li>
      </ul>
      <p>
        Jeder Kurs hat eine feste Farbe, eigene Buchungen sind umrandet. In Woche und Tag sind
        vergangene Zeiten und Buchungen blass dargestellt.
        Ein <strong>schraffierter</strong> Block heißt: Hier ist belegt, die Buchung ist aber durch
        Ihren Filter ausgeblendet – dort kann man also nicht buchen.
      </p>
    </section>

    <section class="karte" id="login">
      <h2><span class="nr">5</span>Erstes Login und Passwort vergessen</h2>
      <p>
        Der Admin kennt Ihr Passwort nie. Er legt Ihr Konto ohne Passwort an und gibt Ihnen
        stattdessen einen <strong>Freischaltcode</strong> (8 Zeichen, <?php echo (int) FREISCHALTCODE_GUELTIG_TAGE; ?> Tage gültig).
      </p>
      <ol>
        <li>Auf der Anmeldeseite bei „Erstes Login oder neuen Code erhalten?“ auf
          <a href="passwort_setzen.php">Passwort setzen</a> klicken.</li>
        <li>Benutzername und Freischaltcode eingeben, das neue Passwort zweimal eintragen, speichern.</li>
        <li>Danach ganz normal mit Benutzername und neuem Passwort anmelden.</li>
      </ol>
      <p>
        Jeder Code funktioniert nur einmal. <strong>Passwort vergessen?</strong> Dann beim Admin einen
        neuen Freischaltcode holen und genauso vorgehen. Bis der neue Code eingelöst ist, gilt das alte
        Passwort weiter.
      </p>
      <p class="hinweis">
        Wer sein Passwort noch kennt, ändert es einfach im <a href="profil.php">Profil</a> („Profile“ oben
        rechts). Dafür ist das aktuelle Passwort nötig. Ein noch offener Freischaltcode wird dabei ungültig.
      </p>
    </section>

    <section class="karte" id="passwort">
      <h2><span class="nr">6</span>Passwortregeln</h2>
      <ul>
        <li>mindestens <?php echo (int) PW_MIN_LAENGE; ?> Zeichen lang</li>
        <li>mindestens ein Kleinbuchstabe (a bis z, auch ä, ö, ü und ß)</li>
        <li>mindestens eine Ziffer</li>
        <li>beim Ändern im Profil: anders als das bisherige Passwort</li>
      </ul>
      <p>
        Großbuchstaben und Sonderzeichen sind erlaubt, aber nicht vorgeschrieben.
        Beispiele: „mäuse1“ ist gültig, „MÄUSE1“ nicht (kein Kleinbuchstabe),
        „abc“ auch nicht (zu kurz, keine Ziffer).
      </p>
    </section>

  </div>

</body>
</html>
