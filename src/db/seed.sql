USE fitfuerinfo;

-- Erzwingt UTF-8 fuer diese Verbindung, unabhaengig davon, welche
-- Verbindungskodierung der importierende Client (z. B. phpMyAdmin oder
-- die mysql-Kommandozeile) sonst verwenden wuerde. Ohne das wurden
-- Umlaute beim Import als kaputte Mehrfachbyte-Zeichen abgespeichert
-- (z. B. "Übungen" -> "├£bungen").
SET NAMES utf8mb4;

-- ============================================================
-- Testdaten
-- Passwort für die vier bestehenden Testkonten: "test1"
-- (Hash von password_hash('test1', PASSWORD_DEFAULT))
--
-- Der fünfte Testbenutzer "neuling" hat noch KEIN Passwort. Er dient zum
-- Ausprobieren von src/web/pages/passwort_setzen.php mit dem Freischaltcode
-- "START123" (gültig bis 2027-12-31).
-- ============================================================

INSERT INTO benutzer (name, email, passwort_hash, rolle, aktiv, freischaltcode, code_gueltig_bis) VALUES
  ('admin',   'admin@fitfuerinfo.local',  '$2y$10$ITlV2jXJ5DzmJCCEK3F7iObgbMpW4E.BDZrcDEwJNsuo77XlbDZIW', 'admin', 1, NULL, NULL),
  ('lena',    'lena@fitfuerinfo.local',   '$2y$10$ITlV2jXJ5DzmJCCEK3F7iObgbMpW4E.BDZrcDEwJNsuo77XlbDZIW', 'mitarbeiter', 1, NULL, NULL),
  ('markus',  'markus@fitfuerinfo.local', '$2y$10$ITlV2jXJ5DzmJCCEK3F7iObgbMpW4E.BDZrcDEwJNsuo77XlbDZIW', 'mitarbeiter', 1, NULL, NULL),
  ('sabine',  'sabine@fitfuerinfo.local', '$2y$10$ITlV2jXJ5DzmJCCEK3F7iObgbMpW4E.BDZrcDEwJNsuo77XlbDZIW', 'mitarbeiter', 0, NULL, NULL),
  ('neuling', NULL,                       NULL, 'mitarbeiter', 1, 'START123', '2027-12-31 23:59:59');

INSERT INTO software (name) VALUES
  ('VirtualBox'), ('Ubuntu'), ('Kali Linux'), ('Wireshark'), ('Office'), ('Visual Studio Code');

INSERT INTO raum (name, arbeitsplaetze) VALUES
  ('Raum A', 16), ('Raum B', 10), ('Raum C', 20);

INSERT INTO raum_software (raum_id, software_id) VALUES
  (1,1),(1,2),(1,5),(1,6),
  (2,1),(2,3),(2,4),
  (3,5),(3,6);

INSERT INTO kurs (titel, beschreibung, max_teilnehmer, ersteller_id) VALUES
  ('Linux-Grundlagen',      'Einführung in Linux: Dateisystem, Shell und Benutzerverwaltung anhand von Ubuntu.', 12, 2),
  ('IT-Sicherheit Praxis',  'Praktische Übungen zu Netzwerksicherheit mit Kali Linux und Wireshark.',           8, 3),
  ('Office für Einsteiger', 'Grundlagen von Textverarbeitung, Tabellenkalkulation und Präsentation.',          16, 2),
  ('Python für Admins',     'Automatisierung von Verwaltungsaufgaben mit Python in Visual Studio Code.',       10, 3);

INSERT INTO kurs_eigentuemer (kurs_id, benutzer_id) VALUES
  (1,2), (2,3), (3,2), (3,3), (4,3);

INSERT INTO kurs_software (kurs_id, software_id) VALUES
  (1,1),(1,2),
  (2,3),(2,4),
  (3,5),
  (4,6);

INSERT INTO raum_bearbeiter (raum_id, benutzer_id) VALUES
  (1,2), (2,3);

-- ------------------------------------------------------------
-- Buchungen relativ zum Importdatum, damit der Kalender bei jeder
-- Vorfuehrung gefuellt ist (statt fester Daten, die irgendwann alle in
-- der Vergangenheit liegen).
--
-- @mo = der naechste Montag nach dem Importtag (wird auch an einem Montag
-- importiert, ist es der Montag der FOLGENDEN Woche - so liegen alle
-- Buchungen sicher in der Zukunft). @vw = Montag der vorletzten Woche,
-- liegt also immer komplett in der Vergangenheit ("vergangen"-Darstellung).
--
-- Alle Buchungen erfuellen die fuenf Buchungsregeln aus
-- buchung_pruefen(): Mo-Fr, 07:00-20:00, halbe Stunden, keine
-- Ueberschneidung pro Raum und pro Kurs, Software und Plaetze passen:
--   Linux-Grundlagen (12, VirtualBox+Ubuntu)  -> nur Raum A
--   IT-Sicherheit Praxis (8, Kali+Wireshark)  -> nur Raum B
--   Office fuer Einsteiger (16, Office)       -> Raum A oder C
--   Python fuer Admins (10, VS Code)          -> Raum A oder C
-- Gebucht hat jeweils ein Eigentuemer des Kurses (lena: 1 und 3,
-- markus: 2, 3 und 4). Am kommenden Montag liegen 5 Buchungen, damit die
-- Monatsansicht auch "+N weitere" zeigt.
-- ------------------------------------------------------------
SET @mo = DATE_ADD(CURDATE(), INTERVAL 7 - WEEKDAY(CURDATE()) DAY);
SET @vw = DATE_SUB(@mo, INTERVAL 14 DAY);

