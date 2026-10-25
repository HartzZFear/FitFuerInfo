# Projekt-Setup FitFürInfo (Mission12B)

Anleitung für die Einrichtung des Arbeitsplatzes und Infos für Entwickler. Einmal komplett
durchgehen, danach reicht der Abschnitt "Täglicher Ablauf" am Ende. Die Kurzfassung der
Installation steht in der `README.md`, die verbindlichen Regeln für Änderungen am Code in
der `CLAUDE.md` (beide im Hauptordner).

Aufwand: ca. 30–45 Minuten.

---

## 1. XAMPP installieren

**Wichtig: Es muss Version 5.6 sein (Schulrechner: 5.6.36), nicht die neueste.**

Der Schulrechner hat XAMPP 5.6.36 mit PHP 5.6. Wenn wir zu Hause auf PHP 8
entwickeln, läuft bei der Abgabe auf dem Schulrechner nichts mehr. Deshalb
arbeiten alle mit PHP 5.6 (getestet auch mit XAMPP 5.6.40).

**Download:**
`sourceforge.net/projects/xampp/files/XAMPP Windows/5.6.36/`
→ Datei `xampp-win32-5.6.36-0-VC11-installer.exe`

Für 5.6 gibt es nur die 32-Bit-Variante. Die läuft auf 64-Bit-Windows
problemlos.

**Installation:**

1. Installer starten. Die UAC-Warnung mit OK bestätigen – die kommt immer.
2. Installationspfad: **`C:\xampp` beibehalten**, nicht nach
   `C:\Program Files (x86)` installieren. Dort fehlen Schreibrechte und
   Apache kann keine Dateien anlegen.
3. Den Vorschlag, UAC per msconfig abzuschalten, ignorieren. Unnötig.
4. Bei den Komponenten reichen Apache, MySQL und phpMyAdmin.

**Prüfen, ob es läuft:**

1. XAMPP Control Panel öffnen, bei **Apache** und **MySQL** auf Start klicken.
   Beide müssen grün hinterlegt sein.
2. Im Browser `http://localhost/dashboard` aufrufen → XAMPP-Seite erscheint.
3. Im Browser `http://localhost/phpmyadmin` aufrufen → phpMyAdmin erscheint.

**Wenn Apache nicht startet:** Meist belegt ein anderes Programm Port 80
(Skype, IIS, VMware). Im Control Panel steht die Ursache im Log. Fix: in
`C:\xampp\apache\conf\httpd.conf` `Listen 80` auf `Listen 8080` ändern, dann
läuft alles unter `http://localhost:8080/`.

**PHP-Version kontrollieren:** Im Control Panel neben Apache auf "Config" →
"PHP (php.ini)". Oben in der Titelzeile oder über `http://localhost/dashboard`
muss PHP 5.6.x stehen.

---

## 2. Git installieren und Repo klonen

**Git installieren:** `git-scm.com` → Download for Windows. Bei der
Installation alle Standardeinstellungen durchklicken.

**Repo klonen** – Eingabeaufforderung öffnen (Windows-Taste, "cmd" eingeben):

```
cd C:\xampp\htdocs
git clone https://github.com/HartzZFear/FitFuerInfo.git fitfuerinfo
cd fitfuerinfo
git config user.name "DEIN-GITHUB-NAME"
git config user.email "deine@mail.de"
```

Das Repo liegt jetzt unter `C:\xampp\htdocs\fitfuerinfo`. Der Hauptordner ist
zugleich der Webroot: Die Anwendung ist im Browser unter
`http://localhost/fitfuerinfo/` erreichbar.

**Nicht als ZIP herunterladen.** Beim ZIP fehlt der `.git`-Ordner. Ohne den
kann man nicht committen, nicht pushen und sieht keine Änderungen der
anderen. Nur der Klon funktioniert.

---

## 3. GitHub-Zugang einrichten (Personal Access Token)

Beim ersten Push fragt Git nach Anmeldedaten. **Das GitHub-Passwort
funktioniert dort nicht mehr**, es wird ein Token gebraucht. Jeder erstellt
sich einen eigenen.

1. GitHub öffnen → Profilbild oben rechts → **Settings**
   (Account-Settings, nicht die Repo-Settings)
2. Linke Seitenleiste ganz nach unten → **Developer settings**
3. **Personal access tokens** → **Tokens (classic)**
4. Rechts oben **Generate new token** → **Generate new token (classic)**
5. Ausfüllen:
   - Note: `Mission12B lokal`
   - Expiration: 90 days
   - Scopes: nur den obersten Haken bei **`repo`** setzen
6. Unten **Generate token**
7. **Der Token wird nur einmal angezeigt.** Sofort kopieren und sicher
   ablegen. Nicht ins Repo committen, nicht in WhatsApp schicken.

Beim ersten `git push`:
- Username: dein GitHub-Benutzername
- Password: der Token (beginnt mit `ghp_`)

Windows merkt sich das danach. Falls man sich vertippt und der Push
fehlschlägt, merkt sich Windows die falschen Daten: Systemsteuerung →
Anmeldeinformationsverwaltung → Windows-Anmeldeinformationen → Eintrag
`git:https://github.com` löschen, dann neu pushen.

