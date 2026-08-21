<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Seite für Wartungszwecke: Zeigt fehlende Zuordnungen an

// Datenbank-Export/Import Funktionen
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'export') {
        // Datenbank Export mit korrekter Struktur
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="vaibad_database_' . date('Y-m-d_His') . '.sql"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        
        // Tabellenstrukturen aus der Datenbank holen
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        if ($result) {
            while ($row = $result->fetch_row()) {
                $tables[] = $row[0];
            }
            $result->free();
        }
        
        $output = "-- VAIBad Datenbank Export\n";
        $output .= "-- Erstellt am: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Definiere die korrekten CREATE TABLE Statements
        $create_table_statements = [
            'Hauptsponsoren' => "CREATE TABLE IF NOT EXISTS Hauptsponsoren (    id INT AUTO_INCREMENT PRIMARY KEY,    name VARCHAR(200) NOT NULL,    betrag_pro_bahn DECIMAL(10, 2) NOT NULL,    `limit` INT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'Sponsoren' => "CREATE TABLE IF NOT EXISTS Sponsoren (    id INT AUTO_INCREMENT PRIMARY KEY,    name VARCHAR(200) NOT NULL,    betrag_pro_bahn DECIMAL(10, 2) NOT NULL,    `limit` INT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'Teams' => "CREATE TABLE IF NOT EXISTS Teams (    id INT AUTO_INCREMENT PRIMARY KEY,    name VARCHAR(200) NOT NULL,    betrag_pro_bahn DECIMAL(10, 2) NOT NULL,    `limit` INT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'Schwimmer' => "CREATE TABLE IF NOT EXISTS Schwimmer (    id INT AUTO_INCREMENT PRIMARY KEY,    startnummer INT UNIQUE,    vorname VARCHAR(100) NOT NULL,    nachname VARCHAR(100) NOT NULL,    geburtsjahr INT NOT NULL,    schwimmleistung_vormittag INT NOT NULL DEFAULT 0,    schwimmleistung_nachmittag INT NOT NULL DEFAULT 0,    schwimmleistung_gesamt INT GENERATED ALWAYS AS (schwimmleistung_vormittag + schwimmleistung_nachmittag) STORED,    erstelldatum DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'schwimmer_sponsor' => "CREATE TABLE IF NOT EXISTS schwimmer_sponsor (    id INT AUTO_INCREMENT PRIMARY KEY,    schwimmer_id INT NOT NULL,    sponsoren_id INT NOT NULL,    FOREIGN KEY (schwimmer_id) REFERENCES Schwimmer(id) ON DELETE CASCADE,    FOREIGN KEY (sponsoren_id) REFERENCES Sponsoren(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'schwimmer_team' => "CREATE TABLE IF NOT EXISTS schwimmer_team (    id INT AUTO_INCREMENT PRIMARY KEY,    schwimmer_id INT NOT NULL,    team_id INT NOT NULL,    FOREIGN KEY (schwimmer_id) REFERENCES Schwimmer(id) ON DELETE CASCADE,    FOREIGN KEY (team_id) REFERENCES Teams(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'spenden_sponsoren' => "CREATE TABLE IF NOT EXISTS spenden_sponsoren (    id INT AUTO_INCREMENT PRIMARY KEY,    schwimmer_id INT NOT NULL,    sponsoren_id INT NOT NULL,    spendenbetrag_vormittag DECIMAL(10, 2) NOT NULL DEFAULT 0.00,    spendenbetrag_nachmittag DECIMAL(10, 2) NOT NULL DEFAULT 0.00,    spendenbetrag_gesamt DECIMAL(10, 2) NOT NULL DEFAULT 0.00,    erstelldatum DATETIME DEFAULT CURRENT_TIMESTAMP,    UNIQUE INDEX uniq_schwimmer_sponsor_spende (schwimmer_id, sponsoren_id),    FOREIGN KEY (schwimmer_id) REFERENCES Schwimmer(id) ON DELETE CASCADE,    FOREIGN KEY (sponsoren_id) REFERENCES Sponsoren(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'spenden_teams' => "CREATE TABLE IF NOT EXISTS spenden_teams (    id INT AUTO_INCREMENT PRIMARY KEY,    team_id INT NOT NULL,    schwimmer_id INT NOT NULL,    spendenbetrag_vormittag DECIMAL(10, 2) NOT NULL DEFAULT 0.00,    spendenbetrag_nachmittag DECIMAL(10, 2) NOT NULL DEFAULT 0.00,    spendenbetrag_gesamt DECIMAL(10, 2) NOT NULL DEFAULT 0.00,    spendenbetrag_gedeckelt DECIMAL(10, 2) NOT NULL DEFAULT 0.00,    erstelldatum DATETIME DEFAULT CURRENT_TIMESTAMP,    UNIQUE INDEX uniq_team_schwimmer_spende (team_id, schwimmer_id),    FOREIGN KEY (team_id) REFERENCES Teams(id) ON DELETE CASCADE,    FOREIGN KEY (schwimmer_id) REFERENCES Schwimmer(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'spenden_hauptsponsoren' => "CREATE TABLE IF NOT EXISTS spenden_hauptsponsoren (    id INT AUTO_INCREMENT PRIMARY KEY,    hauptsponsor_id INT NOT NULL,    schwimmer_id INT NOT NULL,    spendenbetrag_vormittag DECIMAL(10, 2) NOT NULL DEFAULT 0.00,    spendenbetrag_nachmittag DECIMAL(10, 2) NOT NULL DEFAULT 0.00,    spendenbetrag_gesamt DECIMAL(10, 2) NOT NULL DEFAULT 0.00,    spendenbetrag_gedeckelt DECIMAL(10, 2) NOT NULL DEFAULT 0.00,    erstelldatum DATETIME DEFAULT CURRENT_TIMESTAMP,    UNIQUE INDEX uniq_hauptsponsor_schwimmer_spende (hauptsponsor_id, schwimmer_id),    FOREIGN KEY (hauptsponsor_id) REFERENCES Hauptsponsoren(id) ON DELETE CASCADE,    FOREIGN KEY (schwimmer_id) REFERENCES Schwimmer(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
        ];
        
        // Exportiere jede Tabelle
        foreach ($tables as $table) {
            // DROP TABLE + CREATE TABLE
            if (isset($create_table_statements[$table])) {
                $output .= "--\n-- Tabelle: $table\n--\n";
                $output .= "DROP TABLE IF EXISTS `$table`;\n";
                $output .= $create_table_statements[$table] . "\n\n";
            } else {
                // Für unbekannte Tabellen: nur DROP + CREATE via SHOW CREATE TABLE
                $result = $conn->query("SHOW CREATE TABLE `$table`");
                if ($result) {
                    $row = $result->fetch_row();
                    $output .= "--\n-- Tabelle: $table\n--\n";
                    $output .= "DROP TABLE IF EXISTS `$table`;\n";
                    $output .= $row[1] . ";\n\n";
                    $result->free();
                }
            }
            
            // Daten exportieren
            $result = $conn->query("SELECT * FROM `$table`");
            if ($result && $result->num_rows > 0) {
                // Spaltennamen holen
                $fields = [];
                $meta = $result->fetch_fields();
                foreach ($meta as $field) {
                    $fields[] = $field->name;
                }
                
                while ($row = $result->fetch_assoc()) {
                    $output .= "INSERT INTO `$table` (";
                    $columns = [];
                    $values = [];
                    
                    foreach ($row as $key => $value) {
                        $columns[] = "`$key`";
                        if ($value === null) {
                            $values[] = 'NULL';
                        } elseif ($key === 'schwimmleistung_gesamt' && $table === 'Schwimmer') {
                            // Spezialbehandlung für berechnete Spalte
                            $values[] = 'DEFAULT';
                        } elseif (is_numeric($value) && !is_string($value)) {
                            $values[] = $value;
                        } else {
                            $values[] = '"' . $conn->real_escape_string($value) . '"';
                        }
                    }
                    
                    $output .= implode(', ', $columns) . ") VALUES(";
                    $output .= implode(', ', $values) . ");\n";
                }
                $result->free();
            }
            $output .= "\n";
        }
        
        echo $output;
        exit;
    } elseif ($_GET['action'] === 'import' && isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
        // Datenbank Import - zeilenweise verarbeiten
        $tmp_file = $_FILES['sql_file']['tmp_name'];
        
        // SQL-Datei zeilenweise einlesen
        $sql_lines = file($tmp_file);
        if ($sql_lines !== false) {
            $errors = [];
            $success_count = 0;
            $sql_buffer = '';
            
            foreach ($sql_lines as $line) {
                $line = trim($line);
                
                // Leere Zeilen und Kommentare überspringen
                if (empty($line) || $line[0] === '-' || $line[0] === '/') {
                    continue;
                }
                
                // Semikolon bedeutet Ende eines Statements
                if (substr($line, -1) === ';') {
                    $sql_buffer .= $line;
                    
                    // Führe das Statement aus
                    if (!empty($sql_buffer)) {
                        if ($conn->query($sql_buffer)) {
                            $success_count++;
                        } else {
                            $errors[] = $conn->error . " (SQL: " . substr($sql_buffer, 0, 100) . "...)";
                        }
                        $sql_buffer = '';
                    }
                } else {
                    $sql_buffer .= $line . " ";
                }
            }
            
            // Letztes Statement ausführen
            if (!empty($sql_buffer)) {
                if ($conn->query($sql_buffer)) {
                    $success_count++;
                } else {
                    $errors[] = $conn->error . " (SQL: " . substr($sql_buffer, 0, 100) . "...)";
                }
            }
            
            if (empty($errors)) {
                $import_success = true;
            } else {
                $import_error = implode("\n", $errors);
            }
        } else {
            $import_error = "Konnte SQL-Datei nicht einlesen";
        }
    }
}

// Alle Variablen initialisieren (leer setzen)
$schwimmer_ohne_leistung = [];
$schwimmer_ohne_sponsor = [];
$sponsoren_ohne_schwimmer = [];
$teams_ohne_schwimmer = [];

// HTML-Header einbinden
if (file_exists('includes/header.php')) {
    include 'includes/header.php';
} else {
    echo '<!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Wartung - Fehlende Zuordnungen - VAIBad</title>
        <link rel="stylesheet" href="/css/style.css">
    </head>
    <body>';
}
?>
<div class="container">
    <h1>Wartung - Fehlende Zuordnungen</h1>
    <p>Diese Seite zeigt Daten, die einer Überprüfung bedürfen.</p>

    <div class="action-bar">
        <a href="/index.php" class="btn btn-secondary">Startseite</a>
        <a href="/wartung.php?action=export" class="btn btn-primary">Datenbank exportieren</a>
        <form method="POST" action="/wartung.php?action=import" style="display: inline; margin-left: 10px;" enctype="multipart/form-data">
            <input type="file" name="sql_file" accept=".sql" style="display: none;" id="sql_file_input">
            <button type="button" onclick="document.getElementById('sql_file_input').click()" class="btn btn-primary">Datenbank importieren</button>
            <input type="submit" id="sql_file_submit" style="display: none;">
        </form>
    </div>
    
    <?php if (isset($import_success) && $import_success): ?>
        <div class="success-box" style="margin: 1rem 0;">
            ✓ Datenbank wurde erfolgreich importiert! (<?php echo isset($success_count) ? $success_count : '0'; ?> Befehle ausgeführt)
        </div>
    <?php elseif (isset($import_error)): ?>
        <div class="error-box" style="margin: 1rem 0;">
            Fehler beim Import: <?php echo nl2br(htmlspecialchars($import_error)); ?>
        </div>
    <?php endif; ?>

    <script>
    document.getElementById('sql_file_input').addEventListener('change', function() {
        if (this.files.length > 0) {
            if (confirm('Warnung: Der Import wird die bestehende Datenbank überschreiben! Fortfahren?')) {
                document.getElementById('sql_file_submit').click();
            }
        }
    });
    </script>

    <!-- Schwimmer ohne Schwimmleistung -->
    <h2 style="margin-top: 2rem;">Schwimmer ohne Schwimmleistung</h2>
    <?php
    // Variablen zurücksetzen
    $schwimmer_ohne_leistung = [];
    $res = $conn->query("
        SELECT id, startnummer, vorname, nachname
        FROM Schwimmer
        WHERE schwimmleistung_vormittag = 0 AND schwimmleistung_nachmittag = 0
        ORDER BY startnummer, nachname, vorname
    ");
    if ($res) { while ($r = $res->fetch_assoc()) $schwimmer_ohne_leistung[] = $r; $res->free(); }
    if (!empty($schwimmer_ohne_leistung)):
    ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Startnr.</th>
                    <th>Name</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($schwimmer_ohne_leistung as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['startnummer']); ?></td>
                        <td><?php echo htmlspecialchars($r['vorname'] . ' ' . $r['nachname']); ?></td>
                        <td>
                            <a href="/schwimmleistung_eingeben.php?schwimmer_id=<?php echo $r['id']; ?>" class="btn btn-primary">Schwimmleistung eingeben</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="success-box" style="margin: 1rem 0;">
            ✓ Alle Schwimmer haben Schwimmleistungen eingegeben.
        </div>
    <?php endif; ?>

    <!-- Schwimmer ohne Sponsoren-Zuordnung -->
    <h2 style="margin-top: 2rem;">Schwimmer ohne Sponsoren-Zuordnung</h2>
    <?php
    // Variablen zurücksetzen
    $schwimmer_ohne_sponsor = [];
    $res = $conn->query("
        SELECT sw.id, sw.startnummer, sw.vorname, sw.nachname
        FROM Schwimmer sw
        LEFT JOIN schwimmer_sponsor ss ON sw.id = ss.schwimmer_id
        WHERE ss.schwimmer_id IS NULL
        ORDER BY sw.startnummer, sw.nachname, sw.vorname
    ");
    if ($res) { while ($r = $res->fetch_assoc()) $schwimmer_ohne_sponsor[] = $r; $res->free(); }
    if (!empty($schwimmer_ohne_sponsor)):
    ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Startnr.</th>
                    <th>Name</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($schwimmer_ohne_sponsor as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['startnummer']); ?></td>
                        <td><?php echo htmlspecialchars($r['vorname'] . ' ' . $r['nachname']); ?></td>
                        <td>
                            <a href="/neue_verknuepfung.php?schwimmer_id=<?php echo $r['id']; ?>" class="btn btn-primary">Verknüpfen</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="success-box" style="margin: 1rem 0;">
            ✓ Alle Schwimmer haben Sponsoren zugeordnet.
        </div>
    <?php endif; ?>

    <!-- Sponsoren ohne Schwimmer-Zuordnung -->
    <h2 style="margin-top: 2rem;">Sponsoren ohne Schwimmer-Zuordnung</h2>
    <?php
    // Variablen zurücksetzen
    $sponsoren_ohne_schwimmer = [];
    $res = $conn->query("
        SELECT sp.id, sp.name
        FROM Sponsoren sp
        LEFT JOIN schwimmer_sponsor ss ON sp.id = ss.sponsoren_id
        WHERE ss.sponsoren_id IS NULL
        ORDER BY sp.name
    ");
    if ($res) { while ($r = $res->fetch_assoc()) $sponsoren_ohne_schwimmer[] = $r; $res->free(); }
    if (!empty($sponsoren_ohne_schwimmer)):
    ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sponsor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sponsoren_ohne_schwimmer as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="success-box" style="margin: 1rem 0;">
            ✓ Alle Sponsoren sind Schwimmern zugeordnet.
        </div>
    <?php endif; ?>

    <!-- Teams ohne Schwimmer-Zuordnung -->
    <h2 style="margin-top: 2rem;">Teams ohne Schwimmer-Zuordnung</h2>
    <?php
    // Variablen zurücksetzen
    $teams_ohne_schwimmer = [];
    $res = $conn->query("
        SELECT t.id, t.name
        FROM Teams t
        LEFT JOIN schwimmer_team st ON t.id = st.team_id
        WHERE st.team_id IS NULL
        ORDER BY t.name
    ");
    if ($res) { while ($r = $res->fetch_assoc()) $teams_ohne_schwimmer[] = $r; $res->free(); }
    if (!empty($teams_ohne_schwimmer)):
    ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Team</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teams_ohne_schwimmer as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="success-box" style="margin: 1rem 0;">
            ✓ Alle Teams sind Schwimmern zugeordnet.
        </div>
    <?php endif; ?>
</div>
<?php
// HTML-Footer einbinden
if (file_exists('includes/footer.php')) {
    include 'includes/footer.php';
} else {
    echo '</body>
    </html>';
}
$conn->close();
?>