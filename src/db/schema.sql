-- ============================================================
-- FitFuerInfo – Kurs- und Raumverwaltung
-- Datenbankschema für MySQL / MariaDB (XAMPP 5.6, phpMyAdmin)
-- Import: phpMyAdmin -> Importieren -> diese Datei auswählen
-- ============================================================

CREATE DATABASE IF NOT EXISTS fitfuerinfo
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fitfuerinfo;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS buchung, raum_bearbeiter, raum_software, kurs_software,
                     kurs_eigentuemer, raum, kurs, software, benutzer;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Stammtabellen
-- ------------------------------------------------------------

CREATE TABLE benutzer (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name              VARCHAR(50)  NOT NULL,
  email             VARCHAR(100) NULL,
  passwort_hash     VARCHAR(255) NULL,
  rolle             ENUM('admin','mitarbeiter') NOT NULL DEFAULT 'mitarbeiter',
  aktiv             TINYINT(1)   NOT NULL DEFAULT 1,
  freischaltcode    VARCHAR(32)  NULL,
  code_gueltig_bis  DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_benutzer_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE software (
  id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name  VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_software_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kurs (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titel            VARCHAR(100) NOT NULL,
  beschreibung     TEXT NULL,
  max_teilnehmer   INT UNSIGNED NOT NULL,
  ersteller_id     INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY idx_kurs_ersteller (ersteller_id),
  CONSTRAINT fk_kurs_ersteller FOREIGN KEY (ersteller_id)
    REFERENCES benutzer (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE raum (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name            VARCHAR(50)  NOT NULL,
  arbeitsplaetze  INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_raum_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Zwischentabellen (n:m)
-- ------------------------------------------------------------

CREATE TABLE kurs_eigentuemer (
  kurs_id      INT UNSIGNED NOT NULL,
  benutzer_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (kurs_id, benutzer_id),
  CONSTRAINT fk_ke_kurs FOREIGN KEY (kurs_id)
    REFERENCES kurs (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ke_benutzer FOREIGN KEY (benutzer_id)
    REFERENCES benutzer (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kurs_software (
  kurs_id      INT UNSIGNED NOT NULL,
  software_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (kurs_id, software_id),
  CONSTRAINT fk_ks_kurs FOREIGN KEY (kurs_id)
    REFERENCES kurs (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ks_software FOREIGN KEY (software_id)
    REFERENCES software (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE raum_software (
  raum_id      INT UNSIGNED NOT NULL,
  software_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (raum_id, software_id),
  CONSTRAINT fk_rs_raum FOREIGN KEY (raum_id)
    REFERENCES raum (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_rs_software FOREIGN KEY (software_id)
    REFERENCES software (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE raum_bearbeiter (
  raum_id      INT UNSIGNED NOT NULL,
  benutzer_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (raum_id, benutzer_id),
  CONSTRAINT fk_rb_raum FOREIGN KEY (raum_id)
    REFERENCES raum (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_rb_benutzer FOREIGN KEY (benutzer_id)
    REFERENCES benutzer (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Buchungen
-- ------------------------------------------------------------

CREATE TABLE buchung (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  raum_id      INT UNSIGNED NOT NULL,
  kurs_id      INT UNSIGNED NOT NULL,
  benutzer_id  INT UNSIGNED NOT NULL,
  start        DATETIME NOT NULL,
  ende         DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_buchung_raum_start (raum_id, start),
  KEY idx_buchung_kurs (kurs_id),
  KEY idx_buchung_benutzer (benutzer_id),
  CONSTRAINT fk_b_raum FOREIGN KEY (raum_id)
    REFERENCES raum (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_b_kurs FOREIGN KEY (kurs_id)
    REFERENCES kurs (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_b_benutzer FOREIGN KEY (benutzer_id)
    REFERENCES benutzer (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