INSERT INTO buchung (raum_id, kurs_id, benutzer_id, start, ende) VALUES
  -- kommende Woche: Montag
  (1, 1, 2, TIMESTAMP(@mo, '08:00:00'), TIMESTAMP(@mo, '11:30:00')),
  (2, 2, 3, TIMESTAMP(@mo, '08:00:00'), TIMESTAMP(@mo, '10:00:00')),
  (3, 3, 2, TIMESTAMP(@mo, '09:00:00'), TIMESTAMP(@mo, '12:00:00')),
  (3, 4, 3, TIMESTAMP(@mo, '13:00:00'), TIMESTAMP(@mo, '15:30:00')),
  (1, 4, 3, TIMESTAMP(@mo, '16:00:00'), TIMESTAMP(@mo, '18:00:00')),
  -- Dienstag
  (1, 1, 2, TIMESTAMP(@mo + INTERVAL 1 DAY, '08:00:00'), TIMESTAMP(@mo + INTERVAL 1 DAY, '12:00:00')),
  (2, 2, 3, TIMESTAMP(@mo + INTERVAL 1 DAY, '13:00:00'), TIMESTAMP(@mo + INTERVAL 1 DAY, '16:30:00')),
  -- Mittwoch
  (3, 3, 3, TIMESTAMP(@mo + INTERVAL 2 DAY, '08:30:00'), TIMESTAMP(@mo + INTERVAL 2 DAY, '11:00:00')),
  (1, 4, 3, TIMESTAMP(@mo + INTERVAL 2 DAY, '12:00:00'), TIMESTAMP(@mo + INTERVAL 2 DAY, '14:00:00')),
  (2, 2, 3, TIMESTAMP(@mo + INTERVAL 2 DAY, '14:30:00'), TIMESTAMP(@mo + INTERVAL 2 DAY, '17:00:00')),
  -- Donnerstag
  (1, 1, 2, TIMESTAMP(@mo + INTERVAL 3 DAY, '10:00:00'), TIMESTAMP(@mo + INTERVAL 3 DAY, '13:00:00')),
  -- Freitag
  (1, 3, 2, TIMESTAMP(@mo + INTERVAL 4 DAY, '08:00:00'), TIMESTAMP(@mo + INTERVAL 4 DAY, '10:00:00')),
  -- vorletzte Woche (vergangen)
  (1, 1, 2, TIMESTAMP(@vw, '08:00:00'), TIMESTAMP(@vw, '12:00:00')),
  (2, 2, 3, TIMESTAMP(@vw + INTERVAL 1 DAY, '13:00:00'), TIMESTAMP(@vw + INTERVAL 1 DAY, '17:00:00')),
  (3, 3, 2, TIMESTAMP(@vw + INTERVAL 3 DAY, '09:00:00'), TIMESTAMP(@vw + INTERVAL 3 DAY, '11:00:00'));

-- ============================================================
-- Nützliche Abfragen für die PHP-Umsetzung
-- ============================================================

-- Passende Räume für Kurs :kurs_id
-- (genug Plätze UND alle benötigten Softwarepakete vorhanden)
-- SELECT r.*
-- FROM raum r
-- JOIN kurs k ON k.id = :kurs_id
-- WHERE r.arbeitsplaetze >= k.max_teilnehmer
--   AND NOT EXISTS (
--     SELECT 1 FROM kurs_software ks
--     WHERE ks.kurs_id = k.id
--       AND ks.software_id NOT IN (
--         SELECT rs.software_id FROM raum_software rs WHERE rs.raum_id = r.id
--       )
--   );

-- Überschneidungsprüfung vor dem Speichern einer Buchung
-- SELECT COUNT(*) FROM buchung
-- WHERE raum_id = :raum_id
--   AND start < :neu_ende
--   AND ende  > :neu_start;
-- -> Ergebnis muss 0 sein, sonst Buchung ablehnen

-- Wochenbelegung
-- SELECT b.start, b.ende, r.name AS raum, k.titel AS kurs, u.name AS gebucht_von
-- FROM buchung b
-- JOIN raum r ON r.id = b.raum_id
-- JOIN kurs k ON k.id = b.kurs_id
-- JOIN benutzer u ON u.id = b.benutzer_id
-- WHERE b.start >= :wochenstart AND b.start < :wochenende
-- ORDER BY b.start, r.name;
