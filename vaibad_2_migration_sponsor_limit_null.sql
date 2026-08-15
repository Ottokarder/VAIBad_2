-- Migrationsskript für bestehende vaibad_2-Datenbanken
-- Macht die Spalte `limit` der Tabelle Sponsoren NULL-fähig (optional, kein Limit).
--
-- Hintergrund: Sponsoren.limit war ursprünglich NOT NULL. Damit ein Sponsor
-- auch ohne Limit gespeichert werden kann (analog zu Teams), wird die Spalte
-- auf NULL umgestellt. Vorhandene Werte bleiben erhalten.
--
-- Dieses Skript ist idempotent: es kann gefahrlos wiederholt werden.
--
-- Ausführen mit:
--   mysql -u root vaibad_2 < vaibad_2_migration_sponsor_limit_null.sql
-- oder im phpMyAdmin / MySQL-Client als komplettes Skript ausführen.

USE vaibad_2;

ALTER TABLE Sponsoren MODIFY COLUMN `limit` INT NULL;

SELECT 'Migration abgeschlossen: Sponsoren.limit ist jetzt NULL-fähig.' AS Erfolg;
