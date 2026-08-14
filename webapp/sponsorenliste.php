<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Sponsor löschen (wenn DELETE-Request)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM Sponsoren WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: /VAIBad_2/webapp/sponsorenliste.php");
    exit();
}

// Alle Sponsoren abrufen
$sql = "SELECT id, name, betrag_pro_bahn, `limit` FROM Sponsoren ORDER BY name";
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
        <title>Sponsorenliste - VAIBad</title>
        <link rel="stylesheet" href="/VAIBad_2/webapp/css/style.css">
    </head>
    <body>';
}
?>

<div class="container">
    <h1>Sponsorenliste</h1>

    <!-- Button für neuen Sponsor und Startseite -->
    <div class="action-bar">
        <a href="/VAIBad_2/webapp/neuer_sponsor.php" class="btn btn-primary">Neuer Sponsor</a>
        <a href="/VAIBad_2/webapp/index.php" class="btn btn-secondary">Startseite</a>
    </div>

    <!-- Tabelle mit Sponsorendaten -->
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
                                <a href="/VAIBad_2/webapp/bearbeiten_sponsor.php?id=<?php echo $row['id']; ?>"
                                   class="btn btn-edit" title="Bearbeiten">
                                    Bearbeiten
                                </a>

                                <!-- Löschen-Button -->
                                <a href="/VAIBad_2/webapp/sponsorenliste.php?action=delete&id=<?php echo $row['id']; ?>"
                                   class="btn btn-delete"
                                   onclick="return confirm('Möchtest du diesen Sponsor wirklich löschen?')"
                                   title="Löschen">
                                    Löschen
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="no-data">Keine Sponsoren gefunden.</td>
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
