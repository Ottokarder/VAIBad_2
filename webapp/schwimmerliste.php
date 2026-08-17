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
    header("Location: /schwimmerliste.php");
    exit();
}

// Alle Schwimmer abrufen
// Filter aus GET holen (Textfilter "enthält")
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';

if ($filter !== '') {
    $suchbegriff = "%" . $filter . "%";
    $stmt = $conn->prepare("SELECT id, startnummer, vorname, nachname, geburtsjahr, schwimmleistung_vormittag, schwimmleistung_nachmittag, schwimmleistung_gesamt, erstelldatum FROM Schwimmer WHERE vorname LIKE ? OR nachname LIKE ? OR CAST(startnummer AS CHAR) LIKE ? ORDER BY startnummer, nachname, vorname");
    $stmt->bind_param("sss", $suchbegriff, $suchbegriff, $suchbegriff);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $sql = "SELECT id, startnummer, vorname, nachname, geburtsjahr, schwimmleistung_vormittag, schwimmleistung_nachmittag, schwimmleistung_gesamt, erstelldatum FROM Schwimmer ORDER BY startnummer, nachname, vorname";
    $result = $conn->query($sql);
}

// Alter berechnen
function berechneAlter($geburtsjahr) {
    $aktuellesJahr = date('Y');
    return $aktuellesJahr - $geburtsjahr;
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
        <title>Teilnehmerliste - VAIBad</title>
        <link rel="stylesheet" href="/css/style.css">
    </head>
    <body>';
}
?>

<div class="container">
    <h1>Teilnehmerliste</h1>

    <!-- Button für neuen Schwimmer und Startseite -->
    <div class="action-bar">
        <a href="/neuer_schwimmer.php" class="btn btn-primary">Neuer Teilnehmer</a>
        <a href="/index.php" class="btn btn-secondary">Startseite</a>
    </div>

    <!-- Filter -->
    <div class="action-bar" style="margin-bottom: 1rem;">
        <form method="GET" action="/schwimmerliste.php" class="form-inline" style="display:flex; gap:.5rem; flex-wrap:wrap; align-items:center;">
            <input type="text" name="filter" placeholder="Filter (Name oder Startnr.)..." value="<?php echo htmlspecialchars($filter); ?>" style="flex:1; min-width:200px; padding:.4rem .6rem;">
            <button type="submit" class="btn btn-primary">Filtern</button>
            <?php if ($filter !== ''): ?>
                <a href="/schwimmerliste.php" class="btn btn-secondary">Zurücksetzen</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tabelle mit Schwimmerdaten -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Startnr.</th>
                    <th>Vorname</th>
                    <th>Nachname</th>
                    <th>Alter</th>
                    <th>Bahnen Vormittag</th>
                    <th>Bahnen Nachmittag</th>
                    <th>Bahnen Gesamt</th>
                    <th>Erstellungsdatum</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['startnummer']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['vorname']); ?></td>
                            <td><?php echo htmlspecialchars($row['nachname']); ?></td>
                            <td><?php echo berechneAlter($row['geburtsjahr']); ?></td>
                            <td><?php echo htmlspecialchars($row['schwimmleistung_vormittag']); ?></td>
                            <td><?php echo htmlspecialchars($row['schwimmleistung_nachmittag']); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['schwimmleistung_gesamt']); ?></strong></td>
                            <td><?php echo date('d.m.Y H:i', strtotime($row['erstelldatum'])); ?></td>
                            <td class="actions">
                                <!-- Bearbeiten-Button -->
                                <a href="/bearbeiten_schwimmer.php?id=<?php echo $row['id']; ?>"
                                   class="btn btn-edit" title="Bearbeiten">
                                    Bearbeiten
                                </a>
                                <!-- Verknüpfung hinzufügen-Button -->
                                <a href="/neue_verknuepfung.php?schwimmer_id=<?php echo $row['id']; ?>"
                                   class="btn btn-link" title="Verknüpfung mit Sponsor">
                                    Verknüpfen
                                </a>
                                <!-- Spenden-Button -->
                                <a href="/schwimmer_spenden.php?schwimmer_id=<?php echo $row['id']; ?>"
                                   class="btn btn-primary" title="Spenden dieses Schwimmers">
                                    Spenden
                                </a>
                                <!-- Löschen-Button -->
                                <a href="/schwimmerliste.php?action=delete&id=<?php echo $row['id']; ?>"
                                   class="btn btn-delete"
                                   onclick="return confirm('Möchtest du diesen Teilnehmer wirklich löschen?')"
                                   title="Löschen">
                                    Löschen
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="no-data">Keine Teilnehmer gefunden.</td>
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
