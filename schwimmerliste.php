<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Schwimmer löschen (wenn DELETE-Request)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM Schwimmer WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: schwimmerliste.php");
    exit();
}

// Alle Schwimmer abrufen
$sql = "SELECT id, vorname, nachname, geburtsjahr, schwimmleistung, erstelldatum FROM Schwimmer ORDER BY nachname, vorname";
$result = $conn->query($sql);

// Alter berechnen
function berechneAlter($geburtsjahr) {
    $aktuellesJahr = date('Y');
    return $aktuellesJahr - $geburtsjahr;
}

// HTML-Header einbinden (falls vorhanden)
if (file_exists('includes/header.php')) {
    include 'includes/header.php';
} else {
    echo '<!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Schwimmerliste - VAIBad</title>
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body>';
}
?>

<!-- Hauptinhalt -->
<div class="container">
    <h1>Schwimmerliste</h1>

    <!-- Button für neuen Schwimmer -->
    <div class="action-bar">
        <a href="neuer_schwimmer.php" class="btn btn-primary">Neuer Schwimmer</a>
    </div>

    <!-- Tabelle mit Schwimmerdaten -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Vorname</th>
                    <th>Nachname</th>
                    <th>Alter</th>
                    <th>Schwimmleistung (Bahnen)</th>
                    <th>Erstellungsdatum</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['vorname']); ?></td>
                            <td><?php echo htmlspecialchars($row['nachname']); ?></td>
                            <td><?php echo berechneAlter($row['geburtsjahr']); ?></td>
                            <td><?php echo htmlspecialchars($row['schwimmleistung']); ?></td>
                            <td><?php echo date('d.m.Y H:i', strtotime($row['erstelldatum'])); ?></td>
                            <td class="actions">
                                <!-- Bearbeiten-Button -->
                                <a href="bearbeiten_schwimmer.php?id=<?php echo $row['id']; ?>"
                                   class="btn btn-edit" title="Bearbeiten">
                                    Bearbeiten
                                </a>

                                <!-- Verknüpfung hinzufügen-Button -->
                                <a href="neue_verknuepfung.php?schwimmer_id=<?php echo $row['id']; ?>"
                                   class="btn btn-link" title="Verknüpfung mit Sponsor">
                                    Verknüpfen
                                </a>

                                <!-- Löschen-Button -->
                                <a href="schwimmerliste.php?action=delete&id=<?php echo $row['id']; ?>"
                                   class="btn btn-delete"
                                   onclick="return confirm('Möchtest du diesen Schwimmer wirklich löschen?')"
                                   title="Löschen">
                                    Löschen
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="no-data">Keine Schwimmer gefunden.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// HTML-Footer einbinden (falls vorhanden)
if (file_exists('includes/footer.php')) {
    include 'includes/footer.php';
} else {
    echo '</body>
    </html>';
}

// Datenbankverbindung schließen
$conn->close();
?>
