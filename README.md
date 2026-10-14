# FitFürInfo

FitFürInfo ist eine Kurs- und Raumverwaltung für eine Weiterbildungseinrichtung:
Mitarbeiter legen Kurse an und buchen dafür Räume, ein Belegungskalender zeigt, wann
welcher Raum belegt ist. Das System prüft dabei automatisch Zeiten, Überschneidungen,
benötigte Software und Platzzahl.

**Voraussetzung:** XAMPP **5.6.36** (PHP 5.6) mit Apache und MySQL. Neuere XAMPP-Versionen
werden nicht unterstützt.
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
   Kontrolle: Es muss die Datei `<XAMPP>\htdocs\fitfuerinfo\src\web\index.php` geben.
   (Beim ZIP aufpassen, dass kein doppelter Ordner wie `fitfuerinfo\FitFuerInfo-main\src`
   entsteht.)
2. **Server starten.** XAMPP Control Panel öffnen, bei **Apache** und **MySQL** auf
   **Start** klicken. Beide müssen grün werden.
3. **Datenbank anlegen.** Im Browser `http://localhost/phpmyadmin` öffnen.
   - Oben auf **Importieren** → Datei `src\db\schema.sql` auswählen → **OK**.
     Das legt die Datenbank `fitfuerinfo` mit allen Tabellen an.
   - Links auf **fitfuerinfo** klicken, dann wieder **Importieren** →
     `src\db\seed.sql` → **OK**. Das lädt die Testdaten.
   - Zeichencodierung beim Import auf **utf-8** lassen (Standard).
4. **Konfiguration anlegen.** Im Ordner `src\web` die Datei `config.example.php`
   kopieren und die Kopie `config.php` nennen. Darin prüfen:
   - `BASE_URL` steht auf `'/fitfuerinfo'`
   - `DEBUG` für Abgabe und Präsentation auf `false` setzen
   - Datenbank-Benutzer `root` ohne Passwort passt bei einer frischen XAMPP-Installation.
5. **Aufrufen:** `http://localhost/fitfuerinfo/src/web/` – es erscheint die Anmeldeseite.

## Testzugänge

| Benutzer | Rolle       | Passwort | Hinweis |
|----------|-------------|----------|---------|
| admin    | Admin       | `test1`  | sieht zusätzlich den Tab „Benutzer“ |
| lena     | Mitarbeiter | `test1`  | |
| markus   | Mitarbeiter | `test1`  | |
| sabine   | Mitarbeiter | `test1`  | **gesperrt** – Anmeldung schlägt absichtlich fehl |
| neuling  | Mitarbeiter | –        | hat noch kein Passwort: auf der Anmeldeseite „Passwort vergessen?“ → Benutzername `neuling`, Freischaltcode `START123`, eigenes Passwort wählen (z. B. `abc1`) |

Die Testbuchungen werden beim Import relativ zum heutigen Datum angelegt (kommende Woche
und vorletzte Woche). Wer den Kalender vor einer Präsentation wieder frisch haben will,
importiert `schema.sql` und `seed.sql` einfach noch einmal.

## Selbsttest

`http://localhost/fitfuerinfo/src/web/tst/test.php` prüft PHP-Version, Datenbanktreiber,
`config.php`, Datenbankverbindung, Tabellen, Testdaten und Passwortprüfung. Stehen alle
Zeilen auf OK, ist die Installation fertig. Bei FEHLER steht daneben, was zu tun ist.

## Häufige Fehler

| Problem | Ursache / Lösung |
|---------|------------------|
| **404 Not Found** | Projekt liegt im falschen Ordner oder die URL stimmt nicht. Es muss `<XAMPP>\htdocs\fitfuerinfo\src\web\index.php` geben und die Adresse `http://localhost/fitfuerinfo/src/web/` lauten. |
| **„Fehler: src/web/config.php fehlt“** | Schritt 4 vergessen: `config.example.php` nach `config.php` kopieren. |
| **Apache startet nicht** | Port 80 ist belegt (z. B. Skype, IIS, VMware). In `<XAMPP>\apache\conf\httpd.conf` `Listen 80` in `Listen 8080` ändern; dann `http://localhost:8080/fitfuerinfo/src/web/` aufrufen. |
| **„Datenbankverbindung fehlgeschlagen“** | MySQL läuft nicht (Control Panel) oder `schema.sql` wurde nicht importiert. |
| **Umlaute kaputt** (z. B. „Ã¼“ statt „ü“) | `seed.sql` wurde nicht als UTF-8 importiert. Beim Import Zeichencodierung **utf-8** wählen, dann `schema.sql` und `seed.sql` erneut importieren. |
| **Anmeldung klappt nicht** | Passwort ist `test1`; `sabine` ist absichtlich gesperrt. |

## Hinweis zu den Skripten

`init.ps1`, `start.ps1` und `end.ps1` im Hauptordner sind **veraltet** (sie erwarten eine
alte Ordnerstruktur und einen anderen XAMPP-Pfad) und werden nicht verwendet. Bitte nur die
Installation von Hand oben benutzen.

Weitere Unterlagen: `docs\` (Projektdokumentation, Bedienungsanleitung),
Entwickler-Infos: `src\CONTRIBUTE.md`.
