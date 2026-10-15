# Projektregeln FitFuerInfo

Kurs- und Raumverwaltung, Schulprojekt LF12. Diese Regeln gelten für jede Änderung am Code.

## Rahmenbedingungen (nicht verhandelbar)

- Laufzeit: XAMPP 5.6 → **PHP 5.6** und MySQL/MariaDB aus XAMPP. Keine PHP-7-Syntax (kein `??`, keine Typ-Deklarationen wie `int`/`string` bei Parametern, keine Return-Types, kein `<=>`).
- Kein Framework, kein Composer, kein Build-Schritt. Das Projekt muss per ZIP in `htdocs` entpackt sofort laufen.
- Datenbankzugriff ausschließlich über **PDO mit Prepared Statements**, `PDO::ERRMODE_EXCEPTION`.
- Alle Tabellen verwenden die Engine **InnoDB**.

## Angemessenheit: nur bauen, was gefordert ist

- Maßstab sind die Anforderungen (z. B.: es gibt genau **einen** Admin). Keine Funktionen oder Absicherungen für Szenarien, die die Anforderungen nicht vorsehen.
- Jede technische Maßnahme muss sich mit einem konkreten Problem in diesem Projekt begründen lassen. Wenn nicht: weglassen.
- Lieber einfach und erklärbar als clever. Wir müssen jede Zeile im Fachgespräch erklären können.

## Ordnerstruktur

Webroot ist der Projektordner (aktuell `src/web/`). Alle Seiten werden ausschließlich über `index.php` aufgerufen.

```
projekt/
│
├── index.php              Front-Controller: einziger Einstiegspunkt
│
├── config/
│   └── config.php         Konstanten (DB-Zugang, ASSET_VERSION, erlaubte Seiten)
│
├── lib/                   Wiederverwendbare PHP-Funktionen – KEINE Ausgabe beim Einbinden
│   ├── db.php             PDO-Verbindung, Transaktions-Helfer
│   ├── auth.php           Login, Session, Rechteprüfung
│   ├── cache.php          Funktionen für Cache-Header
│   ├── funktionen.php     Allgemeine Helfer und UI-Bausteine (z. B. button(), meldung())
│   └── <bereich>.php      Fachliche Funktionen je Bereich (kurse.php, raeume.php,
│                          buchungen.php, benutzer.php) – enthalten das SQL
│
├── templates/             Wiederkehrende Layout-Elemente
│   ├── header.php         <!DOCTYPE>, <head>, Kopfbereich, Navigation
│   └── footer.php         Fußbereich, Script-Einbindung, </body></html>
│
├── pages/                 Die eigentlichen Seiteninhalte – nur der Body-Inhalt
│   ├── startseite.php
│   ├── kurse.php
│   └── ...
│
└── assets/
    ├── css/main.css       gemeinsames CSS, :root-Variablen NUR hier
    ├── css/<seite>.css    nur für umfangreiches seitenspezifisches CSS
    └── js/main.js         gemeinsames JavaScript
```

## Front-Controller (`index.php`)

Ablauf, in dieser Reihenfolge:
1. Konstante `FFI_START` definieren, `config/config.php` und benötigte `lib/`-Dateien einbinden, Session starten.
2. Gewünschte Seite aus `$_GET['seite']` lesen und gegen eine **Whitelist** in `config.php` prüfen (`in_array(..., true)`). Unbekannte Werte → Startseite oder 404-Seite. **Niemals** einen Dateinamen ungeprüft aus der URL einbinden (Sicherheitslücke: File Inclusion).
3. Cache-Header zentral setzen (`cache_dynamisch()`), da alle Seiten dynamisch sind.
4. Seite mit Output-Buffering einbinden:
   ```php
   ob_start();
   include __DIR__ . '/pages/' . $seite . '.php';
   $inhalt = ob_get_clean();
   ```
   Grund: Die Seite kann so erst ihre Logik ausführen, `$seitentitel` setzen oder per `header('Location: ...')` weiterleiten, **bevor** irgendetwas an den Browser geht.
5. `templates/header.php` einbinden, `echo $inhalt;`, `templates/footer.php` einbinden.

Links und Formulare zeigen immer auf `index.php?seite=<name>`. Da alles über `index.php` läuft, sind Pfade zu `assets/` überall gleich.

## Seiten (`pages/`)

- Jede Seitendatei beginnt mit `<?php if (!defined('FFI_START')) { exit; } ?>`, damit sie nicht direkt aufgerufen werden kann.
- Eine Seite enthält **nur den Body-Inhalt** – kein `<!DOCTYPE>`, kein `<html>`, `<head>`, `<body>`, keine Navigation, keinen Footer. Diese kommen ausschließlich aus `templates/`.
- Aufbau:
  1. **Oben: Steuerung** – Rechteprüfung, Formular entgegennehmen, passende Funktion aus `lib/` aufrufen, Ergebnis in Variablen ablegen, `$seitentitel` setzen.
  2. **Unten: HTML-Ausgabe** – nur Darstellung. PHP beschränkt sich auf kurze Ausgaben (`<?php echo e($x); ?>`), Aufrufe von UI-Bausteinen und einfache `foreach`/`if` in alternativer Syntax (`foreach (...):` … `endforeach;`).
- **Kein SQL in Seiten.** Datenbankzugriffe stehen in Funktionen in `lib/<bereich>.php`.
- **Kein** `<style>`- und **kein** `<script>`-Block mit Inhalt. CSS und JS gehören in `assets/`.
- Ziel: Seiten bleiben klein und lesbar. Wird eine Seite lang, ist das ein Zeichen, dass Logik nach `lib/` gehört.