**Alternative ohne Token:** GitHub Desktop installieren und einmal per
Browser einloggen. Clone, Commit und Push laufen dann per Klick. Für den
Einstieg völlig in Ordnung.

---

## 4. Datenbank einrichten

Jeder hat seine **eigene lokale Datenbank**. Es gibt keine zentrale DB auf
einem Rechner – das wäre bei der Abgabe auf dem Schulrechner nicht
erreichbar. Synchronisiert wird über die SQL-Dateien im Repo.

Die beiden Dateien liegen nach dem Klonen bereits unter
`C:\xampp\htdocs\fitfuerinfo\src\db\`.

**Import:**

1. `http://localhost/phpmyadmin` öffnen
2. Reiter **Importieren** → `src/db/schema.sql` auswählen → OK
   (legt die Datenbank `fitfuerinfo` mit allen Tabellen an)
3. Links in der Baumansicht **`fitfuerinfo` anklicken**
4. Wieder **Importieren** → `src/db/seed.sql` → OK (Testdaten)

**Diese Meldungen sind normal und kein Fehler:**
- `#1051 Unbekannte Tabelle` – kommt von `DROP TABLE IF EXISTS`. Bei einer
  frischen DB gibt es die Tabellen noch nicht.
- `#1046 Keine Datenbank ausgewählt` – löst sich durch das `USE fitfuerinfo`
  direkt danach auf.

**Kontrolle:** Links auf `fitfuerinfo` klicken. Es müssen **neun Tabellen**
da sein: `benutzer`, `buchung`, `kurs`, `kurs_eigentuemer`, `kurs_software`,
`raum`, `raum_bearbeiter`, `raum_software`, `software`.
`benutzer` hat 5 Zeilen, `buchung` hat 15. Die Buchungen werden relativ zum
Importdatum angelegt (kommende Woche und vorletzte Woche), der Kalender ist
also nach jedem Import gefüllt.

**Testkonten** (Passwort für die ersten vier: `test1`):

| Benutzer  | Rolle       | aktiv | Passwort              |
|-----------|-------------|-------|------------------------|
| admin     | admin       | ja    | `test1`                |
| lena      | mitarbeiter | ja    | `test1`                |
| markus    | mitarbeiter | ja    | `test1`                |
| sabine    | mitarbeiter | nein  | `test1`                |
| neuling   | mitarbeiter | ja    | noch keins – siehe Abschnitt 6 |

---

## 5. Konfiguration anlegen und Selbsttest

Die Datei `config/config.php` enthält die rechnerspezifischen Werte
(Datenbankzugang, `BASE_URL`, `DEBUG`). Sie liegt **nicht** im Repo, weil sie
bei jedem anders aussehen kann. Im Repo liegt nur die Vorlage
`config/config.example.php`. Was für alle gleich ist (Liste der erlaubten
Seiten, Versionsnummer für CSS/JS, Passwort-Mindestlänge), steht in
`config/app.php` und kommt über Git.

In der Eingabeaufforderung:

```
cd C:\xampp\htdocs\fitfuerinfo
copy config\config.example.php config\config.php
```

Bei einer frischen XAMPP-Installation passen die Standardwerte
(Benutzer `root`, kein Passwort). Nur wer sein MySQL abgesichert hat, trägt
in `config/config.php` seine eigenen Daten ein. Die Vorlage steht auf
`DEBUG = false` (richtig für Abgabe und Präsentation). **Zum Entwickeln** in
der eigenen `config.php` `DEBUG = true` setzen: Dann stehen PHP-Meldungen und
bei einem Fehler die technischen Details direkt auf der Seite.

**Selbsttest aufrufen:** `http://localhost/fitfuerinfo/tests/test.php`

Die Seite prüft der Reihe nach:

1. PHP-Version ist 5.6.x
2. PDO-MySQL-Treiber ist geladen
3. `config/config.php` und `config/app.php` sind vorhanden
4. `config.php` enthält keine veralteten Zeilen (`ASSET_VERSION`, `PW_MIN_LAENGE`)
5. Verbindung zur Datenbank steht
6. `ASSET_VERSION` und `ERLAUBTE_SEITEN` stehen in `config/app.php`, zu jeder
   erlaubten Seite gibt es eine Datei in `pages/`
7. Alle neun Tabellen sind da, Testdaten sind importiert
8. `password_verify('test1', ...)` funktioniert gegen den Admin-Hash

**Wenn alle Zeilen auf OK stehen, ist die Einrichtung fertig.** Steht
irgendwo FEHLER, sagt die Spalte "Ergebnis", was zu tun ist.

---

## 6. Zugang und Passwörter

Der Systemverwalter (Rolle `admin`) darf Zugang gewähren und entziehen, darf
aber zu keinem Zeitpunkt ein Passwort kennen – auch nicht das erste. Deshalb
läuft die Einrichtung eines Kontos in zwei Schritten:

1. **Admin legt das Konto an** (Benutzername, optional E-Mail). Ein Passwort
   wird dabei nicht vergeben, und das Konto ist immer ein Mitarbeiter (siehe
   Abschnitt 8, „Genau ein Admin“). Stattdessen erzeugt das System einen
   einmaligen **Freischaltcode** (8 Zeichen, siehe `erzeuge_freischaltcode()`
   in `lib/auth.php`) und zeigt ihn dem Admin an.
2. **Mitarbeiter öffnet „Passwort setzen“**
   (`index.php?seite=passwort_setzen`, auf der Anmeldeseite verlinkt), gibt
   seinen Benutzernamen und den Freischaltcode ein und vergibt sein Passwort
   selbst. Der Code wird dabei entwertet (nur einmal gültig) und funktioniert
   danach nicht mehr.
3. Ab da läuft die Anmeldung ganz normal über die Anmeldeseite mit
   Benutzername und Passwort.

Passwortregel laut Aufgabenstellung: mindestens `PW_MIN_LAENGE` (Standard: 4)
Zeichen, darunter mindestens ein Kleinbuchstabe und mindestens eine Ziffer.
Als Kleinbuchstabe zählen `a`–`z` sowie `ä`, `ö`, `ü`, `ß`. Geprüft wird das über
`pruefe_passwortregeln()` in `lib/auth.php`; alle Regexe dort laufen mit dem
`/u`-Modifier, und die Länge wird in Zeichen gezählt, nicht in Bytes (`ä` = 1
Zeichen). Beispiele: `mäuse1` gültig, `MÄUSE1` ungültig (kein Kleinbuchstabe),
`abc` ungültig (zu kurz, keine Ziffer).

**Zum Ausprobieren** liegt in `src/db/seed.sql` ein fünfter Testbenutzer
bereit, der noch kein Passwort hat:

| Benutzer  | Freischaltcode | Gültig bis  |
|-----------|----------------|-------------|
| `neuling` | `START123`     | 2027-12-31  |

Damit lässt sich der komplette Ablauf lokal durchspielen: auf der
Anmeldeseite „Passwort setzen“ öffnen, `neuling` und `START123` eingeben, ein
Passwort vergeben (z. B. `abc1`), danach mit `neuling` und diesem Passwort
anmelden. Ein zweiter Versuch mit demselben Code schlägt danach
erwartungsgemäß fehl.

---

## 7. Teamregeln

Diese sieben Punkte sind das, woran Gruppenprojekte sonst scheitern.

**1. Datenbankänderungen immer über `schema.sql`.**
Wer eine Tabelle oder Spalte ändert, ändert **die Datei** `src/db/schema.sql`
und pusht sie – nicht nur lokal in phpMyAdmin klicken. Die anderen ziehen und
importieren neu. Ohne diese Regel hat nach drei Tagen jeder ein anderes
Datenmodell. Neue oder geänderte Testdaten kommen ebenso von Hand in
`src/db/seed.sql`.

**`seed.sql` nie per `mysqldump` überschreiben.** Die Testbuchungen darin
stehen nicht als feste Daten drin, sondern werden beim Import relativ zum
Importdatum berechnet (Variablen `@mo` und `@vw`: kommende Woche und
vorletzte Woche). Ein Dump würde sie als feste Daten speichern: Nach ein paar
Tagen lägen alle Buchungen in der Vergangenheit und der Kalender wäre leer.
Außerdem gingen die Erklärungen in den Kommentaren verloren. Dasselbe gilt
für `schema.sql` (Kommentare, Reihenfolge der Tabellen) – beide Dateien
werden deshalb nur von Hand geändert.

**2. SQL immer über `abfrage()`, Ausgaben immer über `h()`.**
Beide Funktionen stehen in `lib/db.php` und sind auf jeder Seite verfügbar
(`index.php` bindet `lib/` ein). SQL steht nur in Funktionen in `lib/`, nie
in einer Seite:

```php
// lib/kurs_logik.php
$kurse = abfrage('SELECT * FROM kurs WHERE ersteller_id = ?', array($id))->fetchAll();

// pages/kurse.php
echo h($kurs['titel']);
```

Werte niemals per Stringverkettung ins SQL schreiben – immer als Parameter
übergeben. Damit sind SQL-Injection und XSS von Anfang an erledigt, und wir
können das in der Doku als bewusste Entscheidung beschreiben.

**3. Nur PHP-5.6-Syntax verwenden.**
Nicht erlaubt, weil erst ab PHP 7 verfügbar:
- `??` (Null-Coalescing) → stattdessen `isset($x) ? $x : ''`
- Typdeklarationen wie `function f(int $x): bool`
- Arrow Functions `fn() =>`, `match`, `str_contains()`, `<=>`, `random_bytes()`
- Typed Properties

Erlaubt und erwünscht: `password_hash()` / `password_verify()`, PDO mit
Prepared Statements, Sessions, `[]`-Array-Syntax.

**4. Nie direkt auf `main-dev` oder `main-prd` arbeiten.**
`main-prd` bleibt immer lauffähig. Gearbeitet wird in eigenen Branches von
`main-dev` (`dev-f/<thema>` für neue Funktionen, `refactor/<thema>` für
Umbauten), die per Pull Request nach `main-dev` gemerged werden:

```
git checkout main-dev
git pull
git checkout -b dev-f/login
```

**5. `config.php` wird nicht committet.**
Sie steht in der `.gitignore`, weil sie lokale Zugangsdaten enthält. Im Repo
liegt nur `config/config.example.php` als Vorlage (siehe Abschnitt 5). Wer
eine neue rechnerspezifische Einstellung braucht, trägt sie **auch in die
Vorlage** ein, sonst fehlt sie bei den anderen. Was für alle gleich ist,
gehört in `config/app.php`.

**6. Vor dem Arbeiten immer `git pull`.**
Kostet fünf Sekunden und erspart die meisten Merge-Konflikte.

**7. Jedes neue POST-Formular braucht `csrf_feld()`.**
Direkt nach dem `<form method="post" ...>`-Tag `<?php csrf_feld(); ?>`
einfügen, und die Seite ruft oben – nach `erfordere_login()` –
`csrf_pruefen();` auf. Ohne das lehnt die Seite das Formular nicht ab, und es
ist angreifbar (siehe Abschnitt 8, „CSRF-Schutz").
GET-Formulare (Suche, Filter, Kalender) brauchen kein Token.

---

## 8. Seitenübersicht und Rechte

Alle Seiten laufen über `index.php` (Front-Controller): Aufruf
`index.php?seite=<name>`, die Datei dazu ist `pages/<name>.php`. Erlaubt sind
nur die Namen aus `ERLAUBTE_SEITEN` in `config/app.php`. Die Hinweisseiten
`zugriff_verweigert`, `anfrage_abgelehnt`, `freischaltcode`, `nicht_gefunden`
und `fehler` stehen bewusst nicht in der Liste – sie werden nur aus dem Code
heraus angezeigt.

| Seite (`?seite=`) | Zweck | Wer darf was |
|---|---|---|
| `login` | Anmelden | jeder mit Benutzername/Passwort |
| `passwort_setzen` | Freischaltcode einlösen, eigenes Passwort setzen | jeder mit gültigem Code |
| `logout` | Abmelden | jeder Eingeloggte |
| `profil` | Eigenes Profil: Name, E-Mail, Rolle, Anzahl eigener Kurse, Räume als Bearbeiter, kommende Buchungen. Formular „Passwort ändern“ (aktuelles Passwort, neues zweimal; POST mit CSRF-Token). Bei Erfolg wird ein offener Freischaltcode verworfen und die Session-ID erneuert | jeder Eingeloggte, nur für sich selbst. Name und E-Mail ändert nur der Admin |
| `hilfe` | Bedienungshilfe: Rollen, Kurse, Buchungsregeln, Kalender, erstes Login/Freischaltcode, Passwortregeln. Zahlen (Buchungszeiten, Passwortlänge, Code-Gültigkeit) kommen direkt aus den Konstanten | jeder Eingeloggte |
| `kurse` | Kursliste, Suche, Filter „nur eigene / alle" | Lesen: jeder Eingeloggte. Buttons „bearbeiten"/„löschen" nur bei eigenen Kursen sichtbar |
| `kurs_bearbeiten` | Kurs anlegen (ohne `&id=`) oder bearbeiten (mit `&id=N`) | Anlegen: jeder Mitarbeiter. Bearbeiten: nur Eigentümer des Kurses oder Admin |
| `kurs_loeschen` | Sicherheitsabfrage + Löschen eines Kurses | nur Eigentümer des Kurses oder Admin |
| `raeume` | Raumliste mit Suche und Filtern (Software, Mindestanzahl Arbeitsplätze, „nur meine / alle"; alle kombinierbar) | Lesen: jeder Eingeloggte. „bearbeiten" nur bei Räumen, für die man als Bearbeiter eingetragen ist; „löschen" und der „+"-Knopf nur für den Admin |
| `raum_bearbeiten` | Raum anlegen (ohne `&id=`) oder bearbeiten (mit `&id=N`) | Anlegen: nur Admin. Bearbeiten (Name, Arbeitsplätze, Software): Admin und Einträge in `raum_bearbeiter`. Der Checkbox-Block „Bearbeiter" ist nur für den Admin sichtbar |
| `raum_loeschen` | Sicherheitsabfrage + Löschen eines Raums | nur Admin |
| `belegung` | Belegungskalender: Monat (Raster Mo–Fr), Woche (ein Raum, Halbstunden-Slots 07:00–20:00), Tag (alle Räume nebeneinander). Filter Raum, Kurs, „nur meine"; Blättern und „Heute"; Link „Listenansicht" auf `buchungen` mit denselben Filtern | Lesen: jeder Eingeloggte. Klick auf eine Buchung führt nur zu `buchung_bearbeiten`, wenn `buchung_darf_bearbeiten()` true liefert (eigene bzw. Admin, nicht vergangen). Klick auf einen freien Slot (Woche/Tag) nur, wenn man mindestens einen Kurs buchen darf und der Slot nicht vorbei ist |
| `buchungen` | Belegungsliste mit Filtern (Raum, Kurs, „nur meine", ab Datum); Link „Kalenderansicht" auf `belegung` mit denselben Filtern | Lesen: jeder Eingeloggte. Buttons „bearbeiten"/„löschen" nur bei eigenen Buchungen. „+"-Knopf nur, wenn man mindestens einen Kurs buchen darf |
| `buchung_bearbeiten` | Buchung anlegen (ohne `&id=`) oder bearbeiten (mit `&id=N`) | Anlegen: nur für eigene Kurse (Admin: alle). Bearbeiten: nur wer die Buchung angelegt hat, oder Admin |
| `buchung_loeschen` | Sicherheitsabfrage + Löschen einer Buchung | nur wer die Buchung angelegt hat, oder Admin |
| `benutzer` | Benutzerliste mit Rolle, Status (aktiv / gesperrt / wartet auf Freischaltung), Anzahl Kurse als Eigentümer, Räume als Bearbeiter und kommende Buchungen; Suche (Name/E-Mail), Filter Rolle und Status. Knöpfe „bearbeiten", „sperren"/„entsperren" und „Neuer Freischaltcode" (beide per POST mit Sicherheitsabfrage), „+" zum Anlegen | nur Admin (`erfordere_admin()`); „sperren" fehlt auf der Karte des Admin-Kontos |
| `benutzer_bearbeiten` | Benutzer anlegen (ohne `&id=`) oder bearbeiten (mit `&id=N`): Name, E-Mail (optional), aktiv; die Rolle wird nur angezeigt. **Kein Passwortfeld.** Beim Anlegen wird einmalig ein Freischaltcode angezeigt | nur Admin. Das Admin-Konto kann nicht gesperrt werden (`benutzer_sperre_pruefen()` in `lib/benutzer_logik.php`) |

**Genau ein Admin.** Laut Anforderung gibt es genau einen Systemverwalter
(Gruppenentscheidung). Die Rolle wird deshalb nie über die Oberfläche vergeben
oder entzogen: Neue Benutzer sind immer Mitarbeiter, und beim Bearbeiten wird
die Rolle gar nicht aus dem Formular gelesen – auch eine manipulierte Anfrage
kann sie nicht ändern (`benutzer_speichern()` in `lib/benutzer_logik.php`).
Die einzige Prüfung dazu: Das Admin-Konto kann nicht gesperrt werden, weder
über das Formular noch über die Liste. Benutzer löschen gibt es nicht (siehe
unten).

**Eigentümer eines Kurses** sind der Ersteller (`kurs.ersteller_id`) und alle
Einträge in `kurs_eigentuemer`. Nur Eigentümer und der Admin dürfen einen
Kurs bearbeiten oder löschen; das wird serverseitig geprüft
(`kurs_darf_verwalten()` in `lib/kurs_rechte.php`), nicht nur durch
Ein-/Ausblenden der Buttons. Ein Admin kann in `kurs_bearbeiten` zusätzlich mehrere
Mitarbeiter als Eigentümer eintragen (Checkbox-Block „Eigentümer", nur für
den Admin sichtbar).

Ein Kurs mit bestehenden Buchungen lässt sich nicht löschen (Fremdschlüssel
`buchung.kurs_id` steht auf `RESTRICT`); `kurs_loeschen` prüft das vorher
und zeigt stattdessen einen Hinweis, wie viele Buchungen betroffen sind.

**Räume gehören niemandem.** Die Tabelle `raum` hat bewusst keine Spalte für
einen Ersteller: Räume sind Betriebsmittel der Schule. Anlegen und Löschen
darf deshalb nur der Systemverwalter. Wer einen Raum bearbeiten darf – Name,
Anzahl der Arbeitsplätze und Softwarepakete –, steht in `raum_bearbeiter`; der
Admin trägt das in `raum_bearbeiten` ein. Nur die Liste der Bearbeiter selbst
kann ausschließlich der Admin ändern. Geprüft wird das serverseitig in
`raum_darf_bearbeiten()` / `raum_darf_anlegen()` / `raum_darf_loeschen()`
in `lib/raum_rechte.php`. Ein Bearbeiter bekommt dadurch **keine** Rechte
an den Buchungen des Raums (siehe Abschnitt 9).

Ein Raum mit bestehenden Buchungen lässt sich nicht löschen (Fremdschlüssel
`buchung.raum_id` steht auf `RESTRICT`); `raum_loeschen` prüft das vorher
und zeigt stattdessen, wie viele Buchungen betroffen sind. `raum_software` und
`raum_bearbeiter` hängen per `ON DELETE CASCADE` am Raum und verschwinden mit
ihm.

Die Tab-Leiste oben (**Kurse | Räume | Belegung | Benutzer**) ist auf allen Seiten gleich;
der Tab „Benutzer" erscheint nur für den Admin (`navigation_tabs()` in
`lib/funktionen.php`). Die Kopfzeile (Logo, Tabs, „Profile", „Log Out", darunter das
Wellen-Banner mit „?") steht nur an einer Stelle: `templates/header.php`. Jede Seite setzt
oben nur `$seitentitel` und `$aktiverTab`; Formular- und Hinweisseiten setzen
`$ohneKopfzeile = true` und zeigen stattdessen den Formularkopf (`formular_kopf()` in
`templates/bausteine.php`). Die Farbvariablen (`--teal` usw.) stehen nur in `:root` in
`assets/css/style.css`. Ab 1024 px Breite passt alles in eine Zeile, darunter
rutschen die Tabs in eine eigene Zeile und die Kopfzeile ist nicht mehr fixiert.
„Profile" führt auf `profil`, „?" auf `hilfe`.
„Räume" zeigt auf `raeume`, „Belegung" auf den Kalender `belegung`;
die Listenansicht `buchungen` ist von dort über „Listenansicht" erreichbar.
Jede Raumkarte in `raeume` verlinkt auf die Wochenansicht des Raums, jede
Kurskarte in `kurse` über „Termine" auf die Monatsansicht des Kurses.

Die Logik des Kalenders (Parameter prüfen, Zeitraum berechnen, Buchungen auf
Slots verteilen, Blättern) steht in `lib/kalender_logik.php`. Alle
Buchungen des sichtbaren Zeitraums kommen aus **einer** Abfrage. Die Filter
Kurs und „nur meine" blenden Buchungen nur aus: in Woche und Tag bleibt ein
so belegter Slot als schraffierter Block „belegt" sichtbar und bekommt keinen
Buchen-Link, weil eine Buchung dort an Regel 2 scheitern würde. Ungültige
Parameter (`datum=abc`, `ansicht=xyz`, `raum_id=999`) fallen still auf den
Standard zurück (Woche, heute bzw. am Wochenende der nächste Montag).

**Benutzer werden nicht gelöscht, sondern gesperrt.** `kurs.ersteller_id` und
`buchung.benutzer_id` stehen auf `RESTRICT`, ein Löschen würde also an Kursen
und Buchungen scheitern (und deren Historie zerstören). Ein gesperrter Benutzer
(`aktiv = 0`) kann sich nicht anmelden. Weil `erfordere_login()` das Konto bei
jedem Seitenaufruf frisch aus der Datenbank liest, endet auch eine noch
laufende Sitzung beim nächsten Klick; die Login-Seite zeigt dann „Ihr Konto
wurde gesperrt".

### Freischaltcodes

Der Admin kennt nie ein Passwort – auch nicht das erste. Ablauf:

1. **Anlegen:** Der Admin legt in `benutzer_bearbeiten` einen Benutzer an.
   Er wird ohne Passwort gespeichert (`passwort_hash = NULL`, Status „wartet
   auf Freischaltung").
2. **Code:** Das System erzeugt einen 8-stelligen Freischaltcode
   (`benutzer_code_erzeugen()`, 7 Tage gültig) und zeigt ihn dem Admin genau
   **einmal** groß an (Hinweisseite `freischaltcode`), zusammen mit dem Link auf
   `passwort_setzen`. Danach ist der Code nirgends mehr abrufbar; die Liste zeigt
   nur noch, bis wann er gilt. Der Admin gibt ihn persönlich weiter.
3. **Einlösen:** Der Mitarbeiter gibt in `passwort_setzen` Benutzername,
   Code und sein selbst gewähltes Passwort ein (`code_einloesen()`). Der Code
   wird dabei entwertet.

**Passwort vergessen:** Die Login-Seite verweist dafür an den Systemverwalter
(ein Link führt nur für „Erstes Login oder neuen Code erhalten?" auf
`passwort_setzen`). Der Admin klickt in `benutzer` auf „Neuer
Freischaltcode". Das alte Passwort bleibt gültig, bis der neue Code eingelöst
ist – wer sich doch noch erinnert, ist also nicht ausgesperrt. Ein noch
offener älterer Code wird durch den neuen ungültig.

Die Codes kommen aus `openssl_random_pseudo_bytes()` (kryptografisch sicher,
siehe `erzeuge_freischaltcode()` in `lib/auth.php`), nicht aus `mt_rand()`.

Schlägt eine serverseitige Rechteprüfung fehl, zeigt
`zugriff_verweigert_seite()` (in `lib/funktionen.php`) statt der Seite die
Hinweisseite „Zugriff verweigert“. Die Buchungs-, Raum- und Benutzerseiten
geben ihr Ziel für den Zurück-Link mit
(`zugriff_verweigert_seite($meldung, 'raeume', 'Zurück zu den Räumen')`);
ohne Angabe führt der Link zurück zur Kursliste.

Ruft ein Mitarbeiter eine reine Admin-Seite auf, leitet `erfordere_admin()` auf
`kurse` um und legt die Meldung in `$_SESSION['fehler']` ab. Jede Seite mit
Tab-Leiste ruft oben `session_fehler_anzeigen()` (in `lib/auth.php`) auf:
Die Meldung erscheint einmal und wird danach aus der Session gelöscht.

**Unerwartete Fehler.** Wirft eine Datenbankabfrage einen Fehler, den keine
Funktion selbst abfängt, zeigt `fehler_seite_zeigen()` (in
`lib/funktionen.php`, angemeldet in `index.php`) die Hinweisseite „Es ist ein
Fehler aufgetreten“ – ohne Dateipfade und SQL. Die Details stehen im
Apache-Fehlerlog und, nur bei `DEBUG = true`, zusätzlich auf der Seite.
Textfelder aus der Anfrage werden über `eingabe_text()` gelesen: Ein
manipuliertes Array (z. B. `?suche[]=x`) zählt dann wie ein leeres Feld statt
eine PHP-Warnung auszulösen.

### CSRF-Schutz

Ohne Schutz könnte eine fremde Webseite den Browser eines angemeldeten
Benutzers dazu bringen, unbemerkt ein Formular an uns zu schicken – der
Browser hängt das Session-Cookie automatisch an (z. B. „Benutzer sperren"
oder „Raum löschen"). Deshalb trägt jedes Formular mit `method="post"` ein
geheimes Token mit, das die fremde Seite nicht kennt:

- `csrf_token()` erzeugt pro Session einmal ein Token
  (`bin2hex(openssl_random_pseudo_bytes(32))`).
- `csrf_feld()` gibt es als `<input type="hidden" name="csrf">` aus.
- `csrf_pruefen()` vergleicht bei POST das gesendete mit dem gespeicherten
  Token per `hash_equals()`. Fehlt es oder passt es nicht, zeigt die Seite die
  Hinweisseite „Anfrage abgelehnt“ (HTTP 400); es wird nichts gespeichert. Bei
  GET tut die Funktion nichts, deshalb steht der Aufruf einfach oben auf der
  Seite.

Nach dem Login verwirft `anmelden()` das Token der Login-Seite; die
angemeldete Sitzung bekommt beim nächsten Formular ein neues. Nach dem
Abmelden ist die Session und damit auch das Token weg. Ein Formular, das
über einen Login/Logout hinweg offen war, muss deshalb neu geladen werden.

---

## 9. Buchungsregeln

Eine Buchung belegt **einen Raum für einen Kurs in einem Zeitraum**. Sie wird
nur gespeichert, wenn **alle fünf Regeln** erfüllt sind. Die Prüfung steht
komplett in `buchung_pruefen()` in `lib/buchung_logik.php` und läuft immer
serverseitig – auch wenn das Formular manipuliert wurde.

| # | Regel | Bedingung |
|---|---|---|
| 1 | **Zeit** | Wochentag Mo–Fr; Start ≥ 07:00, Ende ≤ 20:00; Minuten nur `00` oder `30`, Sekunden `00`; Start und Ende am selben Tag; Ende > Start; Start nicht in der Vergangenheit |
| 2 | **Raum frei** | keine andere Buchung mit gleicher `raum_id` und `start < neues_ende AND ende > neuer_start` |
| 3 | **Kurs frei** | keine andere Buchung mit gleicher `kurs_id` im selben Zeitraum (gleiche Bedingung), egal in welchem Raum |
| 4 | **Software** | jede Software aus `kurs_software` des Kurses ist in `raum_software` des Raums vorhanden |
| 5 | **Platz** | `kurs.max_teilnehmer <= raum.arbeitsplaetze` |

Beim **Bearbeiten** wird die eigene Buchungs-ID bei den Regeln 2 und 3
ausgeschlossen (Parameter `$ignorierId`) – sonst würde die Buchung mit sich
selbst kollidieren.

`buchung_pruefen()` bricht **nicht beim ersten Fehler ab**, sondern gibt alle
Verstöße als Array zurück. Das Formular zeigt sie gesammelt an, damit man nicht
dreimal hintereinander abschicken muss. Verletzt eine Buchung Regel 4 oder 5,
blendet `buchung_bearbeiten` zusätzlich `passende_raeume()` ein – die Räume,
die Software und Plätze für diesen Kurs mitbringen.

**Gleichzeitige Buchungen.** Prüfen und Speichern laufen in **einer
Transaktion** (`buchung_speichern()`). Vor der Prüfung werden die Zeilen von
`raum` und `kurs` (in dieser Reihenfolge) mit `SELECT ... FOR UPDATE`
gesperrt. Ohne die Sperre könnten zwei parallele Anfragen beide die Prüfung
bestehen und sich danach überschneiden. `tests/nebenlaeufigkeit.php` führt das
vor (siehe README). `raum_speichern()` sperrt die Zeile des Raums genauso,
bevor es dessen Ausstattung ändert, `kurs_speichern()` die Zeile des Kurses.
Im ganzen Projekt gilt dieselbe Sperrreihenfolge raum → kurs → buchung →
benutzer, damit sich zwei Anfragen nicht gegenseitig blockieren (Deadlock).

**Änderungen am Raum.** Die Regeln 4 und 5 hängen am Raum, nicht nur an der
Buchung: Wer Arbeitsplätze reduziert oder ein Softwarepaket entfernt, kann
damit eine längst gespeicherte Buchung ungültig machen. `raum_speichern()`
(in `lib/raum_logik.php`) schreibt die Änderung deshalb innerhalb einer
Transaktion, prüft mit `raum_konflikte_mit_buchungen()` (in
`lib/raum_rechte.php`) alle **noch kommenden** Buchungen des Raums gegen den
neuen Stand und macht bei einem Verstoß ein `rollBack()`. Wie
`buchung_pruefen()` sammelt die Prüfung alle Verstöße ein statt beim ersten
abzubrechen. Vergangene Buchungen bleiben als Historie unberührt und
blockieren nichts.

**Änderungen am Kurs.** Genauso von der anderen Seite: Wer die Teilnehmerzahl
eines Kurses erhöht oder ihm ein Softwarepaket hinzufügt, kann eine kommende
Buchung ungültig machen. `kurs_speichern()` (in `lib/kurs_logik.php`) sperrt
die Kurszeile, schreibt und prüft dann mit `kurs_konflikte_mit_buchungen()`
(in `lib/kurs_rechte.php`) alle kommenden Buchungen des Kurses; bei einem
Verstoß `rollBack()`. Die Raumzeilen sperrt `kurs_speichern()` bewusst nicht –
sonst wäre die Reihenfolge raum → kurs umgedreht. In beiden Fällen zeigt das
Formular einen Hinweis mit Link auf die betroffenen Buchungen
(`konflikt_hinweis()` in `lib/funktionen.php`).

### Rechte

| Aktion | Wer darf |
|---|---|
| **Anlegen** | Mitarbeiter nur für Kurse, bei denen `kurs_darf_verwalten()` true liefert; Admin für alle Kurse |
| **Bearbeiten** | nur `buchung.benutzer_id == eingeloggter Benutzer`, oder Admin |
| **Löschen** | nur `buchung.benutzer_id == eingeloggter Benutzer`, oder Admin |

Wer einen Raum verwalten darf (`raum_bearbeiter`), hat **bewusst keine
Sonderrechte** auf Buchungen – sonst könnte er fremde Kurstermine umbuchen.

**Vergangene Buchungen** sind nicht mehr bearbeitbar (auch nicht vom Admin);
löschen kann sie nur der Admin. Beides steckt in `buchung_darf_bearbeiten()`
und `buchung_darf_loeschen()`, die auf `buchung_darf_verwalten()` aufsetzen.

Beim Bearbeiten bleibt `buchung.benutzer_id` unverändert – die Buchung gehört
weiterhin dem, der sie angelegt hat.

---

## Täglicher Ablauf

```
cd C:\xampp\htdocs\fitfuerinfo
git checkout main-dev
git pull

git checkout -b dev-f/XYZ
... arbeiten ...

git status
git add <geänderte Dateien>
git commit -m "Kurze Beschreibung, was gemacht wurde"
git push -u origin dev-f/XYZ
```

Danach auf GitHub den Pull Request von `dev-f/XYZ` nach `main-dev`
erstellen. Ein anderer aus dem Team schaut drüber und merged.

`git status` vor dem Commit zeigt, was wirklich reingeht – kurz prüfen, ob
da nichts Unerwartetes dabei ist (z. B. `config/config.php`).

---

## Wenn etwas nicht klappt

| Problem | Ursache / Lösung |
|---|---|
| Apache startet nicht | Port 80 belegt → auf 8080 umstellen (siehe Abschnitt 1) |
| MySQL startet nicht | Port 3306 belegt, meist von einer anderen MySQL-Installation |
| `fatal: pathspec ... did not match` | Datei liegt nicht da, wo Git sie erwartet – Pfad prüfen |
| Push wird abgelehnt | Token falsch oder abgelaufen → Anmeldeinformationsverwaltung leeren |
| Seite zeigt PHP-Code statt Ausgabe | Apache läuft nicht oder Datei liegt außerhalb von `htdocs` |
| `.gitignore` heißt `gitignore.txt` | Windows hängt `.txt` an → im Explorer Dateiendungen einblenden und umbenennen |
| `test.php` meldet "config/config.php fehlt" | Vorlage noch nicht kopiert → Abschnitt 5 |
| `test.php` meldet PHP 8.x | Falsche XAMPP-Version installiert → Abschnitt 1 |
| Seite „Es ist ein Fehler aufgetreten“ | Details im Apache-Fehlerlog (`C:\xampp\apache\logs\error.log`) oder in der eigenen `config.php` `DEBUG = true` setzen |

---

## Checkliste

- [ ] XAMPP 5.6 installiert, Apache und MySQL starten grün
- [ ] `http://localhost/phpmyadmin` erreichbar
- [ ] Git installiert, Repo nach `C:\xampp\htdocs\fitfuerinfo` geklont
- [ ] `git config user.name` und `user.email` gesetzt
- [ ] Personal Access Token erstellt und sicher abgelegt
- [ ] `schema.sql` und `seed.sql` aus `src/db/` importiert, neun Tabellen sichtbar
- [ ] `config/config.php` aus der Vorlage erstellt
- [ ] `http://localhost/fitfuerinfo/tests/test.php` zeigt nur OK
- [ ] Kanban-Board auf Logineo geöffnet, eigene Karten zugewiesen
