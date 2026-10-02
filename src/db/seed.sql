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

INSERT INTO buchung (raum_id, kurs_id, benutzer_id, start, ende) VALUES
  (1, 1, 2, '2026-09-07 08:00:00', '2026-09-07 12:00:00'),
  (1, 1, 2, '2026-09-08 08:00:00', '2026-09-08 12:00:00'),
  (2, 2, 3, '2026-09-08 13:00:00', '2026-09-08 17:00:00'),
  (2, 2, 3, '2026-09-09 13:00:00', '2026-09-09 17:00:00'),
  (3, 3, 2, '2026-09-07 08:00:00', '2026-09-07 12:00:00'),
  (3, 3, 2, '2026-09-09 08:00:00', '2026-09-09 12:00:00'),
  (1, 4, 3, '2026-09-11 08:00:00', '2026-09-11 12:00:00');

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
