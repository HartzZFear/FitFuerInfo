# FitFürInfo

FitFürInfo ist eine Kurs- und Raumverwaltung für eine Weiterbildungseinrichtung:
Mitarbeiter legen Kurse an und buchen dafür Räume, ein Belegungskalender zeigt, wann
welcher Raum belegt ist. Das System prüft dabei automatisch Zeiten, Überschneidungen,
benötigte Software und Platzzahl – beim Buchen und auch dann, wenn später ein Raum oder ein
Kurs geändert wird.

**Voraussetzung:** XAMPP **5.6** (PHP 5.6) mit Apache und MySQL, getestet mit 5.6.40; der
Test auf dem Schulrechner (5.6.36) steht noch aus. Neuere XAMPP-Versionen werden nicht
unterstützt.
Download: `sourceforge.net/projects/xampp/files/XAMPP Windows/5.6.36/`
→ `xampp-win32-5.6.36-0-VC11-installer.exe`, Installationsordner `C:\xampp`.

---

## Installation

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
3. **Einrichtung starten.** Im Browser `http://localhost/fitfuerinfo/einrichtung.php`
   öffnen und **Einrichtung starten** klicken. Die Seite arbeitet fünf Schritte ab und
   hakt jeden ab:
   1. `config/config.php` aus der Vorlage `config/config.example.php` anlegen (eine
      vorhandene `config.php` bleibt unverändert). `BASE_URL` wird dabei aus der
      Adresse übernommen, unter der die Seite aufgerufen wurde.
   2. Verbindung zu MySQL
   3. Datenbank `fitfuerinfo` mit allen Tabellen anlegen (`src\db\schema.sql`)
   4. Testdaten laden (`src\db\seed.sql`)
   5. Prüfung: Tabellen, Benutzer, Anmeldung mit `admin` / `test1`

   Sind alle fünf grün, führt **Zur Anmeldung** zur Anwendung. Bei einem roten ✗
   steht darunter, was zu tun ist; danach **Erneut versuchen**. Die Seite funktioniert
   nur über `localhost` auf dem eigenen Rechner, weil sie alle Daten löscht.

   In `config.php` stehen nur die Werte, die von Rechner zu Rechner verschieden sind:
   Datenbank-Benutzer (`root` ohne Passwort passt bei einer frischen XAMPP-Installation),
   `BASE_URL` und `DEBUG` (`false`, richtig für Abgabe und Präsentation; zum Entwickeln
   auf `true` setzen, siehe „Häufige Fehler“ unten). Was für alle gleich ist (erlaubte
   Seiten, Versionsnummer für CSS/JS, Passwort-Mindestlänge), steht in `config/app.php`
   und kommt mit dem Repo.
4. **Aufrufen:** `http://localhost/fitfuerinfo/` – es erscheint die Anmeldeseite.
   Zur Kontrolle gibt es zusätzlich den Selbsttest (siehe „Selbsttest“ unten).

### Alternative von Hand

Statt Schritt 3 geht es auch ohne die Einrichtungsseite:

- `http://localhost/phpmyadmin` öffnen, oben **Importieren** → `src\db\schema.sql` → **OK**,
  dann links auf **fitfuerinfo** klicken und wieder **Importieren** → `src\db\seed.sql`
  → **OK**. Zeichencodierung beim Import auf **utf-8** lassen (Standard).
- Im Ordner `config` die Datei `config.example.php` kopieren und die Kopie `config.php`
  nennen. Liegt das Projekt nicht unter `htdocs\fitfuerinfo`, darin `BASE_URL` anpassen.

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

Die Testbuchungen werden bei der Einrichtung relativ zum heutigen Datum angelegt (kommende
Woche und vorletzte Woche). Wer den Kalender vor einer Präsentation wieder frisch haben
will, ruft `http://localhost/fitfuerinfo/einrichtung.php` auf und klickt noch einmal
**Einrichtung starten** – das setzt alle Daten auf die Testdaten zurück.

## Adressen der Seiten

Alle Seiten laufen über `index.php`, die Seite steht im Parameter `seite`:

```
http://localhost/fitfuerinfo/index.php?seite=kurse
http://localhost/fitfuerinfo/index.php?seite=kurs_bearbeiten&id=3
http://localhost/fitfuerinfo/index.php?seite=passwort_setzen
```

Ohne `seite` zeigt `index.php` die Belegung (angemeldet) bzw. die Anmeldung. Erlaubt sind
nur die Seiten aus der Liste `ERLAUBTE_SEITEN` in `config/app.php`; jeder andere Name führt
zur Seite „Seite nicht gefunden“. Einzige Ausnahme ist `einrichtung.php`, die
eigenständig läuft (siehe Installation). Die Dateien in `pages/` direkt aufzurufen
(`.../pages/kurse.php`) zeigt bewusst nur eine leere Seite.

## Projektstruktur

