-- Migrationsskript für bestehende vaibad_2-Datenbanken
-- Führt die Teams-Tabelle sowie die n:m-Zuordnung zwischen Schwimmern und Teams ein.
--
-- Ein Schwimmer kann für mehrere Teams schwimmen (und umgekehrt),
-- daher wird die Zuordnung über die Verknüpfungstabelle schwimmer_team abgebildet
-- (analog zu schwimmer_sponsor).
--
-- Dieses Skript ist idempotent: es kann gefahrlos wiederholt werden.
-- Bestehende Werte in einer eventuell vorhandenen Spalte Schwimmer.team_id werden
-- nach schwimmer_team migriert; danach wird die Spalte team_id entfernt.
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
    'CREATE TABLE Teams (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL, betrag_pro_bahn DECIMAL(10, 2) NOT NULL, `limit` INT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT "Tabelle Teams existiert bereits" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Falls die Tabelle bereits mit NOT NULL angelegt wurde: limit auf NULL-fähig setzen.
SET @sql := 'ALTER TABLE Teams MODIFY COLUMN `limit` INT NULL';
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -------------------------------------------------------------------
-- 2) Verknüpfungstabelle schwimmer_team erstellen (falls fehlend)
-- -------------------------------------------------------------------
SET @tbl_exists := (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'schwimmer_team');
SET @sql := IF(@tbl_exists = 0,
    'CREATE TABLE schwimmer_team (id INT AUTO_INCREMENT PRIMARY KEY, schwimmer_id INT NOT NULL, team_id INT NOT NULL, FOREIGN KEY (schwimmer_id) REFERENCES Schwimmer(id) ON DELETE CASCADE, FOREIGN KEY (team_id) REFERENCES Teams(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT "Tabelle schwimmer_team existiert bereits" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Eindeutigen Index verhindern, dass ein Schwimmer demselben Team doppelt zugeordnet wird.
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'schwimmer_team' AND INDEX_NAME = 'uniq_schwimmer_team');
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE schwimmer_team ADD UNIQUE INDEX uniq_schwimmer_team (schwimmer_id, team_id)',
    'SELECT "Index uniq_schwimmer_team existiert bereits" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -------------------------------------------------------------------
-- 3) Falls noch vorhanden: Werte aus Schwimmer.team_id nach schwimmer_team migrieren
--    und danach die Spalte team_id (inkl. Fremdschlüssel) entfernen.
-- -------------------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'Schwimmer' AND COLUMN_NAME = 'team_id');
SET @sql := IF(@col_exists > 0,
    'INSERT IGNORE INTO schwimmer_team (schwimmer_id, team_id) SELECT id, team_id FROM Schwimmer WHERE team_id IS NOT NULL',
    'SELECT "Spalte team_id nicht vorhanden, nichts zu migrieren" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Fremdschlüssel fk_schwimmer_team entfernen (falls vorhanden), dann die Spalte löschen.
SET @fk_exists := (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'Schwimmer'
      AND COLUMN_NAME = 'team_id' AND REFERENCED_TABLE_NAME = 'Teams');
SET @sql := IF(@fk_exists > 0,
    'ALTER TABLE Schwimmer DROP FOREIGN KEY fk_schwimmer_team',
    'SELECT "Fremdschlüssel fk_schwimmer_team nicht vorhanden" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@col_exists > 0,
    'ALTER TABLE Schwimmer DROP COLUMN team_id',
    'SELECT "Spalte team_id nicht vorhanden, nichts zu löschen" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'Migration abgeschlossen: Tabelle Teams, Verknüpfungstabelle schwimmer_team eingerichtet.' AS Erfolg;
