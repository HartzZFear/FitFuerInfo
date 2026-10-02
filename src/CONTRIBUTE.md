# Projekt-Setup FitFuerInfo (Mission12B)

Anleitung für die Einrichtung des Arbeitsplatzes. Einmal komplett durchgehen,
danach reicht der Abschnitt "Täglicher Ablauf" am Ende.

Aufwand: ca. 30–45 Minuten.

---

## 1. XAMPP installieren

**Wichtig: Es muss Version 5.6.36 sein, nicht die neueste.**

Der Schulrechner hat XAMPP 5.6.36 mit PHP 5.6. Wenn wir zu Hause auf PHP 8
entwickeln, läuft bei der Abgabe auf dem Schulrechner nichts mehr. Deshalb
arbeiten alle drei mit derselben Version.

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
git clone https://github.com/HartzZFear/Mission12B.git fitfuerinfo
cd fitfuerinfo
git config user.name "DEIN-GITHUB-NAME"
git config user.email "deine@mail.de"
```

Das Repo liegt jetzt unter `C:\xampp\htdocs\fitfuerinfo`. Die eigentliche
Anwendung liegt darin im Ordner `FitFuerInfo\` und ist später im Browser
unter `http://localhost/fitfuerinfo/FitFuerInfo` erreichbar.

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
`C:\xampp\htdocs\fitfuerinfo\FitFuerInfo\src\db\`.

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
`benutzer` hat 5 Zeilen, `buchung` hat 7.

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

Die Datei `src/web/config.php` enthält die lokalen Zugangsdaten zur
Datenbank. Sie liegt **nicht** im Repo, weil sie bei jedem anders aussehen
kann. Im Repo liegt nur die Vorlage `src/web/config.example.php`.

In der Eingabeaufforderung:

```
cd C:\xampp\htdocs\fitfuerinfo\FitFuerInfo
copy src\web\config.example.php src\web\config.php
```

Bei einer frischen XAMPP-Installation passen die Standardwerte
(Benutzer `root`, kein Passwort). Nur wer sein MySQL abgesichert hat, trägt
in `src/web/config.php` seine eigenen Daten ein.

**Selbsttest aufrufen:** `http://localhost/fitfuerinfo/src/web/tst/test.php`

Die Seite prüft der Reihe nach:

1. PHP-Version ist 5.6.x
2. PDO-MySQL-Treiber ist geladen
3. `src/web/config.php` ist vorhanden
4. Verbindung zur Datenbank steht
5. Alle neun Tabellen sind da
6. Testdaten sind importiert
7. `password_verify('test1', ...)` funktioniert gegen den Admin-Hash

**Wenn alle sieben Zeilen auf OK stehen, ist die Einrichtung fertig.** Steht
irgendwo FEHLER, sagt die Spalte "Ergebnis", was zu tun ist.

`test.php` ist nur ein Hilfsmittel für die Einrichtung und fliegt vor der
Abgabe wieder raus.

---

## 6. Zugang und Passwörter

Der Systemverwalter (Rolle `admin`) darf Zugang gewähren und entziehen, darf
aber zu keinem Zeitpunkt ein Passwort kennen – auch nicht das erste. Deshalb
läuft die Einrichtung eines Kontos in zwei Schritten:

1. **Admin legt das Konto an** (Benutzername, optional E-Mail, Rolle). Ein
   Passwort wird dabei nicht vergeben. Stattdessen erzeugt das System einen
   einmaligen **Freischaltcode** (8 Zeichen, siehe `erzeuge_freischaltcode()`
   in `src/web/auth.php`) und zeigt ihn dem Admin an.
2. **Mitarbeiter öffnet `src/web/pages/passwort_setzen.php`**, gibt seinen
   Benutzernamen und den Freischaltcode ein und vergibt sein Passwort selbst.
   Der Code wird dabei entwertet (nur einmal gültig) und funktioniert danach
   nicht mehr.
3. Ab da läuft die Anmeldung ganz normal über `src/web/pages/login.php` mit
   Benutzername und Passwort.

