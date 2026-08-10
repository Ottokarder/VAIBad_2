<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Hauptsponsor löschen (wenn DELETE-Request)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM Hauptsponsoren WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: /VAIBad_2/webapp/hauptsponsorenliste.php");
    exit();
}

// Alle Hauptsponsoren abrufen
$sql = "SELECT id, name, betrag_pro_bahn, `limit` FROM Hauptsponsoren ORDER BY name";
$result = $conn->query($sql);

// HTML-Header einbinden
if (file_exists('includes/header.php')) {
    include 'includes/header.php';
} else {
    echo '<!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Hauptsponsorenliste - VAIBad</title>
        <link rel="stylesheet" href="/VAIBad_2/webapp/css/style.css">
    </head>
    <body>';
}
?>

<div class="container">
    <h1>Hauptsponsorenliste</h1>

    <!-- Button für neuen Hauptsponsor und Startseite -->
    <div class="action-bar">
        <a href="/VAIBad_2/webapp/neuer_hauptsponsor.php" class="btn btn-primary">Neuer Hauptsponsor</a>
        <a href="/VAIBad_2/webapp/index.php" class="btn btn-secondary">Startseite</a>
    </div>

    <!-- Tabelle mit Hauptsponsorendaten -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Betrag pro Bahn (€)</th>
                    <th>Limit</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo number_format($row['betrag_pro_bahn'], 2, ',', '.'); ?></td>
                            <td><?php echo ($row['limit'] !== null) ? htmlspecialchars($row['limit']) : 'Ohne Limit'; ?></td>
                            <td class="actions">
                                <!-- Bearbeiten-Button -->
                                <a href="/VAIBad_2/webapp/bearbeiten_hauptsponsor.php?id=<?php echo $row['id']; ?>"
                                   class="btn btn-edit" title="Bearbeiten">
                                    Bearbeiten
                                </a>

                                <!-- Löschen-Button -->
                                <a href="/VAIBad_2/webapp/hauptsponsorenliste.php?action=delete&id=<?php echo $row['id']; ?>"
                                   class="btn btn-delete"
                                   onclick="return confirm('Möchtest du diesen Hauptsponsor wirklich löschen?')"
                                   title="Löschen">
                                    Löschen
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="no-data">Keine Hauptsponsoren gefunden.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
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
