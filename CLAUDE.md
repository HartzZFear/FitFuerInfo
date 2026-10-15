# Projektregeln FitFuerInfo

Kurs- und Raumverwaltung, Schulprojekt LF12. Diese Regeln gelten für jede Änderung am Code.

## Rahmenbedingungen (nicht verhandelbar)

- Laufzeit: XAMPP 5.6 → **PHP 5.6** und MySQL/MariaDB aus XAMPP. Keine PHP-7-Syntax (kein `??`, keine Typ-Deklarationen wie `int`/`string` bei Parametern, keine Return-Types, kein `<=>`, kein `random_bytes()`).
- Kein Framework, kein Composer, kein Build-Schritt. Das Projekt muss per ZIP in `htdocs\fitfuerinfo` entpackt sofort laufen.
- Datenbankzugriff ausschließlich über die vorhandenen Helfer in `lib/db.php`: `db()` (PDO-Verbindung) und `abfrage($sql, $parameter)` (Prepared Statement). Parameter **immer** über das Array, nie per Stringverkettung ins SQL.
- Jede Ausgabe von Daten im HTML über den vorhandenen Helfer `h()` (`htmlspecialchars`).
- Alle Tabellen verwenden die Engine **InnoDB** (siehe `src/db/schema.sql`).
- Alle URLs werden aus der Konstante `BASE_URL` gebaut. **Nie** `/fitfuerinfo` fest in den Code schreiben.

## Angemessenheit: nur bauen, was gefordert ist

- Maßstab sind die Anforderungen. Keine Funktionen oder Absicherungen für Szenarien, die die Anforderungen nicht vorsehen.
- Jede technische Maßnahme muss sich mit einem konkreten Problem in diesem Projekt begründen lassen. Wenn nicht: weglassen.
- Lieber einfach und erklärbar als clever. Wir müssen jede Zeile im Fachgespräch erklären können.
- Bei Refactorings ändert sich **nur die Struktur, nicht das Verhalten**. Funktionen, Texte und Aussehen bleiben gleich, außer die Aufgabe sagt ausdrücklich etwas anderes.

## Ordnerstruktur

Der Repo-Hauptordner ist der Webroot. Aufruf: `http://localhost/fitfuerinfo/`. Alle Seiten werden ausschließlich über `index.php` aufgerufen.

```
fitfuerinfo/
│
├── index.php              Front-Controller: einziger Einstiegspunkt
├── CLAUDE.md, README.md
│
├── config/
│   ├── app.php            projektweite Konstanten (im Repo): Seiten-Whitelist
│   │                      ERLAUBTE_SEITEN, ASSET_VERSION, PW_MIN_LAENGE
│   ├── config.example.php Vorlage für config.php (im Repo)
│   └── config.php         lokale Kopie (nicht im Repo, .gitignore)
│                          nur rechnerspezifische Werte: DB-Zugang, BASE_URL, DEBUG
│
├── lib/                   Wiederverwendbare PHP-Funktionen – KEINE Ausgabe beim Einbinden
│   ├── db.php             db(), abfrage(), h()
│   ├── auth.php           Login, Session, CSRF, Rechteprüfung
│   ├── cache.php          Funktionen für Cache-Header
│   ├── funktionen.php     Allgemeine Helfer und UI-Bausteine (z. B. button(), meldung())
│   └── <bereich>_*.php    Fachliche Funktionen je Bereich, enthalten das SQL
│                          (bestehende Namen bleiben: buchung_logik.php, kalender_logik.php,
│                          benutzer_logik.php, kurs_rechte.php, raum_rechte.php;
│                          neue Dateien nach demselben Muster, z. B. kurs_logik.php)
│
├── templates/             Wiederkehrende Layout-Elemente
│   ├── header.php         <!DOCTYPE>, <head>, Kopfzeile mit Tabs, Wellen-Banner
│   ├── footer.php         Fußbereich, Script-Einbindung, </body></html>
│   └── bausteine.php      wiederkehrende Layout-Teile als Funktionen (Welle, Formularkopf)
│
├── pages/                 Die eigentlichen Seiteninhalte – nur der Body-Inhalt
│   ├── belegung.php
│   ├── kurse.php
│   └── ...
│
├── assets/
│   ├── css/style.css      gemeinsames CSS, :root-Variablen NUR hier
│   ├── css/<seite>.css    nur für umfangreiches seitenspezifisches CSS
│   ├── js/main.js         gemeinsames JavaScript
│   ├── js/<seite>.js      nur für umfangreiches seitenspezifisches JavaScript
│   └── imgs/              Bilder (Logo genau einmal)
│
├── src/db/                SQL-Dateien (schema.sql, seed.sql, ffi_dbinit.sql) – nicht im Webroot-Betrieb
└── tests/                 Skripte, Testseite, Dokumentation
```

