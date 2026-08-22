<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Seite für Wartungszwecke: Zeigt fehlende Zuordnungen an

// Datenbank-Export/Import Funktionen
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'export') {
        // Export-Typ bestimmen: structure, data oder beides (default)
        $export_type = isset($_GET['type']) ? $_GET['type'] : 'full';
        
        // Dateinamen anpassen
        $type_suffix = '';
        if ($export_type === 'structure') {
            $type_suffix = '_structure';
        } elseif ($export_type === 'data') {
            $type_suffix = '_data';
        }
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="vaibad_database_' . date('Y-m-d_His') . $type_suffix . '.sql"');
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
        $output .= "-- Typ: " . ucfirst($export_type) . "\n";
        $output .= "-- Erstellt am: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Für Daten-Export: FOREIGN_KEY_CHECKS deaktivieren
        if ($export_type === 'data' || $export_type === 'full') {
            $output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        }
        
        // Exportiere jede Tabelle
        foreach ($tables as $table) {
            // --- STRUKTUR-EXPORT (für 'structure' oder 'full') ---
            if ($export_type === 'structure' || $export_type === 'full') {
                $result = $conn->query("SHOW CREATE TABLE `$table`");
                if ($result) {
                    $row = $result->fetch_row();
                    $output .= "--\n-- Tabelle: $table\n--\n";
                    $output .= "DROP TABLE IF EXISTS `$table`;\n";
                    $output .= $row[1] . ";\n\n";
                    $result->free();
                }
            }
            
            // --- DATEN-EXPORT (für 'data' oder 'full') ---
            if ($export_type === 'data' || $export_type === 'full') {
                // AUTO_INCREMENT zurücksetzen
                $output .= "--\n-- Daten für Tabelle: $table\n--\n";
                $output .= "ALTER TABLE `$table` AUTO_INCREMENT = 1;\n";
                $output .= "DELETE FROM `$table`;\n\n";
                
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
                        // Spezialbehandlung für Schwimmer-Tabelle
                        if ($table === 'Schwimmer') {
                            // Korrekte Spaltenreihenfolge für Schwimmer
                            $output .= "INSERT INTO `$table` (";
                            $columns = ['id', 'startnummer', 'vorname', 'nachname', 'geburtsjahr', 
                                       'schwimmleistung_vormittag', 'schwimmleistung_nachmittag', 
                                       'schwimmleistung_gesamt', 'erstelldatum'];
                            $output .= implode(', ', array_map(function($c) { return "`$c`"; }, $columns)) . ") VALUES(";
                            
                            $values = [];
                            $values[] = $row['id'];
                            $values[] = $row['startnummer'] !== null ? $row['startnummer'] : 'NULL';
                            $values[] = '"' . $conn->real_escape_string($row['vorname']) . '"';
                            $values[] = '"' . $conn->real_escape_string($row['nachname']) . '"';
                            $values[] = $row['geburtsjahr'];
                            $values[] = $row['schwimmleistung_vormittag'];
                            $values[] = $row['schwimmleistung_nachmittag'];
                            $values[] = 'DEFAULT'; // bahnen_gesamt ist GENERATED ALWAYS AS
                            $values[] = $row['erstelldatum'] !== null ? '"' . $row['erstelldatum'] . '"' : 'NULL';
                            
                            $output .= implode(', ', $values) . ");\n";
                        } else {
                            // Standard-INSERT für andere Tabellen
                            $output .= "INSERT INTO `$table` (";
                            $columns = [];
                            $values = [];
                            
                            foreach ($row as $key => $value) {
                                $columns[] = "`$key`";
                                if ($value === null) {
                                    $values[] = 'NULL';
                                } elseif (is_numeric($value) && !is_string($value)) {
                                    $values[] = $value;
                                } else {
                                    $values[] = '"' . $conn->real_escape_string($value) . '"';
                                }
                            }
                            
                            $output .= implode(', ', $columns) . ") VALUES(";
                            $output .= implode(', ', $values) . ");\n";
                        }
                    }
                    $result->free();
                }
                $output .= "\n";
            }
        }
        
        // FOREIGN_KEY_CHECKS wieder aktivieren
        if ($export_type === 'data' || $export_type === 'full') {
            $output .= "SET FOREIGN_KEY_CHECKS = 1;\n\n";
        }
        
        echo $output;
        exit;
    } elseif ($_GET['action'] === 'import' && isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
        // Datenbank Import - zeilenweise verarbeiten mit Transaktion
        $tmp_file = $_FILES['sql_file']['tmp_name'];
        
        // SQL-Datei zeilenweise einlesen
        $sql_lines = file($tmp_file);
        if ($sql_lines !== false) {
            $errors = [];
            $success_count = 0;
            $sql_buffer = '';
            
            // Transaktion starten
            $conn->begin_transaction();
            
            try {
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
                    $conn->commit();
                    $import_success = true;
                } else {
                    $conn->rollback();
                    $import_error = implode("\n", $errors);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $import_error = "Fehler bei der Transaktion: " . $e->getMessage();
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
        <div style="display: inline-flex; gap: 10px; margin-left: 10px;">
            <a href="/wartung.php?action=export&type=full" class="btn btn-primary">Datenbank exportieren (vollständig)</a>
            <a href="/wartung.php?action=export&type=structure" class="btn btn-primary">Struktur exportieren</a>
            <a href="/wartung.php?action=export&type=data" class="btn btn-primary">Daten exportieren</a>
        </div>
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
            if (confirm('Warnung: Der Import wird ALLE DATEN in den Tabellen LÖSCHEN und durch die Importdaten ersetzen! Fortfahren?')) {
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