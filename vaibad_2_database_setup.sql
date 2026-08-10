-- SQL-Skript zum Erstellen der Datenbank vaibad_2 und der benötigten Tabellen
-- Zugangsdaten: Host: localhost, Benutzer: root, Passwort: (leer)

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
    limit INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle Sponsoren erstellen
CREATE TABLE IF NOT EXISTS Sponsoren (
    id INT AUTO_INCREMENT PRIMARY KEY,
    betrag_pro_bahn DECIMAL(10, 2) NOT NULL,
    limit INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle Schwimmer erstellen
CREATE TABLE IF NOT EXISTS Schwimmer (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vorname VARCHAR(100) NOT NULL,
    nachname VARCHAR(100) NOT NULL,
    geburtsjahr INT NOT NULL,
    schwimmleistung INT NOT NULL,
    erstelldatum DATETIME DEFAULT CURRENT_TIMESTAMP
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
-- INSERT INTO Hauptsponsoren (betrag_pro_bahn, limit) VALUES
-- (5.00, 100),
-- (10.00, 200);
--
-- INSERT INTO Sponsoren (betrag_pro_bahn, limit) VALUES
-- (2.00, 50),
-- (3.50, 75);
--
-- INSERT INTO Schwimmer (vorname, nachname, geburtsjahr, schwimmleistung) VALUES
-- ('Max', 'Mustermann', 2000, 50),
-- ('Anna', 'Beispiel', 1995, 75);
--
-- INSERT INTO schwimmer_sponsor (schwimmer_id, sponsoren_id) VALUES
-- (1, 1),
-- (2, 2);

-- Bestätigungsmeldung
SELECT 'Datenbank vaibad_2 und Tabellen wurden erfolgreich erstellt.' AS Erfolg;
