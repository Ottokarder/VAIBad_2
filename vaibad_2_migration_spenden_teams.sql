-- Migrationsskript für bestehende vaibad_2-Datenbanken
-- Führt die Ergebnistabelle spenden_teams ein.
--
-- Für jede Verknüpfung Schwimmer <-> Team (Tabelle schwimmer_team) wird ein
-- Eintrag berechnet, sofern der Schwimmer eine Spende erschwommen hat:
--   spendenbetrag_vormittag   = schwimmleistung_vormittag * betrag_pro_bahn
--   spendenbetrag_nachmittag  = schwimmleistung_nachmittag * betrag_pro_bahn
--   spendenbetrag_gesamt     = spendenbetrag_vormittag + spendenbetrag_nachmittag
-- Das Team-Limit gilt als Maximalbetrag über die Summe aller Schwimmer eines
-- Teams. spendenbetrag_gedeckelt enthält den anteilig auf das Team-Limit
-- gekappten Betrag.
--
-- Spalten der Tabelle:
--   team_id, schwimmer_id,
--   spendenbetrag_vormittag, spendenbetrag_nachmittag, spendenbetrag_gesamt,
--   spendenbetrag_gedeckelt
--
-- Dieses Skript ist idempotent: es kann gefahrlos wiederholt werden.
--
-- Ausführen mit:
--   mysql -u root vaibad_2 < vaibad_2_migration_spenden_teams.sql
-- oder im phpMyAdmin / MySQL-Client als komplettes Skript ausführen.

USE vaibad_2;

-- ---------------------------------------------------------------------
-- 1) Tabelle spenden_teams erstellen (falls noch nicht vorhanden)
-- ---------------------------------------------------------------------
SET @tbl_exists := (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'spenden_teams');

SET @sql := IF(@tbl_exists = 0,
    'CREATE TABLE spenden_teams (id INT AUTO_INCREMENT PRIMARY KEY, team_id INT NOT NULL, schwimmer_id INT NOT NULL, spendenbetrag_vormittag DECIMAL(10, 2) NOT NULL DEFAULT 0.00, spendenbetrag_nachmittag DECIMAL(10, 2) NOT NULL DEFAULT 0.00, spendenbetrag_gesamt DECIMAL(10, 2) NOT NULL DEFAULT 0.00, spendenbetrag_gedeckelt DECIMAL(10, 2) NOT NULL DEFAULT 0.00, erstelldatum DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (team_id) REFERENCES Teams(id) ON DELETE CASCADE, FOREIGN KEY (schwimmer_id) REFERENCES Schwimmer(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT "Tabelle spenden_teams existiert bereits" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Eindeutiger Index: pro Team-Schwimmer-Paar nur ein Ergebnis-Eintrag.
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'spenden_teams'
      AND INDEX_NAME = 'uniq_team_schwimmer_spende');

SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE spenden_teams ADD UNIQUE INDEX uniq_team_schwimmer_spende (team_id, schwimmer_id)',
    'SELECT "Index uniq_team_schwimmer_spende existiert bereits" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'Migration abgeschlossen: Tabelle spenden_teams eingerichtet.' AS Erfolg;
