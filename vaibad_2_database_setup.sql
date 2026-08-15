-- SQL-Skript zum Erstellen der Datenbank vaibad_2 und der benötigten Tabellen
-- Zugangsdaten: Host: localhost, Benutzer: root, Passwort: (leer)
--
-- Durchläufe: Es gibt zwei Durchläufe (vormittags und nachmittags). Die
-- Schwimmleistung wird pro Schwimmer getrennt nach Vormittag und Nachmittag
-- in zwei Spalten erfasst. Eine berechnete Spalte schwimmleistung_gesamt
-- führt automatisch die Summe beider Durchläufe.
-- Startnummer: Jeder Schwimmer erhält beim Anlegen eine eindeutige Startnummer.

-- Datenbank erstellen
CREATE DATABASE IF NOT EXISTS vaibad_2
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Datenbank auswählen
USE vaibad_2;

-- Tabelle Hauptsponsoren erstellen
CREATE TABLE IF NOT EXISTS Hauptsponsoren (
    id INT AUTO_INCREMENT PRIMARY KEY,
    betrag_pro_bahn DECIMAL(10, 2) NOT NULL,
    `limit` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle Sponsoren erstellen
CREATE TABLE IF NOT EXISTS Sponsoren (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    betrag_pro_bahn DECIMAL(10, 2) NOT NULL,
    `limit` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle Teams erstellen
CREATE TABLE IF NOT EXISTS Teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    betrag_pro_bahn DECIMAL(10, 2) NOT NULL,
    `limit` INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle Schwimmer erstellen (mit Startnummer, getrennten Durchläufen und Team-Zuordnung)
-- Ein Schwimmer kann einem Team zugeordnet sein, muss aber nicht (team_id ist NULL erlaubt).
CREATE TABLE IF NOT EXISTS Schwimmer (
    id INT AUTO_INCREMENT PRIMARY KEY,
    startnummer INT UNIQUE,
    vorname VARCHAR(100) NOT NULL,
    nachname VARCHAR(100) NOT NULL,
    geburtsjahr INT NOT NULL,
    schwimmleistung_vormittag INT NOT NULL DEFAULT 0,
    schwimmleistung_nachmittag INT NOT NULL DEFAULT 0,
    schwimmleistung_gesamt INT GENERATED ALWAYS AS (schwimmleistung_vormittag + schwimmleistung_nachmittag) STORED,
    erstelldatum DATETIME DEFAULT CURRENT_TIMESTAMP,
    team_id INT NULL,
    FOREIGN KEY (team_id) REFERENCES Teams(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle schwimmer_sponsor erstellen (Verknüpfungstabelle)
CREATE TABLE IF NOT EXISTS schwimmer_sponsor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schwimmer_id INT NOT NULL,
    sponsoren_id INT NOT NULL,
    FOREIGN KEY (schwimmer_id) REFERENCES Schwimmer(id) ON DELETE CASCADE,
    FOREIGN KEY (sponsoren_id) REFERENCES Sponsoren(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: Beispiel-Daten einfügen (auskommentiert, falls nicht benötigt)
--
-- INSERT INTO Hauptsponsoren (betrag_pro_bahn, `limit`) VALUES
-- (5.00, 100),
-- (10.00, 200);
--
-- INSERT INTO Sponsoren (name, betrag_pro_bahn, `limit`) VALUES
-- ('Beispiel-Sponsor 1', 2.00, 50),
-- ('Beispiel-Sponsor 2', 3.50, 75);
--
-- INSERT INTO Schwimmer (startnummer, vorname, nachname, geburtsjahr, schwimmleistung_vormittag, schwimmleistung_nachmittag) VALUES
-- (1, 'Max', 'Mustermann', 2000, 30, 20),
-- (2, 'Anna', 'Beispiel', 1995, 0, 50);
--
-- INSERT INTO schwimmer_sponsor (schwimmer_id, sponsoren_id) VALUES
-- (1, 1),
-- (2, 2);

-- Bestätigungsmeldung
SELECT 'Datenbank vaibad_2 und Tabellen wurden erfolgreich erstellt.' AS Erfolg;
