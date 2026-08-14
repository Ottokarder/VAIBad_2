-- Migrationsskript für bestehende vaibad_2-Datenbanken
-- Erweitert die Schwimmer-Tabelle um Vormittag-/Nachmittag-Durchläufe und Startnummern.
--
-- Dieses Skript ist idempotent: es kann gefahrlos wiederholt werden.
-- Es migriert die alte Einzelspalte 'schwimmleistung' in 'schwimmleistung_vormittag'.
--
-- Ausführen mit:
--   mysql -u root vaibad_2 < vaibad_2_migration.sql
-- oder im phpMyAdmin / MySQL-Client als komplettes Skript ausführen.

USE vaibad_2;

-- -------------------------------------------------------------------
-- 1) Neue Spalten an der Tabelle Schwimmer ergänzen (falls fehlend)
-- -------------------------------------------------------------------

-- Startnummer
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'Schwimmer' AND COLUMN_NAME = 'startnummer');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE Schwimmer ADD COLUMN startnummer INT NULL AFTER id',
    'SELECT "Spalte startnummer existiert bereits" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Eindeutigen Index auf startnummer setzen (falls noch nicht vorhanden)
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'Schwimmer' AND COLUMN_NAME = 'startnummer');
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE Schwimmer ADD UNIQUE INDEX uniq_startnummer (startnummer)',
    'SELECT "Index uniq_startnummer existiert bereits" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- schwimmleistung_vormittag
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'Schwimmer' AND COLUMN_NAME = 'schwimmleistung_vormittag');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE Schwimmer ADD COLUMN schwimmleistung_vormittag INT NOT NULL DEFAULT 0',
    'SELECT "Spalte schwimmleistung_vormittag existiert bereits" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- schwimmleistung_nachmittag
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'Schwimmer' AND COLUMN_NAME = 'schwimmleistung_nachmittag');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE Schwimmer ADD COLUMN schwimmleistung_nachmittag INT NOT NULL DEFAULT 0',
    'SELECT "Spalte schwimmleistung_nachmittag existiert bereits" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- schwimmleistung_gesamt (berechnete Spalte)
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'Schwimmer' AND COLUMN_NAME = 'schwimmleistung_gesamt');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE Schwimmer ADD COLUMN schwimmleistung_gesamt INT GENERATED ALWAYS AS (schwimmleistung_vormittag + schwimmleistung_nachmittag) STORED',
    'SELECT "Spalte schwimmleistung_gesamt existiert bereits" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -------------------------------------------------------------------
-- 2) Alte Einzelspalte 'schwimmleistung' nach 'schwimmleistung_vormittag' migrieren
-- -------------------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'Schwimmer' AND COLUMN_NAME = 'schwimmleistung');
SET @sql := IF(@col_exists > 0,
    'UPDATE Schwimmer SET schwimmleistung_vormittag = schwimmleistung WHERE schwimmleistung IS NOT NULL',
    'SELECT "Alte Spalte schwimmleistung nicht vorhanden, nichts zu migrieren" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@col_exists > 0,
    'ALTER TABLE Schwimmer DROP COLUMN schwimmleistung',
    'SELECT "Alte Spalte schwimmleistung nicht vorhanden, nichts zu löschen" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -------------------------------------------------------------------
-- 3) Fehlende Startnummern für bestehende Schwimmer vergeben (aufsteigend nach id)
-- -------------------------------------------------------------------
-- Hinweis: In MySQL kann eine SET-basierte Schleife nicht direkt pro Zeile mit
-- laufender MAX-Logik gefüllt werden. Daher via Cursor in einer Prozedur:
DROP PROCEDURE IF EXISTS vaibad_2_vergebe_startnummern;
DELIMITER //
CREATE PROCEDURE vaibad_2_vergebe_startnummern()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE sid INT;
    DECLARE next_nr INT;
    DECLARE cur CURSOR FOR SELECT id FROM Schwimmer WHERE startnummer IS NULL ORDER BY id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    SELECT COALESCE(MAX(startnummer), 0) INTO next_nr FROM Schwimmer;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO sid;
        IF done = 1 THEN
            LEAVE read_loop;
        END IF;
        SET next_nr = next_nr + 1;
        UPDATE Schwimmer SET startnummer = next_nr WHERE id = sid;
    END LOOP;
    CLOSE cur;
END //
DELIMITER ;

CALL vaibad_2_vergebe_startnummern();
DROP PROCEDURE IF EXISTS vaibad_2_vergebe_startnummern;

SELECT 'Migration abgeschlossen: Vormittag/Nachmittag-Spalten und Startnummern eingerichtet.' AS Erfolg;