Passwortregel laut Aufgabenstellung: mindestens `PW_MIN_LAENGE` (Standard: 4)
Zeichen, darunter mindestens ein Kleinbuchstabe und mindestens eine Ziffer.
Geprüft wird das über `pruefe_passwortregeln()` in `src/web/auth.php`.

**Zum Ausprobieren** liegt in `src/db/seed.sql` ein fünfter Testbenutzer
bereit, der noch kein Passwort hat:

| Benutzer  | Freischaltcode | Gültig bis  |
|-----------|----------------|-------------|
| `neuling` | `START123`     | 2027-12-31  |

Damit lässt sich der komplette Ablauf lokal durchspielen:
`src/web/pages/passwort_setzen.php` öffnen, `neuling` und `START123`
eingeben, ein Passwort vergeben (z. B. `abc1`), danach mit `neuling` und
diesem Passwort auf `src/web/pages/login.php` anmelden. Ein zweiter Versuch
mit demselben Code schlägt danach erwartungsgemäß fehl.

---

## 7. Teamregeln

Diese sechs Punkte sind das, woran Gruppenprojekte sonst scheitern.

**1. Datenbankänderungen immer über `schema.sql`.**
Wer eine Tabelle oder Spalte ändert, ändert **die Datei** und pusht sie –
nicht nur lokal in phpMyAdmin klicken. Die anderen ziehen und importieren
neu. Ohne diese Regel hat nach drei Tagen jeder ein anderes Datenmodell.

Struktur aus der lokalen DB zurück in die Datei schreiben:

```
C:\xampp\mysql\bin\mysqldump -u root --no-data fitfuerinfo > src\db\schema.sql
C:\xampp\mysql\bin\mysqldump -u root --no-create-info fitfuerinfo > src\db\seed.sql
```

**2. SQL immer über `abfrage()`, Ausgaben immer über `h()`.**
Beide Funktionen stehen in `src/web/db.php`:

```php
require_once __DIR__ . '/../db.php';

$kurse = abfrage('SELECT * FROM kurs WHERE ersteller_id = ?', array($id))->fetchAll();
echo h($kurs['titel']);
```

Werte niemals per Stringverkettung ins SQL schreiben – immer als Parameter
übergeben. Damit sind SQL-Injection und XSS von Anfang an erledigt, und wir
können das in der Doku als bewusste Entscheidung beschreiben.

**3. Nur PHP-5.6-Syntax verwenden.**
Nicht erlaubt, weil erst ab PHP 7 verfügbar:
- `??` (Null-Coalescing) → stattdessen `isset($x) ? $x : ''`
- Typdeklarationen wie `function f(int $x): bool`
- Arrow Functions `fn() =>`, `match`, `str_contains()`, `<=>`
- Typed Properties

Erlaubt und erwünscht: `password_hash()` / `password_verify()`, PDO mit
Prepared Statements, Sessions, `[]`-Array-Syntax.

**4. Nie direkt auf `main-prd` arbeiten.**
`main-prd` bleibt immer lauffähig. Gearbeitet wird in Feature-Branches, die
nach `main-dev` gemerged werden:

```
git checkout main-dev
git pull
git checkout -b feature-login
```

**5. `config.php` wird nicht committet.**
Sie steht in der `.gitignore`, weil sie lokale Zugangsdaten enthält. Im Repo
liegt nur `config.example.php` als Vorlage (siehe Abschnitt 5). Wer eine neue
Einstellung braucht, trägt sie **auch in die Vorlage** ein, sonst fehlt sie
bei den anderen.

**6. Vor dem Arbeiten immer `git pull`.**
Kostet fünf Sekunden und erspart die meisten Merge-Konflikte.

---

## 8. Seitenübersicht und Rechte