## Front-Controller (`index.php`)

Ablauf, in dieser Reihenfolge:
1. Konstante `FFI_START` definieren, `lib/`-Dateien einbinden (`lib/db.php` lädt erst `config/config.php`, dann `config/app.php`), Session starten. Fehlt `ERLAUBTE_SEITEN`, bricht `index.php` mit einer klaren Meldung ab (wie `lib/db.php` bei fehlender `config.php`).
2. Gewünschte Seite aus `$_GET['seite']` lesen und gegen die **Whitelist** `ERLAUBTE_SEITEN` in `config/app.php` prüfen (`in_array(..., true)`). Ohne Angabe: angemeldet → `belegung`, nicht angemeldet → `login`. Unbekannte Werte → 404-Seite. **Niemals** einen Dateinamen ungeprüft aus der URL einbinden (Sicherheitslücke: File Inclusion).
3. Cache-Header zentral setzen (`cache_dynamisch()`), da alle Seiten dynamisch sind.
4. Seite mit Output-Buffering einbinden:
   ```php
   ob_start();
   include __DIR__ . '/pages/' . $seite . '.php';
   $inhalt = ob_get_clean();
   ```
   Grund: Die Seite kann so erst ihre Logik ausführen, `$seitentitel` und `$aktiverTab` setzen oder per `header('Location: ...')` weiterleiten, **bevor** irgendetwas an den Browser geht.
5. `templates/header.php` einbinden, `echo $inhalt;`, `templates/footer.php` einbinden.
6. Seiten ohne Kopfzeile (z. B. `login`, `passwort_setzen`) setzen `$ohneKopfzeile = true;` – das Template lässt dann Tabs und Banner weg.

Links, Formulare und Weiterleitungen zeigen immer auf `BASE_URL . '/index.php?seite=<name>'` (weitere Parameter mit `&`). Dafür gibt es eine Funktion `url($seite, $parameter = array())` in `lib/funktionen.php`.

## Seiten (`pages/`)

- Jede Seitendatei beginnt mit `<?php if (!defined('FFI_START')) { exit; } ?>`, damit sie nicht direkt aufgerufen werden kann.
- Eine Seite enthält **nur den Body-Inhalt** – kein `<!DOCTYPE>`, kein `<html>`, `<head>`, `<body>`, keine Kopfzeile, keinen Footer. Diese kommen ausschließlich aus `templates/`.
- Aufbau:
  1. **Oben: Steuerung** – Rechteprüfung, Formular entgegennehmen (inkl. CSRF-Prüfung), passende Funktion aus `lib/` aufrufen, Ergebnis in Variablen ablegen, `$seitentitel` und `$aktiverTab` setzen.
  2. **Unten: HTML-Ausgabe** – nur Darstellung. PHP beschränkt sich auf kurze Ausgaben (`<?php echo h($x); ?>`), Aufrufe von UI-Bausteinen und einfache `foreach`/`if` in alternativer Syntax (`foreach (...):` … `endforeach;`).
- **Kein SQL in Seiten.** Datenbankzugriffe stehen in Funktionen in `lib/`.
- **Kein** `<style>`- und **kein** `<script>`-Block mit Inhalt. CSS und JS gehören in `assets/`. Werte, die JavaScript vom Server braucht, über `data-`-Attribute übergeben.
- Ziel: Seiten bleiben klein und lesbar. Wird eine Seite lang, ist das ein Zeichen, dass Logik nach `lib/` gehört.

