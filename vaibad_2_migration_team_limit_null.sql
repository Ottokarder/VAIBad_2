-- Migrationsskript für bestehende vaibad_2-Datenbanken
-- Stellt sicher, dass die Spalte `limit` der Tabelle Teams NULL-fähig ist
-- (optional, kein Limit). Vorhandene Werte bleiben erhalten.
--
-- Dieses Skript ist idempotent: es kann gefahrlos wiederholt werden.
--
-- Ausführen mit:
--   mysql -u root vaibad_2 < vaibad_2_migration_team_limit_null.sql
-- oder im phpMyAdmin / MySQL-Client als komplettes Skript ausführen.

USE vaibad_2;

ALTER TABLE Teams MODIFY COLUMN `limit` INT NULL;

SELECT 'Migration abgeschlossen: Teams.limit ist jetzt NULL-fähig.' AS Erfolg;
