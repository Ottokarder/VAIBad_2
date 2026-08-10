<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Schwimmer-ID prüfen
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: /VAIBad_2/webapp/schwimmerliste.php");
    exit();
}

$id = intval($_GET['id']);

// Schwimmerdaten abrufen
$stmt = $conn->prepare("SELECT id, vorname, nachname, geburtsjahr, schwimmleistung FROM Schwimmer WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$schwimmer = $result->fetch_assoc();
$stmt->close();

if (!$schwimmer) {
    header("Location: /VAIBad_2/webapp/schwimmerliste.php");
    exit();
}

// Verknüpfte Sponsoren abrufen
$verknuepfungen_sql = "
    SELECT s.id, s.name, s.betrag_pro_bahn, s.`limit`
    FROM schwimmer_sponsor ss
    JOIN Sponsoren s ON ss.sponsoren_id = s.id
    WHERE ss.schwimmer_id = ?
";
$verknuepfungen_stmt = $conn->prepare($verknuepfungen_sql);
$verknuepfungen_stmt->bind_param("i", $id);
$verknuepfungen_stmt->execute();
$verknuepfungen_result = $verknuepfungen_stmt->get_result();
$verknuepfungen_stmt->close();

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $vorname = trim($_POST['vorname']);
    $nachname = trim($_POST['nachname']);
    $geburtsjahr = intval($_POST['geburtsjahr']);
    $schwimmleistung = (isset($_POST['schwimmleistung']) && $_POST['schwimmleistung'] != '') ? intval($_POST['schwimmleistung']) : NULL;

    // Validierung
    $fehler = [];
    if (empty($vorname)) $fehler[] = "Vorname ist erforderlich.";
    if (empty($nachname)) $fehler[] = "Nachname ist erforderlich.";
    if ($geburtsjahr < 1900 || $geburtsjahr > date('Y')) $fehler[] = "Ungültiges Geburtsjahr.";
    if (isset($_POST['schwimmleistung']) && $_POST['schwimmleistung'] != '' && ($schwimmleistung <= 0)) {
        $fehler[] = "Schwimmleistung muss größer als 0 sein.";
    }

    if (empty($fehler)) {
        // Schwimmer aktualisieren
        $stmt = $conn->prepare("UPDATE Schwimmer SET vorname = ?, nachname = ?, geburtsjahr = ?, schwimmleistung = ? WHERE id = ?");
        $stmt->bind_param("ssiii", $vorname, $nachname, $geburtsjahr, $schwimmleistung, $id);
        $stmt->execute();
        $stmt->close();

        // Weiterleitung zur Schwimmerliste
        header("Location: /VAIBad_2/webapp/schwimmerliste.php");
        exit();
    }
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
        <title>Schwimmer bearbeiten - VAIBad</title>
        <link rel="stylesheet" href="/VAIBad_2/webapp/css/style.css">
    </head>
    <body>';
}
?>

<div class="container">
    <h1>Schwimmer bearbeiten</h1>

    <!-- Fehler anzeigen -->
    <?php if (!empty($fehler)): ?>
        <div class="error-box">
            <ul>
                <?php foreach ($fehler as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Formular -->
    <form method="POST" action="/VAIBad_2/webapp/bearbeiten_schwimmer.php?id=<?php echo $id; ?>" class="form">
        <div class="form-group">
            <label for="vorname">Vorname:</label>
            <input type="text" id="vorname" name="vorname" required
                   value="<?php echo htmlspecialchars($schwimmer['vorname']); ?>">
        </div>

        <div class="form-group">
            <label for="nachname">Nachname:</label>
            <input type="text" id="nachname" name="nachname" required
                   value="<?php echo htmlspecialchars($schwimmer['nachname']); ?>">
        </div>

        <div class="form-group">
            <label for="geburtsjahr">Geburtsjahr:</label>
            <input type="number" id="geburtsjahr" name="geburtsjahr" required
                   min="1900" max="<?php echo date('Y'); ?>"
                   value="<?php echo htmlspecialchars($schwimmer['geburtsjahr']); ?>">
        </div>

        <div class="form-group">
            <label for="schwimmleistung">Schwimmleistung (Bahnen, optional):</label>
            <input type="number" id="schwimmleistung" name="schwimmleistung"
                   min="1" value="<?php echo ($schwimmer['schwimmleistung'] !== null) ? htmlspecialchars($schwimmer['schwimmleistung']) : ''; ?>">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Speichern</button>
            <a href="/VAIBad_2/webapp/schwimmerliste.php" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>

    <!-- Liste der zugeordneten Sponsoren -->
    <?php if ($verknuepfungen_result->num_rows > 0): ?>
        <div class="verknuepfungen-liste">
            <h3>Zugeordnete Sponsoren</h3>
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
                    <?php
                    // Da $verknuepfungen_result bereits durchlaufen wurde, müssen wir die Abfrage erneut ausführen
                    $verknuepfungen_stmt = $conn->prepare($verknuepfungen_sql);
                    $verknuepfungen_stmt->bind_param("i", $id);
                    $verknuepfungen_stmt->execute();
                    $verknuepfungen_result = $verknuepfungen_stmt->get_result();
                    while ($verknuepfung = $verknuepfungen_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($verknuepfung['name']); ?></td>
                            <td><?php echo number_format($verknuepfung['betrag_pro_bahn'], 2, ',', '.'); ?></td>
                            <td>
                                <?php echo ($verknuepfung['limit'] !== null) ? htmlspecialchars($verknuepfung['limit']) : 'Ohne Limit'; ?>
                            </td>
                            <td class="actions">
                                <a href="/VAIBad_2/webapp/entfernen_verknuepfung.php?schwimmer_id=<?php echo $id; ?>&sponsor_id=<?php echo $verknuepfung['id']; ?>"
                                   class="btn btn-delete"
                                   onclick="return confirm('Möchtest du diese Verknüpfung wirklich entfernen?')"
                                   title="Verknüpfung entfernen">
                                    Entfernen
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="verknuepfungen-liste">
            <p>Keine Sponsoren zugewiesen.</p>
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