| Seite | Zweck | Wer darf was |
|---|---|---|
| `src/web/pages/login.php` | Anmelden | jeder mit Benutzername/Passwort |
| `src/web/pages/passwort_setzen.php` | Freischaltcode einlösen, eigenes Passwort setzen | jeder mit gültigem Code |
| `src/web/pages/logout.php` | Abmelden | jeder Eingeloggte |
| `src/web/pages/kurse.php` | Kursliste, Suche, Filter „nur eigene / alle" | Lesen: jeder Eingeloggte. Buttons „bearbeiten"/„löschen" nur bei eigenen Kursen sichtbar |
| `src/web/pages/kurs_bearbeiten.php` | Kurs anlegen (ohne `?id=`) oder bearbeiten (mit `?id=N`) | Anlegen: jeder Mitarbeiter. Bearbeiten: nur Eigentümer des Kurses oder Admin |
| `src/web/pages/kurs_loeschen.php` | Sicherheitsabfrage + Löschen eines Kurses | nur Eigentümer des Kurses oder Admin |
| `src/web/pages/raeume.php` | Raumliste mit Suche und Filtern (Software, Mindestanzahl Arbeitsplätze, „nur meine / alle"; alle kombinierbar) | Lesen: jeder Eingeloggte. „bearbeiten" nur bei Räumen, für die man als Bearbeiter eingetragen ist; „löschen" und der „+"-Knopf nur für den Admin |
| `src/web/pages/raum_bearbeiten.php` | Raum anlegen (ohne `?id=`) oder bearbeiten (mit `?id=N`) | Anlegen: nur Admin. Bearbeiten: Admin und Einträge in `raum_bearbeiter`. Der Checkbox-Block „Bearbeiter" ist nur für den Admin sichtbar |
| `src/web/pages/raum_loeschen.php` | Sicherheitsabfrage + Löschen eines Raums | nur Admin |
| `src/web/pages/buchungen.php` | Belegungsliste mit Filtern (Raum, Kurs, „nur meine", ab Datum) | Lesen: jeder Eingeloggte. Buttons „bearbeiten"/„löschen" nur bei eigenen Buchungen. „+"-Knopf nur, wenn man mindestens einen Kurs buchen darf |
| `src/web/pages/buchung_bearbeiten.php` | Buchung anlegen (ohne `?id=`) oder bearbeiten (mit `?id=N`) | Anlegen: nur für eigene Kurse (Admin: alle). Bearbeiten: nur wer die Buchung angelegt hat, oder Admin |
| `src/web/pages/buchung_loeschen.php` | Sicherheitsabfrage + Löschen einer Buchung | nur wer die Buchung angelegt hat, oder Admin |

**Eigentümer eines Kurses** sind der Ersteller (`kurs.ersteller_id`) und alle
Einträge in `kurs_eigentuemer`. Nur Eigentümer und der Admin dürfen einen
Kurs bearbeiten oder löschen; das wird serverseitig geprüft
(`kurs_darf_verwalten()` in `src/web/kurs_rechte.php`), nicht nur durch
Ein-/Ausblenden der Buttons. Ein Admin kann in `kurs_bearbeiten.php` zusätzlich mehrere
Mitarbeiter als Eigentümer eintragen (Checkbox-Block „Eigentümer", nur für
den Admin sichtbar).

Ein Kurs mit bestehenden Buchungen lässt sich nicht löschen (Fremdschlüssel
`buchung.kurs_id` steht auf `RESTRICT`); `kurs_loeschen.php` prüft das vorher
und zeigt stattdessen einen Hinweis, wie viele Buchungen betroffen sind.

**Räume gehören niemandem.** Die Tabelle `raum` hat bewusst keine Spalte für
einen Ersteller: Räume sind Betriebsmittel der Schule. Anlegen und Löschen
darf deshalb nur der Systemverwalter. Wer die Ausstattung eines Raums pflegen
darf – Arbeitsplätze und Softwarepakete –, steht in `raum_bearbeiter`; der
Admin trägt das in `raum_bearbeiten.php` ein. Geprüft wird das serverseitig in
`raum_darf_bearbeiten()` / `raum_darf_anlegen()` / `raum_darf_loeschen()`
in `src/web/raum_rechte.php`. Ein Bearbeiter bekommt dadurch **keine** Rechte
an den Buchungen des Raums (siehe Abschnitt 9).

Ein Raum mit bestehenden Buchungen lässt sich nicht löschen (Fremdschlüssel
`buchung.raum_id` steht auf `RESTRICT`); `raum_loeschen.php` prüft das vorher
und zeigt stattdessen, wie viele Buchungen betroffen sind. `raum_software` und
`raum_bearbeiter` hängen per `ON DELETE CASCADE` am Raum und verschwinden mit
ihm.

Die Tab-Leiste oben (**Kurse | Räume | Belegung**) ist auf allen Seiten gleich.
„Räume" zeigt auf `raeume.php`, „Belegung" auf `buchungen.php`.

Schlägt eine serverseitige Rechteprüfung fehl, beendet
`zugriff_verweigert_seite()` (in `src/web/kurs_rechte.php`) die Seite mit einer
Meldung. Die Buchungs- und Raumseiten geben ihr Ziel für den Zurück-Link mit
(`zugriff_verweigert_seite($meldung, 'raeume.php', 'Zurück zu den Räumen')`);
ohne Angabe führt der Link zurück zur Kursliste.

---

## 9. Buchungsregeln

Eine Buchung belegt **einen Raum für einen Kurs in einem Zeitraum**. Sie wird
nur gespeichert, wenn **alle fünf Regeln** erfüllt sind. Die Prüfung steht
komplett in `buchung_pruefen()` in `src/web/buchung_logik.php` und läuft immer
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
blendet `buchung_bearbeiten.php` zusätzlich `passende_raeume()` ein – die Räume,
die Software und Plätze für diesen Kurs mitbringen.

**Gleichzeitige Buchungen.** Prüfen und Speichern laufen in **einer
Transaktion**. Vor der Prüfung werden die Zeilen von `raum` und `kurs` (in
dieser Reihenfolge) mit `SELECT ... FOR UPDATE` gesperrt. Ohne die Sperre
könnten zwei parallele Anfragen beide die Prüfung bestehen und sich danach
überschneiden. `raum_bearbeiten.php` sperrt die Zeile des Raums genauso,
bevor es dessen Ausstattung ändert.

**Änderungen am Raum.** Die Regeln 4 und 5 hängen am Raum, nicht nur an der
Buchung: Wer Arbeitsplätze reduziert oder ein Softwarepaket entfernt, kann
damit eine längst gespeicherte Buchung ungültig machen. `raum_bearbeiten.php`
schreibt die Änderung deshalb innerhalb einer Transaktion, prüft mit
`raum_konflikte_mit_buchungen()` (in `src/web/raum_rechte.php`) alle **noch
kommenden** Buchungen des Raums gegen den neuen Stand und macht bei einem
Verstoß ein `rollBack()`. Wie `buchung_pruefen()` sammelt die Prüfung alle
Verstöße ein statt beim ersten abzubrechen. Vergangene Buchungen bleiben als
Historie unberührt und blockieren nichts.

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

git checkout -b feature-XYZ
... arbeiten ...

git status
git add .
git commit -m "Kurze Beschreibung, was gemacht wurde"
git push -u origin feature-XYZ
```

Danach auf GitHub den Pull Request von `feature-XYZ` nach `main-dev`
erstellen. Ein anderer aus dem Team schaut drüber und merged.

`git status` vor dem Commit zeigt, was wirklich reingeht – kurz prüfen, ob
da nichts Unerwartetes dabei ist.

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
| `test.php` meldet "src/web/config.php fehlt" | Vorlage noch nicht kopiert → Abschnitt 5 |
| `test.php` meldet PHP 8.x | Falsche XAMPP-Version installiert → Abschnitt 1 |

---

## Checkliste

- [ ] XAMPP 5.6.36 installiert, Apache und MySQL starten grün
- [ ] `http://localhost/phpmyadmin` erreichbar
- [ ] Git installiert, Repo nach `C:\xampp\htdocs\fitfuerinfo` geklont
- [ ] `git config user.name` und `user.email` gesetzt
- [ ] Personal Access Token erstellt und sicher abgelegt
- [ ] `schema.sql` und `seed.sql` aus `src/db/` importiert, neun Tabellen sichtbar
- [ ] `src/web/config.php` aus der Vorlage erstellt
- [ ] `http://localhost/fitfuerinfo/src/web/tst/test.php` zeigt siebenmal OK
- [ ] Kanban-Board auf Logineo geöffnet, eigene Karten zugewiesen
