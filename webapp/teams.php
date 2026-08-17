<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Team löschen (wenn DELETE-Request)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM Teams WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: /teams.php");
    exit();
}

// Alle Teams abrufen
// Filter aus GET holen (Textfilter "enthält")
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';

if ($filter !== '') {
    $suchbegriff = "%" . $filter . "%";
    $stmt = $conn->prepare("SELECT id, name, betrag_pro_bahn, `limit` FROM Teams WHERE name LIKE ? ORDER BY name");
    $stmt->bind_param("s", $suchbegriff);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $sql = "SELECT id, name, betrag_pro_bahn, `limit` FROM Teams ORDER BY name";
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
        <title>Teams - VAIBad</title>
        <link rel="stylesheet" href="/css/style.css">
    </head>
    <body>';
}
?>

<div class="container">
    <h1>Teams</h1>

    <!-- Button für neues Team und Startseite -->
    <div class="action-bar">
        <a href="/neues_team.php" class="btn btn-primary">Neues Team</a>
        <a href="/index.php" class="btn btn-secondary">Startseite</a>
    </div>

    <!-- Filter -->
    <div class="action-bar" style="margin-bottom: 1rem;">
        <form method="GET" action="/teams.php" class="form-inline" style="display:flex; gap:.5rem; flex-wrap:wrap; align-items:center;">
            <input type="text" name="filter" placeholder="Filter (Teamname)..." value="<?php echo htmlspecialchars($filter); ?>" style="flex:1; min-width:200px; padding:.4rem .6rem;">
            <button type="submit" class="btn btn-primary">Filtern</button>
            <?php if ($filter !== ''): ?>
                <a href="/teams.php" class="btn btn-secondary">Zurücksetzen</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tabelle mit Teamdaten -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Spendensumme pro Bahn (€)</th>
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
                                <a href="/bearbeiten_team.php?id=<?php echo $row['id']; ?>"
                                   class="btn btn-edit" title="Bearbeiten">
                                    Bearbeiten
                                </a>
                                <!-- Löschen-Button -->
                                <a href="/teams.php?action=delete&id=<?php echo $row['id']; ?>"
                                   class="btn btn-delete"
                                   onclick="return confirm('Möchtest du dieses Team wirklich löschen? Schwimmer ohne Team-Zuordnung bleiben erhalten.')"
                                   title="Löschen">
                                    Löschen
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="no-data">Keine Teams gefunden.</td>
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
