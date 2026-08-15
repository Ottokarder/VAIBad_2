-- Migrationsskript für bestehende vaibad_2-Datenbanken
-- 1) Hauptsponsoren.limit NULL-fähig machen (optional, kein Limit).
-- 2) Fehlende Spalte Hauptsponsoren.name ergänzen (falls noch nicht vorhanden),
--    damit Name/SetName mit der App konsistent sind.
-- 3) Ergebnistabelle spenden_hauptsponsoren anlegen.
--
-- Jeder Hauptsponsor zahlt für JEDEDN Schwimmer. Pro Hauptsponsor und Schwimmer
-- wird ein Eintrag berechnet:
--   spendenbetrag_vormittag   = schwimmleistung_vormittag * betrag_pro_bahn
--   spendenbetrag_nachmittag  = schwimmleistung_nachmittag * betrag_pro_bahn
--   spendenbetrag_gesamt     = spendenbetrag_vormittag + spendenbetrag_nachmittag
-- Das Hauptsponsor-Limit gilt als Maximalbetrag über die Summe aller Schwimmer.
-- spendenbetrag_gedeckelt enthält den anteilig auf das Limit gekappten Betrag.
--
-- Dieses Skript ist idempotent: es kann gefahrlos wiederholt werden.
--
-- Ausführen mit:
--   mysql -u root vaibad_2 < vaibad_2_migration_hauptsponsoren.sql
-- oder im phpMyAdmin / MySQL-Client als komplettes Skript ausführen.

USE vaibad_2;

-- 1) limit NULL-fähig setzen.
ALTER TABLE Hauptsponsoren MODIFY COLUMN `limit` INT NULL;

-- 2) Spalte name ergänzen, falls sie fehlt.
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'Hauptsponsoren' AND COLUMN_NAME = 'name');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE Hauptsponsoren ADD COLUMN name VARCHAR(200) NOT NULL DEFAULT '''' AFTER id',
    'SELECT "Spalte Hauptsponsoren.name existiert bereits" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Ergebnistabelle spenden_hauptsponsoren erstellen (falls noch nicht vorhanden).
SET @tbl_exists := (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'spenden_hauptsponsoren');

SET @sql := IF(@tbl_exists = 0,
    'CREATE TABLE spenden_hauptsponsoren (id INT AUTO_INCREMENT PRIMARY KEY, hauptsponsor_id INT NOT NULL, schwimmer_id INT NOT NULL, spendenbetrag_vormittag DECIMAL(10, 2) NOT NULL DEFAULT 0.00, spendenbetrag_nachmittag DECIMAL(10, 2) NOT NULL DEFAULT 0.00, spendenbetrag_gesamt DECIMAL(10, 2) NOT NULL DEFAULT 0.00, spendenbetrag_gedeckelt DECIMAL(10, 2) NOT NULL DEFAULT 0.00, erstelldatum DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (hauptsponsor_id) REFERENCES Hauptsponsoren(id) ON DELETE CASCADE, FOREIGN KEY (schwimmer_id) REFERENCES Schwimmer(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT "Tabelle spenden_hauptsponsoren existiert bereits" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Eindeutiger Index: pro Hauptsponsor-Schwimmer-Paar nur ein Ergebnis-Eintrag.
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'spenden_hauptsponsoren'
      AND INDEX_NAME = 'uniq_hauptsponsor_schwimmer_spende');

SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE spenden_hauptsponsoren ADD UNIQUE INDEX uniq_hauptsponsor_schwimmer_spende (hauptsponsor_id, schwimmer_id)',
    'SELECT "Index uniq_hauptsponsor_schwimmer_spende existiert bereits" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'Migration abgeschlossen: Hauptsponsoren.limit NULL-fähig, Tabelle spenden_hauptsponsoren eingerichtet.' AS Erfolg;
