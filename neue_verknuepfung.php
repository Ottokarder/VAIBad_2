<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Schwimmer-ID prüfen
if (!isset($_GET['schwimmer_id']) || !is_numeric($_GET['schwimmer_id'])) {
    header("Location: schwimmerliste.php");
    exit();
}

$schwimmer_id = intval($_GET['schwimmer_id']);

// Schwimmerdaten abrufen
$stmt = $conn->prepare("SELECT id, vorname, nachname FROM Schwimmer WHERE id = ?");
$stmt->bind_param("i", $schwimmer_id);
$stmt->execute();
$result = $stmt->get_result();
$schwimmer = $result->fetch_assoc();
$stmt->close();

if (!$schwimmer) {
    header("Location: schwimmerliste.php");
    exit();
}

// Alle Sponsoren abrufen
$sponsoren_result = $conn->query("SELECT id, betrag_pro_bahn, `limit` FROM Sponsoren ORDER BY id");

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sponsoren_id = intval($_POST['sponsoren_id']);

    // Validierung
    $fehler = [];
    if ($sponsoren_id <= 0) {
        $fehler[] = "Bitte wählen Sie einen Sponsor aus.";
    } else {
        // Prüfen, ob die Verknüpfung bereits existiert
        $stmt = $conn->prepare("SELECT id FROM schwimmer_sponsor WHERE schwimmer_id = ? AND sponsoren_id = ?");
        $stmt->bind_param("ii", $schwimmer_id, $sponsoren_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $fehler[] = "Diese Verknüpfung existiert bereits.";
        }
        $stmt->close();
    }

    if (empty($fehler)) {
        // Verknüpfung einfügen
        $stmt = $conn->prepare("INSERT INTO schwimmer_sponsor (schwimmer_id, sponsoren_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $schwimmer_id, $sponsoren_id);
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
        <title>Neue Verknüpfung - VAIBad</title>
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body>';
}
?>

<div class="container">
    <h1>Neue Verknüpfung für <?php echo htmlspecialchars($schwimmer['vorname'] . ' ' . $schwimmer['nachname']); ?></h1>

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
    <form method="POST" action="neue_verknuepfung.php?schwimmer_id=<?php echo $schwimmer_id; ?>" class="form">
        <div class="form-group">
            <label for="sponsoren_id">Sponsor auswählen:</label>
            <select id="sponsoren_id" name="sponsoren_id" required>
                <option value="">-- Bitte auswählen --</option>
                <?php while ($sponsor = $sponsoren_result->fetch_assoc()): ?>
                    <option value="<?php echo $sponsor['id']; ?>">
                        Sponsor #<?php echo $sponsor['id']; ?>
                        (<?php echo htmlspecialchars($sponsor['betrag_pro_bahn']); ?> € pro Bahn,
                        Limit: <?php echo htmlspecialchars($sponsor['limit']); ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Verknüpfung speichern</button>
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
