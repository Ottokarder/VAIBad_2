-- ============================================================
-- VAIBad 2 - Authentifizierung (TELEKOM Online-Version)
-- ============================================================
-- Legt die Benutzertabelle fuer das Login-System an.
-- Passwoerter werden NICHT im Klartext gespeichert, sondern als
-- bcrypt-Hash ueber die PHP-Funktion password_hash().
-- ============================================================
--
-- Datenbank: Diese Datei ist fuer den TELEKOM-Server ausgelegt.
-- Die Datenbank heisst dort: HTO01FLQKFNX_4
-- (Beim Ausfuehren ueber phpMyAdmin die entsprechende Datenbank
--  links auswaehlen; die USE-Anweisung kann dann entfallen.)
USE HTO01FLQKFNX_4;

CREATE TABLE IF NOT EXISTS benutzer (
    id INT AUTO_INCREMENT PRIMARY KEY,
    benutzername VARCHAR(100) NOT NULL UNIQUE,
    passwort_hash VARCHAR(255) NOT NULL,
    aktiv TINYINT(1) NOT NULL DEFAULT 1,
    erstelldatum DATETIME DEFAULT CURRENT_TIMESTAMP,
    letzter_login DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
