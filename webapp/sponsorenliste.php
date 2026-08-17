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
    header("Location: /sponsorenliste.php");
    exit();
}

// Alle Sponsoren abrufen
// Filter aus GET holen (Textfilter "enthält")
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';

if ($filter !== '') {
    $suchbegriff = "%" . $filter . "%";
    $stmt = $conn->prepare("SELECT id, name, betrag_pro_bahn, `limit` FROM Sponsoren WHERE name LIKE ? ORDER BY name");
    $stmt->bind_param("s", $suchbegriff);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $sql = "SELECT id, name, betrag_pro_bahn, `limit` FROM Sponsoren ORDER BY name";
    $result = $conn->query($sql);
}

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
        <link rel="stylesheet" href="/css/style.css">
    </head>
    <body>';
}
?>

<div class="container">
    <h1>Sponsorenliste</h1>

    <!-- Button für neuen Sponsor und Startseite -->
    <div class="action-bar">
        <a href="/neuer_sponsor.php" class="btn btn-primary">Neuer Sponsor</a>
        <a href="/index.php" class="btn btn-secondary">Startseite</a>
    </div>

    <!-- Filter -->
    <div class="action-bar" style="margin-bottom: 1rem;">
        <form method="GET" action="/sponsorenliste.php" class="form-inline" style="display:flex; gap:.5rem; flex-wrap:wrap; align-items:center;">
            <input type="text" name="filter" placeholder="Filter (Sponsorname)..." value="<?php echo htmlspecialchars($filter); ?>" style="flex:1; min-width:200px; padding:.4rem .6rem;">
            <button type="submit" class="btn btn-primary">Filtern</button>
            <?php if ($filter !== ''): ?>
                <a href="/sponsorenliste.php" class="btn btn-secondary">Zurücksetzen</a>
            <?php endif; ?>
        </form>
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
                                <a href="/bearbeiten_sponsor.php?id=<?php echo $row['id']; ?>"
                                   class="btn btn-edit" title="Bearbeiten">
                                    Bearbeiten
                                </a>
                                <!-- Löschen-Button -->
                                <a href="/sponsorenliste.php?action=delete&id=<?php echo $row['id']; ?>"
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
