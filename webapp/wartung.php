<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Seite für Wartungszwecke: Zeigt fehlende Zuordnungen an

// Datenbank-Export/Import Funktionen
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'export') {
        // Datenbank Export - direkt über PHP
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="vaibad_database_' . date('Y-m-d_His') . '.sql"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        
        // Hole alle Tabellen
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        if ($result) {
            while ($row = $result->fetch_row()) {
                $tables[] = $row[0];
            }
            $result->free();
        }
        
        // Exportiere jede Tabelle
        $output = "-- VAIBad Datenbank Export\n";
        $output .= "-- Erstellt am: " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($tables as $table) {
            // Tabelle erstellen
            $output .= "--\n-- Tabelle: $table\n--\n";
            $result = $conn->query("SHOW CREATE TABLE `$table`");
            if ($result) {
                $row = $result->fetch_row();
                $output .= $row[1] . ";\n\n";
                $result->free();
            }
            
            // Daten exportieren
            $result = $conn->query("SELECT * FROM `$table`");
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $output .= "INSERT INTO `$table` VALUES(";
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } elseif (is_numeric($value)) {
                            $values[] = $value;
                        } else {
                            $values[] = '"' . $conn->real_escape_string($value) . '"';
                        }
                    }
                    $output .= implode(', ', $values) . ");\n";
                }
                $result->free();
            }
            $output .= "\n";
        }
        
        echo $output;
        exit;
    } elseif ($_GET['action'] === 'import' && isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
        // Datenbank Import
        $tmp_file = $_FILES['sql_file']['tmp_name'];
        
        // SQL-Datei einlesen
        $sql = file_get_contents($tmp_file);
        if ($sql !== false) {
            // Multi-Query ausführen
            if ($conn->multi_query($sql)) {
                // Alle Ergebnisse abrufen
                while ($conn->next_result()) {
                    if (!$conn->store_result()) {
                        // Error handling
                    }
                }
                $import_success = true;
            } else {
                $import_error = $conn->error;
            }
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
            ✓ Datenbank wurde erfolgreich importiert!
        </div>
    <?php elseif (isset($import_error)): ?>
        <div class="error-box" style="margin: 1rem 0;">
            Fehler beim Import: <?php echo htmlspecialchars($import_error); ?>
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
        LEFT JOIN spenden_sponsoren ss ON sw.id = ss.schwimmer_id
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
        LEFT JOIN spenden_sponsoren ss ON sp.id = ss.sponsoren_id
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
        LEFT JOIN spenden_teams st ON t.id = st.team_id
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