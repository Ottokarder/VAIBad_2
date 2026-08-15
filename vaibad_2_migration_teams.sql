-- Migrationsskript für bestehende vaibad_2-Datenbanken
-- Führt die Teams-Tabelle sowie die optionale Team-Zuordnung (team_id) für Schwimmer ein.
--
-- Dieses Skript ist idempotent: es kann gefahrlos wiederholt werden.
-- Ein Schwimmer kann einem Team zugeordnet sein, muss aber nicht (team_id ist NULL erlaubt).
--
-- Ausführen mit:
--   mysql -u root vaibad_2 < vaibad_2_migration_teams.sql
-- oder im phpMyAdmin / MySQL-Client als komplettes Skript ausführen.

USE vaibad_2;

-- -------------------------------------------------------------------
-- 1) Tabelle Teams erstellen (falls noch nicht vorhanden)
-- -------------------------------------------------------------------
SET @tbl_exists := (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'Teams');
SET @sql := IF(@tbl_exists = 0,
    'CREATE TABLE Teams (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL, betrag_pro_bahn DECIMAL(10, 2) NOT NULL, `limit` INT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT "Tabelle Teams existiert bereits" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -------------------------------------------------------------------
-- 2) Spalte team_id an der Tabelle Schwimmer ergänzen (falls fehlend)
-- -------------------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'Schwimmer' AND COLUMN_NAME = 'team_id');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE Schwimmer ADD COLUMN team_id INT NULL AFTER erstelldatum',
    'SELECT "Spalte team_id existiert bereits" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Fremdschlüssel auf Teams setzen (falls noch nicht vorhanden).
-- ON DELETE SET NULL, damit ein Schwimmer nicht verschwindet, wenn sein Team gelöscht wird.
SET @fk_exists := (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'Schwimmer'
      AND COLUMN_NAME = 'team_id' AND REFERENCED_TABLE_NAME = 'Teams');
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE Schwimmer ADD CONSTRAINT fk_schwimmer_team FOREIGN KEY (team_id) REFERENCES Teams(id) ON DELETE SET NULL',
    'SELECT "Fremdschlüssel fk_schwimmer_team existiert bereits" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'Migration abgeschlossen: Tabelle Teams und Schwimmer.team_id eingerichtet.' AS Erfolg;