## Funktionen auslagern (`lib/`)

- Alles, was mehr als einmal vorkommt oder fachliche Logik ist, wird **einmal als Funktion** in `lib/` deklariert und überall nur noch aufgerufen.
- **UI-Bausteine** in `lib/funktionen.php`, die HTML als String zurückgeben, z. B.:
  ```php
  function button($text, $ziel, $art = 'primaer') { ... }
  function meldung($text, $typ = 'info') { ... }
  function url($seite, $parameter = array()) { ... }
  ```
  In der Seite: `<?php echo button('Speichern', url('kurse')); ?>`
- **Aktionen** (was beim Klick passiert, z. B. Kurs speichern, Buchung anlegen) als Funktionen in `lib/`, z. B. `kurs_speichern($daten)`, `buchung_anlegen(...)`. Sie geben ein Ergebnis zurück (z. B. `true` oder ein Array mit Fehlermeldungen) und geben **nichts** selbst aus.
- **Keine kompletten HTML-Seiten aus `lib/`.** Fehler- und Hinweisseiten (z. B. „Zugriff verweigert“, „Sitzung abgelaufen“, Freischaltcode-Anzeige) sind eigene Seiten in `pages/` und laufen über die Templates.
- **Verhalten im Browser** (z. B. Bestätigungsdialog vor dem Löschen) einmal in `assets/js/`, angesprochen über Klassen oder `data-`-Attribute – nicht pro Button neu geschrieben.
- `lib/`-Dateien erzeugen beim Einbinden **keine Ausgabe** und enthalten nur Funktionen bzw. Konstanten.
- Funktionsnamen auf Deutsch, klein mit Unterstrich, mit Bereich als Präfix (`kurs_...`, `raum_...`, `buchung_...`, `benutzer_...`). Jede Funktion bekommt einen kurzen Kommentar: was sie tut, Parameter, Rückgabe.

## DRY – jede Information genau einmal

- Bevor du Code schreibst: prüfe, ob es dafür schon eine Funktion, ein Template oder eine CSS-Regel gibt, und verwende sie.
- Taucht derselbe Code an **zwei** Stellen auf, wird er ausgelagert: PHP-Logik → `lib/`, Layout → `templates/`, Aussehen → `assets/css/`, Browser-Verhalten → `assets/js/`.
- Farben, Abstände, Schriften nur über die CSS-Variablen in `style.css`. Keine Farbcodes in Seiten oder Templates (Ausnahme: SVG-Verläufe im Template, mit Verweis auf die Variable im Kommentar).
- Kein verschachteltes CSS (keine `&`-Syntax, keine Regeln in Regeln). Jede Regel steht flach mit ihrem vollständigen Selektor, z. B. `.tab.active { ... }`.
- Rechnerspezifische Werte (DB-Zugang, BASE_URL, DEBUG) nur in `config/config.php`. Projektweite Konstanten (Seiten-Whitelist, Versionsnummer, Passwort-Mindestlänge) nur in `config/app.php` – sie gelten für alle gleich und kommen deshalb über Git, statt dass jeder seine lokale `config.php` nachziehen muss.
- Kein auskommentierter Altcode. Was nicht mehr gebraucht wird, wird gelöscht – Git hat die Historie.

## Cache-Steuerung

