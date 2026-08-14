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
    header("Location: /VAIBad_2/webapp/schwimmerliste.php");
    exit();
}

// Alle Schwimmer abrufen
$sql = "SELECT id, startnummer, vorname, nachname, geburtsjahr, schwimmleistung_vormittag, schwimmleistung_nachmittag, schwimmleistung_gesamt, erstelldatum FROM Schwimmer ORDER BY startnummer, nachname, vorname";
$result = $conn->query($sql);

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
        <link rel="stylesheet" href="/VAIBad_2/webapp/css/style.css">
    </head>
    <body>';
}
?>

<div class="container">
    <h1>Teilnehmerliste</h1>

    <!-- Button für neuen Schwimmer und Startseite -->
    <div class="action-bar">
        <a href="/VAIBad_2/webapp/neuer_schwimmer.php" class="btn btn-primary">Neuer Teilnehmer</a>
        <a href="/VAIBad_2/webapp/index.php" class="btn btn-secondary">Startseite</a>
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
                                <a href="/VAIBad_2/webapp/bearbeiten_schwimmer.php?id=<?php echo $row['id']; ?>"
                                   class="btn btn-edit" title="Bearbeiten">
                                    Bearbeiten
                                </a>
                                <!-- Verknüpfung hinzufügen-Button -->
                                <a href="/VAIBad_2/webapp/neue_verknuepfung.php?schwimmer_id=<?php echo $row['id']; ?>"
                                   class="btn btn-link" title="Verknüpfung mit Sponsor">
                                    Verknüpfen
                                </a>
                                <!-- Löschen-Button -->
                                <a href="/VAIBad_2/webapp/schwimmerliste.php?action=delete&id=<?php echo $row['id']; ?>"
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
