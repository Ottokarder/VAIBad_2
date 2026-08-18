-- ============================================================
-- VAIBad 2 - Authentifizierung (TELEKOM Online-Version)
-- ============================================================
-- Legt die Benutzertabelle für das Login-System an.
-- Passwörter werden NICHT im Klartext gespeichert, sondern als
-- bcrypt-Hash über die PHP-Funktion password_hash().
--============================================================

USE vaibad_2;

CREATE TABLE IF NOT EXISTS benutzer (
    id INT AUTO_INCREMENT PRIMARY KEY,
    benutzername VARCHAR(100) NOT NULL UNIQUE,
    passwort_hash VARCHAR(255) NOT NULL,
    aktiv TINYINT(1) NOT NULL DEFAULT 1,
    erstelldatum DATETIME DEFAULT CURRENT_TIMESTAMP,
    letzter_login DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