- Header werden **vor jeder Ausgabe** gesetzt. Durch den Front-Controller mit Output-Buffering ist das gewährleistet; trotzdem keine Leerzeichen/Zeilen vor `<?php` in `lib/`- und `config/`-Dateien und kein schließendes `?>` am Ende reiner PHP-Dateien.
- Cache-Header werden **nur** in `index.php` über eine Funktion aus `lib/cache.php` gesetzt (`cache_dynamisch()` → `Cache-Control: no-store, no-cache, must-revalidate, private` und `Pragma: no-cache`). Bestehende einzelne `header('Cache-Control ...')`-Aufrufe in `lib/` entfallen dadurch.
- `session_start()` sendet über `session.cache_limiter` bereits No-Cache-Header. Keine widersprüchlichen Header zusätzlich setzen.
- CSS/JS dürfen gecacht werden. Damit Änderungen ankommen, werden sie in `templates/` mit Versionsnummer eingebunden: `BASE_URL . '/assets/css/style.css?v=' . ASSET_VERSION`. Bei Änderungen an CSS/JS `ASSET_VERSION` in `config/app.php` erhöhen.

## Transaktionen und Sperren – nur wo logisch nötig

Transaktionen stehen ausschließlich in den Funktionen in `lib/`, nie in Seiten.

**Transaktion verwenden**, wenn:
- mehrere Schreibbefehle zusammengehören (alles oder nichts), oder
- erst geprüft und dann abhängig vom Ergebnis geschrieben wird („ist der Raum zu der Zeit frei? → dann buchen“).

**Keine Transaktion**, wenn nur ein einzelnes SELECT, INSERT, UPDATE oder DELETE ausgeführt wird – ein einzelner Befehl ist bereits atomar.

Muster:
```php
$db = db();
try {
    $db->beginTransaction();
    // 1. relevante Zeilen sperren: SELECT ... FOR UPDATE
    // 2. Bedingung prüfen
    // 3. schreiben
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    // verständliche Fehlermeldung zurückgeben, nicht direkt ausgeben
}
```

- Transaktionen kurz halten: keine Wartezeiten, keine Ausgaben, kein HTML innerhalb der Transaktion.
- Sperrreihenfolge im ganzen Projekt immer gleich: **raum → kurs → buchung → benutzer**. Gleiche Reihenfolge verhindert Deadlocks.
- Doppelbuchungen: Eine Buchung ist ein Zeitraum (`start`, `ende`). Überschneidende Zeiträume lassen sich **nicht** per `UNIQUE` verhindern. Deshalb: in der Transaktion zuerst die Zeile des Raums mit `SELECT ... FOR UPDATE` sperren, dann auf Überschneidung prüfen, dann schreiben. Zwei gleichzeitige Buchungen für denselben Raum laufen so nacheinander statt gleichzeitig.
- Wo ein Wert eindeutig sein muss (z. B. Benutzername, Raumname), sichert ein `UNIQUE`-Constraint in der Datenbank zusätzlich ab.
- Jede Transaktion bekommt einen Kommentar mit dem konkreten Grund (z. B. „verhindert Doppelbuchung, wenn zwei Nutzer gleichzeitig denselben Raum buchen“).

## Arbeitsweise mit Git

- Nie direkt auf `main-dev` oder `main-prd` arbeiten. Jede Aufgabe auf einem eigenen Branch `refactor/<thema>` bzw. `dev-f/<thema>` von `main-dev`.
- Verschieben von Dateien mit `git mv`, damit die Historie erhalten bleibt.
- Kleine Commits mit aussagekräftiger deutscher Nachricht, ein Schritt pro Commit.
- `config/config.php` wird nie committet.

## Abschluss jeder Aufgabe

Bevor du fertig meldest:
1. Prüfe deine Änderungen gegen alle Regeln dieser Datei und nenne Abweichungen, die noch offen sind.
2. Suche nach Duplikaten und Altlasten, die durch deine Änderung entstanden oder übrig sind (z. B. `:root` außerhalb von `style.css`, `<style>`, `<!DOCTYPE>` in `pages/`, SQL außerhalb von `lib/`, festes `/fitfuerinfo`, auskommentierter Code, doppelte Funktionen).
3. Prüfe alle geänderten PHP-Dateien mit `php -l`, sofern PHP verfügbar ist.
4. Fasse zusammen: geänderte Dateien, und **für jede technische Entscheidung 1–2 Sätze Begründung** in einfacher Sprache, so dass sie ein Schüler im Fachgespräch erklären kann.
5. Nenne konkrete Testschritte im Browser.
