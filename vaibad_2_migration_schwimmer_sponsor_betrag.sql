-- Migrationsskript fuer bestehende vaibad_2-Datenbanken
-- Erweitert die Tabelle schwimmer_sponsor um die Spalten betrag_pro_bahn und `limit`.
--
-- Hintergrund: Im urspruenglichen Schema (vaibad_2_database_setup.sql) enthaelt
-- schwimmer_sponsor nur die Zuordnung Schwimmer <-> Sponsor. Der Betrag pro Bahn
-- und das Limit waren nur GLOBAL pro Sponsor in der Tabelle Sponsoren gespeichert.
-- In der Praxis (Sponsorenschwimmen 2025) sind Betrag pro Bahn und Limit aber
-- pro Schwimmer-Sponsor-Paar unterschiedlich (derselbe Sponsor zahlt bei
-- Schwimmer A 0,50/Bahn und bei Schwimmer B 1,00/Bahn).
--
-- Daher werden die Spalten betrag_pro_bahn und `limit` in schwimmer_sponsor
-- ergaenzt. Die Spendenberechnung greift dann pro Paar auf diese Werte zu.
-- Fuer die Abwaertskompatibilitaet werden beide Spalten mit Default-Werten
-- (0.00 bzw. NULL) versehen; bestehende Zuordnungen bleiben somit gueltig.
--
-- Dieses Skript ist idempotent: es kann gefahrlos wiederholt werden.
--
-- Ausfuehren mit:
--   mysql -u root vaibad_2 < vaibad_2_migration_schwimmer_sponsor_betrag.sql
-- oder im phpMyAdmin / MySQL-Client als komplettes Skript ausfuehren.

USE vaibad_2;

-- Pruefen, ob die Spalte betrag_pro_bahn bereits existiert; ggf. anlegen.
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'schwimmer_sponsor'
      AND COLUMN_NAME = 'betrag_pro_bahn');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE schwimmer_sponsor ADD COLUMN betrag_pro_bahn DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER sponsoren_id',
    'SELECT "Spalte betrag_pro_bahn existiert bereits" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Pruefen, ob die Spalte `limit` bereits existiert; ggf. anlegen (NULL erlaubt).
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'vaibad_2' AND TABLE_NAME = 'schwimmer_sponsor'
      AND COLUMN_NAME = 'limit');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE schwimmer_sponsor ADD COLUMN `limit` INT NULL AFTER betrag_pro_bahn',
    'SELECT "Spalte limit existiert bereits" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Damit die Spendenberechnung pro Paar funktioniert: vorhandene Zuordnungen
-- mit den globalen Werten aus der Tabelle Sponsoren vorbelegen, falls die
-- pro-Paar-Spalten noch NULL/0 sind. So bleiben alte Daten konsistent.
UPDATE schwimmer_sponsor ss
JOIN Sponsoren s ON s.id = ss.sponsoren_id
SET ss.betrag_pro_bahn = s.betrag_pro_bahn
WHERE ss.betrag_pro_bahn = 0.00;

UPDATE schwimmer_sponsor ss
JOIN Sponsoren s ON s.id = ss.sponsoren_id
SET ss.`limit` = s.`limit`
WHERE ss.`limit` IS NULL;

SELECT 'Migration abgeschlossen: schwimmer_sponsor um betrag_pro_bahn und limit erweitert.' AS Erfolg;
