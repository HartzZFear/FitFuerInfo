# CONTRIBUTE.md
## 🚀 Projekt FitFuerInfo – Contributor Guide
Dieses Dokument beschreibt alle verbindlichen Regeln für Zusammenarbeit, Code‑Qualität, Branch‑Strategie und Datenbank‑Änderungen im Projekt FitFuerInfo (Mission12B).

## 🧱 Coding‑Standards
PHP-Version & Syntax
Das Projekt verwendet PHP 5.6.

Nicht erlaubt (erst ab PHP 7 verfügbar):

?? Null‑Coalescing

Typdeklarationen (function f(int $x): bool)

Arrow Functions fn() =>

match, str_contains(), <=>

Typed Properties

Erlaubt und erwünscht:

password_hash() / password_verify()

PDO + Prepared Statements

Sessions

[]‑Array‑Syntax

Sicherheit & Best Practices
SQL immer über [abfrage](ca://s?q=Erklaere_abfrage_Funktion) aus src/db.php.

Ausgaben immer über [h()](ca://s?q=Erklaere_h_Funktion) (HTML‑Escaping).

Niemals Werte per Stringverkettung ins SQL schreiben → immer Parameter nutzen.

Dadurch sind SQL‑Injection und XSS von Anfang an ausgeschlossen.

## 🗄️ Datenbank‑Regeln
Änderungen am Datenmodell
Datenbankänderungen ausschließlich über [schema.sql](ca://s?q=Wie_aendere_ich_schema_sql).

Keine Änderungen direkt in phpMyAdmin.

Wer etwas ändert, pusht die aktualisierte Datei – alle anderen importieren neu.

Struktur zurück ins Repo schreiben
bash
C:\xampp\mysql\bin\mysqldump -u root --no-data fitfuerinfo > db\schema.sql
C:\xampp\mysql\bin\mysqldump -u root --no-create-info fitfuerinfo > db\seed.sql

## 🌿 Branch‑Strategie
Grundprinzip
Nie direkt auf main-prd arbeiten.

main-prd bleibt immer lauffähig.

Entwicklung passiert in Feature‑Branches → Merge nach main-dev.

Workflow
``bash
git checkout main-dev
git pull
git checkout -b feature-XYZ

... arbeiten ...
git status
git add .
git commit -m "Kurze Beschreibung"
git push -u origin feature-XYZ
Pull Requests
Nach dem Push PR von feature-XYZ → main-dev.``

Ein Teammitglied reviewed und merged.

## 🔐 Konfigurationsdateien
config.php 
(Wird niemals committet, steht in .gitignore, NICHT entfernen)
Vorlage: config.example.php
Neue Einstellungen müssen auch in die Vorlage eingetragen werden.

## 🔄 Täglicher Ablauf
Vor dem Arbeiten immer...
´´bash
git checkout main-dev
git pull
´´
.. und danach Feature‑Branch erstellen und arbeiten.
**NIEMALS IM MAIN-<Branchname> ARBEITEN!**

## 🛠️ Typische Fehler & Hinweise
**Problem-Ursache / -Lösung**
fatal: pathspec ... did not match	Datei liegt nicht da, wo Git sie erwartet → Pfad prüfen
Push wird abgelehnt	Token falsch/abgelaufen 
→ Windows-Anmeldeinformationen löschen
.gitignore heißt gitignore.txt	Windows hängt .txt an 
→ Dateiendungen einblenden und umbenennen


## ✔️ Contributor‑Checkliste
[] Git installiert
[] Repo geklont
[] git config user.name / user.email gesetzt
[] Vor jeder Arbeit: git pull
[] Feature‑Branch statt direkt auf main-prd
[] SQL‑Änderungen nur über schema.sql
[] config.php niemals committen
[] PR erstellen, Review abwarten