```
fitfuerinfo/
├── index.php        einziger Einstiegspunkt (Front-Controller), bindet die Seiten ein
├── einrichtung.php  Einrichtung auf einem frischen Rechner (config.php + Datenbank);
│                    läuft ohne index.php, weil es vorher weder config.php noch DB gibt
├── config/          app.php (für alle gleich, im Repo), config.example.php (Vorlage),
│                    config.php (eigene Kopie, nicht im Repo)
├── lib/             PHP-Funktionen mit dem SQL und der Fachlogik (db.php, auth.php,
│                    kurs_*, raum_*, buchung_*, benutzer_*, kalender_*, einrichtung_*,
│                    funktionen.php, ausgabe.php mit h())
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

Gilt für jeden Stand, auch wenn man lange nicht gepullt hat. Was schon passt, einfach
überspringen. Alle Befehle in der Eingabeaufforderung im Ordner `C:\xampp\htdocs\fitfuerinfo`.

1. **Neuesten Stand holen:**
   ```
   git checkout main-dev
   git pull
   ```
   (Meldet `git pull` einen Fehler mit der Adresse des Repos, zuerst Schritt 5 machen.)
2. **Eigene `config.php` an den neuen Ort.** Früher lag sie unter
   `src\web\config.php`, jetzt gehört sie nach `config\config.php`. Liegt sie noch am alten
   Ort: in den Ordner `config` **verschieben** (nicht kopieren):
   ```
   move src\web\config.php config\config.php
   ```
   Am alten Ort liest die Anwendung sie nicht mehr – ohne `config\config.php` meldet sie
   „config/config.php fehlt“.
3. **Zwei Zeilen löschen:** In `config\config.php` die Zeilen mit `ASSET_VERSION` und
   `PW_MIN_LAENGE` löschen, falls vorhanden. Die stehen jetzt für alle in `config\app.php`.
4. **DEBUG:** Wer am Code arbeitet, setzt in `config\config.php` `DEBUG` auf `true` (dann
   sieht man Fehlermeldungen). Für Abgabe und Präsentation `false` – so steht es auch in der
   Vorlage.
5. **Adresse des Repos prüfen:** `git remote -v` eingeben. Steht dort nicht
   `https://github.com/HartzZFear/FitFuerInfo.git`, umstellen:
   ```
   git remote set-url origin https://github.com/HartzZFear/FitFuerInfo.git
   ```
6. **Selbsttest:** `http://localhost/fitfuerinfo/tests/test.php` aufrufen – alle Zeilen
   müssen OK sein. Bei FEHLER steht daneben, was zu tun ist.
7. **Neue Adressen:** Alle Seiten laufen jetzt über `index.php?seite=...`, z. B.
   `http://localhost/fitfuerinfo/index.php?seite=kurse`. Alte Lesezeichen auf `.../pages/...`
   oder `.../src/web/...` gehen nicht mehr – einfach `http://localhost/fitfuerinfo/` öffnen
   und neu speichern.

Die Datenbank bleibt, wie sie ist: Seit der Umstellung hat sich an den Tabellen nichts
geändert, ein neuer Import ist nicht nötig.

## Häufige Fehler

| Problem | Ursache / Lösung |
|---------|------------------|
| **404 Not Found** | Projekt liegt im falschen Ordner oder die URL stimmt nicht. Es muss `<XAMPP>\htdocs\fitfuerinfo\index.php` geben und die Adresse `http://localhost/fitfuerinfo/` lauten. |
| **„Fehler: config/config.php fehlt“** | Einrichtung noch nicht ausgeführt: `einrichtung.php` aufrufen (Installation Schritt 3). |
| **Einrichtung: Schritt 1 rot, „BASE_URL in config/config.php ist …“** | Der Projektordner heißt anders als beim Anlegen von `config.php`. In `config/config.php` `BASE_URL` auf den genannten Wert setzen (oder `config.php` löschen, dann legt die Einrichtung sie neu an) oder den Ordner in `fitfuerinfo` umbenennen. |
| **Einrichtung: „nur direkt auf diesem Rechner … erlaubt“** | Die Seite wurde über die IP-Adresse oder von einem anderen Rechner aufgerufen. Am Rechner selbst `http://localhost/fitfuerinfo/einrichtung.php` öffnen. |
| **„Notice: Constant ASSET_VERSION already defined“** (oder `PW_MIN_LAENGE`) | Ältere `config.php`: die Zeilen mit `ASSET_VERSION` und `PW_MIN_LAENGE` dort löschen, sie stehen jetzt in `config/app.php`. `tests/test.php` zeigt, welche Zeile stört. |
| **„Es ist ein Fehler aufgetreten“** | Fehlerseite bei einem unerwarteten technischen Fehler (z. B. Datenbankfehler). Mit `DEBUG = false` stehen bewusst keine Details darauf, sie landen im Apache-Fehlerlog `<XAMPP>\apache\logs\error.log`. Zum Entwickeln in `config.php` `DEBUG = true` setzen, dann stehen sie direkt unter der Meldung. |
| **Leere weiße Seite** | Eine Datei aus `pages/` wurde direkt aufgerufen (alter Link oder Lesezeichen). Stattdessen `http://localhost/fitfuerinfo/index.php?seite=<name>` verwenden. |
| **Apache startet nicht** | Port 80 ist belegt (z. B. Skype, IIS, VMware). In `<XAMPP>\apache\conf\httpd.conf` `Listen 80` in `Listen 8080` ändern; dann `http://localhost:8080/fitfuerinfo/` aufrufen. |
| **„Datenbankverbindung fehlgeschlagen“** | MySQL läuft nicht (Control Panel) oder die Datenbank fehlt: `einrichtung.php` aufrufen. |
| **Umlaute kaputt** (z. B. „Ã¼“ statt „ü“) | `seed.sql` wurde von Hand nicht als UTF-8 importiert. `einrichtung.php` aufrufen (oder beim Import Zeichencodierung **utf-8** wählen und `schema.sql` und `seed.sql` erneut importieren). |
| **Anmeldung klappt nicht** | Passwort ist `test1`; `sabine` ist absichtlich gesperrt. |

## Weitere Unterlagen

Projektdokumentation und Bedienungsanleitung: `tests\docs\`.
