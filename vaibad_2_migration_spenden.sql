-- Migrationsskript für bestehende vaibad_2-Datenbanken
-- Führt die Ergebnistabelle spenden_sponsoren ein.
--
-- Für jede Verknüpfung Schwimmer <-> Sponsor (Tabelle schwimmer_sponsor)
-- wird ein Eintrag berechnet:
--   spendenbetrag_vormittag   = schwimmleistung_vormittag * betrag_pro_bahn,
--                               gedeckelt auf das Limit des Sponsors (falls gesetzt)
--   spendenbetrag_nachmittag  = schwimmleistung_nachmittag * betrag_pro_bahn,
--                               gedeckelt auf das Limit des Sponsors (falls gesetzt)
--   spendenbetrag_gesamt     = spendenbetrag_vormittag + spendenbetrag_nachmittag
--
-- Spalten der Tabelle:
--   schwimmer_id, sponsoren_id,
--   spendenbetrag_vormittag, spendenbetrag_nachmittag, spendenbetrag_gesamt
--
-- Dieses Skript ist idempotent: es kann gefahrlos wiederholt werden.
--
-- Ausführen mit:
--   mysql -u root vaibad_2 < vaibad_2_migration_spenden.sql
-- oder im phpMyAdmin / MySQL-Client als komplettes Skript ausführen.

USE vaibad_2;

-- ---------------------------------------------------------------------
-- 1) Tabelle spenden_sponsoren erstellen (falls noch nicht vorhanden)
-- ---------------------------------------------------------------------
SET @tbl_exists := (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'spenden_sponsoren');

SET @sql := IF(@tbl_exists = 0,
    'CREATE TABLE spenden_sponsoren (id INT AUTO_INCREMENT PRIMARY KEY, schwimmer_id INT NOT NULL, sponsoren_id INT NOT NULL, spendenbetrag_vormittag DECIMAL(10, 2) NOT NULL DEFAULT 0.00, spendenbetrag_nachmittag DECIMAL(10, 2) NOT NULL DEFAULT 0.00, spendenbetrag_gesamt DECIMAL(10, 2) NOT NULL DEFAULT 0.00, erstelldatum DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (schwimmer_id) REFERENCES Schwimmer(id) ON DELETE CASCADE, FOREIGN KEY (sponsoren_id) REFERENCES Sponsoren(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT "Tabelle spenden_sponsoren existiert bereits" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Eindeutiger Index: pro Schwimmer-Sponsor-Paar nur ein Ergebnis-Eintrag.
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'spenden_sponsoren'
      AND INDEX_NAME = 'uniq_schwimmer_sponsor_spende');

SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE spenden_sponsoren ADD UNIQUE INDEX uniq_schwimmer_sponsor_spende (schwimmer_id, sponsoren_id)',
    'SELECT "Index uniq_schwimmer_sponsor_spende existiert bereits" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'Migration abgeschlossen: Tabelle spenden_sponsoren eingerichtet.' AS Erfolg;
