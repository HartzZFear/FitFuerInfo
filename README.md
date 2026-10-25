# FitFürInfo

FitFürInfo ist eine Kurs- und Raumverwaltung für eine Weiterbildungseinrichtung:
Mitarbeiter legen Kurse an und buchen dafür Räume, ein Belegungskalender zeigt, wann
welcher Raum belegt ist. Das System prüft dabei automatisch Zeiten, Überschneidungen,
benötigte Software und Platzzahl – beim Buchen und auch dann, wenn später ein Raum oder ein
Kurs geändert wird.

**Voraussetzung:** XAMPP **5.6** (PHP 5.6) mit Apache und MySQL, getestet mit 5.6.36 und
5.6.40. Neuere XAMPP-Versionen werden nicht unterstützt.
Download: `sourceforge.net/projects/xampp/files/XAMPP Windows/5.6.36/`
→ `xampp-win32-5.6.36-0-VC11-installer.exe`, Installationsordner `C:\xampp`.

---

## Installation von Hand

`<XAMPP>` steht im Folgenden für den XAMPP-Ordner, normalerweise `C:\xampp`.

1. **Projekt ablegen.** Das Repo so klonen (oder als ZIP entpacken), dass es genau unter
   `<XAMPP>\htdocs\fitfuerinfo` liegt:
   ```
   cd C:\xampp\htdocs
   git clone https://github.com/HartzZFear/FitFuerInfo.git fitfuerinfo
   ```
   Kontrolle: Es muss die Datei `<XAMPP>\htdocs\fitfuerinfo\index.php` geben.
   (Beim ZIP aufpassen, dass kein doppelter Ordner wie `fitfuerinfo\FitFuerInfo-main\`
   entsteht.)
2. **Server starten.** XAMPP Control Panel öffnen, bei **Apache** und **MySQL** auf
   **Start** klicken. Beide müssen grün werden.
3. **Datenbank anlegen.** Im Browser `http://localhost/phpmyadmin` öffnen.
   - Oben auf **Importieren** → Datei `src\db\schema.sql` auswählen → **OK**.
     Das legt die Datenbank `fitfuerinfo` mit allen Tabellen an.
   - Links auf **fitfuerinfo** klicken, dann wieder **Importieren** →
     `src\db\seed.sql` → **OK**. Das lädt die Testdaten.
   - Zeichencodierung beim Import auf **utf-8** lassen (Standard).
4. **Konfiguration anlegen.** Im Ordner `config` die Datei `config.example.php`
   kopieren und die Kopie `config.php` nennen. Darin prüfen:
   - `BASE_URL` steht auf `'/fitfuerinfo'`
   - `DEBUG` steht auf `false` (so ausgeliefert, richtig für Abgabe und Präsentation;
     zum Entwickeln auf `true` setzen, siehe „Fehlerseite“ unten)
   - Datenbank-Benutzer `root` ohne Passwort passt bei einer frischen XAMPP-Installation.

   In `config.php` stehen nur die Werte, die von Rechner zu Rechner verschieden sind.
   Was für alle gleich ist (erlaubte Seiten, Versionsnummer für CSS/JS,
   Passwort-Mindestlänge), steht in `config/app.php` und kommt mit dem Repo.
5. **Selbsttest:** `http://localhost/fitfuerinfo/tests/test.php` aufrufen. Stehen alle
   Zeilen auf OK, ist die Installation fertig (siehe „Selbsttest“ unten).
6. **Aufrufen:** `http://localhost/fitfuerinfo/` – es erscheint die Anmeldeseite.

## Testzugänge

Aus `src\db\seed.sql`:

| Benutzer | Rolle       | Passwort | Hinweis |
|----------|-------------|----------|---------|
| admin    | Admin       | `test1`  | sieht zusätzlich den Tab „Benutzer“ |
| lena     | Mitarbeiter | `test1`  | |
| markus   | Mitarbeiter | `test1`  | |
| sabine   | Mitarbeiter | `test1`  | **gesperrt** – Anmeldung schlägt absichtlich fehl |
| neuling  | Mitarbeiter | –        | hat noch kein Passwort: auf der Anmeldeseite bei „Erstes Login oder neuen Code erhalten?“ auf **Passwort setzen** klicken → Benutzername `neuling`, Freischaltcode `START123` (gültig bis 31.12.2027), eigenes Passwort wählen (z. B. `abc1`) |

Es gibt genau einen Admin. Die Rolle wird nicht über die Oberfläche vergeben oder entzogen,
neue Benutzer sind immer Mitarbeiter, und das Admin-Konto kann nicht gesperrt werden.

Die Testbuchungen werden beim Import relativ zum heutigen Datum angelegt (kommende Woche
und vorletzte Woche). Wer den Kalender vor einer Präsentation wieder frisch haben will,
importiert `schema.sql` und `seed.sql` einfach noch einmal.

## Adressen der Seiten

Alle Seiten laufen über `index.php`, die Seite steht im Parameter `seite`:

```
http://localhost/fitfuerinfo/index.php?seite=kurse
http://localhost/fitfuerinfo/index.php?seite=kurs_bearbeiten&id=3
http://localhost/fitfuerinfo/index.php?seite=passwort_setzen
```

Ohne `seite` zeigt `index.php` die Belegung (angemeldet) bzw. die Anmeldung. Erlaubt sind
nur die Seiten aus der Liste `ERLAUBTE_SEITEN` in `config/app.php`; jeder andere Name führt
zur Seite „Seite nicht gefunden“. Die Dateien in `pages/` direkt aufzurufen
(`.../pages/kurse.php`) zeigt bewusst nur eine leere Seite.

## Projektstruktur

```
fitfuerinfo/
├── index.php        einziger Einstiegspunkt (Front-Controller), bindet die Seiten ein
├── config/          app.php (für alle gleich, im Repo), config.example.php (Vorlage),
│                    config.php (eigene Kopie, nicht im Repo)
├── lib/             PHP-Funktionen mit dem SQL und der Fachlogik (db.php, auth.php,
│                    kurs_*, raum_*, buchung_*, benutzer_*, kalender_*, funktionen.php)
├── templates/       Kopf, Fuß und wiederkehrende Layout-Teile
├── pages/           die eigentlichen Seiten, nur der Inhalt (kein SQL)
├── assets/          css/, js/, imgs/
├── src/db/          schema.sql (Tabellen), seed.sql (Testdaten)
└── tests/           test.php (Selbsttest), nebenlaeufigkeit.php, docs/
```

Die Regeln für Änderungen am Code stehen in `CLAUDE.md`, Entwickler-Infos (Teamregeln,
Seiten und Rechte, Buchungsregeln) in `src\CONTRIBUTE.md`.

## Selbsttest

`http://localhost/fitfuerinfo/tests/test.php` prüft PHP-Version, Datenbanktreiber,
`config.php` und `config/app.php` (inkl. Liste der erlaubten Seiten), Datenbankverbindung,
Tabellen, Testdaten und Passwortprüfung. Stehen alle
Zeilen auf OK, ist die Installation fertig. Bei FEHLER steht daneben, was zu tun ist.

## Test für gleichzeitige Buchungen

`tests\nebenlaeufigkeit.php` zeigt, dass zwei Benutzer, die **gleichzeitig** denselben Raum
zur selben Zeit buchen, keine Doppelbuchung erzeugen. Aufruf auf der Kommandozeile im
Projektordner (Apache und MySQL müssen laufen):

```
C:\xampp\php\php.exe tests\nebenlaeufigkeit.php
```

Das Skript schickt in 10 Runden je zwei Buchungen gleichzeitig ab und zählt, wie viele
gespeichert wurden – erwartet ist in jeder Runde genau eine. Danach löscht es seine
Testbuchungen wieder. Wie die Sperre funktioniert und wie man im Fachgespräch vorführt, was
ohne sie passiert, steht oben in der Datei (danach muss `git diff` wieder leer sein).

## Nach dem Pull (für Teammitglieder)

1. `git pull` auf `main-dev` bzw. den Branch holen, den man testen will.
2. Sonst ist nichts zu tun:
   - Die Datenbank ist unverändert – kein neuer Import nötig.
   - Die eigene `config/config.php` bleibt, wie sie ist. Neu in der Vorlage ist nur
     `DEBUG = false`; wer entwickelt, lässt in seiner `config.php` `DEBUG = true`.
   - CSS und JavaScript haben eine neue Versionsnummer (`ASSET_VERSION`) – der Browser lädt
     sie von selbst neu, Cache leeren ist nicht nötig.
   - Neue Dateien in `lib/` und `pages/` bindet `index.php` selbst ein.
3. Kontrolle: `http://localhost/fitfuerinfo/tests/test.php` – alles muss OK sein.

## Häufige Fehler

| Problem | Ursache / Lösung |
|---------|------------------|
| **404 Not Found** | Projekt liegt im falschen Ordner oder die URL stimmt nicht. Es muss `<XAMPP>\htdocs\fitfuerinfo\index.php` geben und die Adresse `http://localhost/fitfuerinfo/` lauten. |
| **„Fehler: config/config.php fehlt“** | Schritt 4 vergessen: `config/config.example.php` nach `config/config.php` kopieren. |
| **„Notice: Constant ASSET_VERSION already defined“** (oder `PW_MIN_LAENGE`) | Ältere `config.php`: die Zeilen mit `ASSET_VERSION` und `PW_MIN_LAENGE` dort löschen, sie stehen jetzt in `config/app.php`. `tests/test.php` zeigt, welche Zeile stört. |
| **„Es ist ein Fehler aufgetreten“** | Fehlerseite bei einem unerwarteten technischen Fehler (z. B. Datenbankfehler). Mit `DEBUG = false` stehen bewusst keine Details darauf, sie landen im Apache-Fehlerlog `<XAMPP>\apache\logs\error.log`. Zum Entwickeln in `config.php` `DEBUG = true` setzen, dann stehen sie direkt unter der Meldung. |
| **Leere weiße Seite** | Eine Datei aus `pages/` wurde direkt aufgerufen (alter Link oder Lesezeichen). Stattdessen `http://localhost/fitfuerinfo/index.php?seite=<name>` verwenden. |
| **Apache startet nicht** | Port 80 ist belegt (z. B. Skype, IIS, VMware). In `<XAMPP>\apache\conf\httpd.conf` `Listen 80` in `Listen 8080` ändern; dann `http://localhost:8080/fitfuerinfo/` aufrufen. |
| **„Datenbankverbindung fehlgeschlagen“** | MySQL läuft nicht (Control Panel) oder `schema.sql` wurde nicht importiert. |
| **Umlaute kaputt** (z. B. „Ã¼“ statt „ü“) | `seed.sql` wurde nicht als UTF-8 importiert. Beim Import Zeichencodierung **utf-8** wählen, dann `schema.sql` und `seed.sql` erneut importieren. |
| **Anmeldung klappt nicht** | Passwort ist `test1`; `sabine` ist absichtlich gesperrt. |

## Hinweis zu den Skripten

`init.ps1`, `start.ps1` und `end.ps1` im Ordner `tests\` sind **veraltet**: Sie erwarten noch
die alte Ordnerstruktur (`web\`, `db\`) und einen anderen XAMPP-Pfad und werden nicht
verwendet. Bitte nur die Installation von Hand oben benutzen.

Weitere Unterlagen (Projektdokumentation, Bedienungsanleitung): `tests\docs\`.
