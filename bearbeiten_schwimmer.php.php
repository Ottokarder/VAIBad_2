<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Schwimmer-ID prüfen
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: schwimmerliste.php");
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
    header("Location: schwimmerliste.php");
    exit();
}

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $vorname = trim($_POST['vorname']);
    $nachname = trim($_POST['nachname']);
    $geburtsjahr = intval($_POST['geburtsjahr']);
    $schwimmleistung = intval($_POST['schwimmleistung']);

    // Validierung
    $fehler = [];
    if (empty($vorname)) $fehler[] = "Vorname ist erforderlich.";
    if (empty($nachname)) $fehler[] = "Nachname ist erforderlich.";
    if ($geburtsjahr < 1900 || $geburtsjahr > date('Y')) $fehler[] = "Ungültiges Geburtsjahr.";
    if ($schwimmleistung <= 0) $fehler[] = "Schwimmleistung muss größer als 0 sein.";

    if (empty($fehler)) {
        // Schwimmer aktualisieren
        $stmt = $conn->prepare("UPDATE Schwimmer SET vorname = ?, nachname = ?, geburtsjahr = ?, schwimmleistung = ? WHERE id = ?");
        $stmt->bind_param("ssiii", $vorname, $nachname, $geburtsjahr, $schwimmleistung, $id);
        $stmt->execute();
        $stmt->close();

        // Weiterleitung zur Schwimmerliste
        header("Location: schwimmerliste.php");
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
        <link rel="stylesheet" href="css/style.css">
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
    <form method="POST" action="bearbeiten_schwimmer.php?id=<?php echo $id; ?>" class="form">
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
            <label for="schwimmleistung">Schwimmleistung (Bahnen):</label>
            <input type="number" id="schwimmleistung" name="schwimmleistung" required
                   min="1" value="<?php echo htmlspecialchars($schwimmer['schwimmleistung']); ?>">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Speichern</button>
            <a href="schwimmerliste.php" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
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