## Funktionen auslagern (`lib/`)

- Alles, was mehr als einmal vorkommt oder fachliche Logik ist, wird **einmal als Funktion** in `lib/` deklariert und überall nur noch aufgerufen.
- **UI-Bausteine** in `lib/funktionen.php`, die HTML zurückgeben, z. B.:
  ```php
  function button($text, $ziel, $art = 'primaer') { ... }
  function meldung($text, $typ = 'info') { ... }
  function e($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
  ```
  In der Seite: `<?php echo button('Speichern', 'index.php?seite=kurse'); ?>`
- **Aktionen** (was beim Klick passiert, z. B. Kurs speichern, Buchung anlegen) als Funktionen in `lib/<bereich>.php`, z. B. `kurs_speichern($daten)`, `buchung_anlegen($raumId, $slot, $benutzerId)`.
- **Verhalten im Browser** (z. B. Bestätigungsdialog vor dem Löschen) einmal in `assets/js/main.js`, angesprochen über Klassen oder `data-`-Attribute – nicht pro Button neu geschrieben.
- `lib/`-Dateien erzeugen beim Einbinden **keine Ausgabe** und enthalten nur Funktionen bzw. Konstanten.
- Funktionsnamen auf Deutsch, klein mit Unterstrich, mit Bereich als Präfix (`kurs_...`, `raum_...`, `buchung_...`). Jede Funktion bekommt einen kurzen Kommentar: was sie tut, Parameter, Rückgabe.

## DRY – jede Information genau einmal

- Bevor du Code schreibst: prüfe, ob es dafür schon eine Funktion, ein Template oder eine CSS-Regel gibt, und verwende sie.
- Taucht derselbe Code an **zwei** Stellen auf, wird er ausgelagert: PHP-Logik → `lib/`, Layout → `templates/`, Aussehen → `assets/css/`, Browser-Verhalten → `assets/js/`.
- Farben, Abstände, Schriften nur über die CSS-Variablen in `main.css`.
- Konfigurationswerte (DB-Zugang, Versionsnummer, Seiten-Whitelist) nur in `config/config.php`.

## Cache-Steuerung

- Header werden **vor jeder Ausgabe** gesetzt. Durch den Front-Controller mit Output-Buffering ist das gewährleistet; trotzdem keine Leerzeichen/Zeilen vor `<?php` in `lib/`- und `config/`-Dateien und kein schließendes `?>` am Ende reiner PHP-Dateien.
- Cache-Header werden **nur** in `index.php` über eine Funktion aus `lib/cache.php` gesetzt (`cache_dynamisch()` → `Cache-Control: no-store, private`).
- `session_start()` sendet über `session.cache_limiter` bereits No-Cache-Header. Keine widersprüchlichen Header zusätzlich setzen.
- CSS/JS dürfen gecacht werden. Damit Änderungen ankommen, werden sie in `templates/` mit Versionsnummer eingebunden: `assets/css/main.css?v=<?php echo ASSET_VERSION; ?>`. Bei Änderungen an CSS/JS `ASSET_VERSION` in `config.php` erhöhen.

## Transaktionen und Sperren – nur wo logisch nötig

Transaktionen stehen ausschließlich in den Funktionen in `lib/<bereich>.php`, nie in Seiten.

**Transaktion verwenden**, wenn:
- mehrere Schreibbefehle zusammengehören (alles oder nichts), oder
- erst geprüft und dann abhängig vom Ergebnis geschrieben wird („ist der Raum frei? → dann buchen“).

**Keine Transaktion**, wenn nur ein einzelnes SELECT, INSERT, UPDATE oder DELETE ausgeführt wird – ein einzelner Befehl ist bereits atomar.

Muster:
```php
try {
    $pdo->beginTransaction();
    // 1. relevante Zeilen sperren: SELECT ... FOR UPDATE
    // 2. Bedingung prüfen
    // 3. schreiben
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    // verständliche Fehlermeldung zurückgeben, nicht direkt ausgeben
}
```

- Transaktionen kurz halten: keine Wartezeiten, keine Ausgaben, kein HTML innerhalb der Transaktion.
- Mehrere Tabellen immer in **derselben Reihenfolge** sperren (z. B. erst `raum`, dann `buchung`), um Deadlocks zu vermeiden.
- Zusätzliche Absicherung auf Datenbankebene über Constraints (`UNIQUE`, `FOREIGN KEY`), z. B. `UNIQUE` auf Raum + Zeitslot gegen Doppelbuchungen.
- Jede Transaktion bekommt einen Kommentar mit dem konkreten Grund (z. B. „verhindert Doppelbuchung, wenn zwei Nutzer gleichzeitig buchen“).

## Abschluss jeder Aufgabe

Bevor du fertig meldest:
1. Prüfe deine Änderungen gegen alle Regeln dieser Datei und nenne Abweichungen.
2. Suche nach Duplikaten, die durch deine Änderung entstanden sind (z. B. `:root`, `<style>`, `<!DOCTYPE>` in `pages/`, SQL außerhalb von `lib/`, doppelte Funktionen).
3. Fasse zusammen: geänderte Dateien, und **für jede technische Entscheidung 1–2 Sätze Begründung** in einfacher Sprache, so dass sie ein Schüler im Fachgespräch erklären kann.
4. Nenne konkrete Testschritte im Browser.
