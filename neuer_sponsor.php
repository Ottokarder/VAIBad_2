<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $betrag_pro_bahn = str_replace(',', '.', trim($_POST['betrag_pro_bahn']));
    $limit = intval($_POST['limit']);

    // Validierung
    $fehler = [];
    if (empty($name)) $fehler[] = "Name ist erforderlich.";
    if (!is_numeric($betrag_pro_bahn) || $betrag_pro_bahn <= 0) $fehler[] = "Betrag pro Bahn muss eine positive Zahl sein.";
    if ($limit <= 0) $fehler[] = "Limit muss größer als 0 sein.";

    if (empty($fehler)) {
        // Sponsor in die Datenbank einfügen
        $stmt = $conn->prepare("INSERT INTO Sponsoren (name, betrag_pro_bahn, `limit`) VALUES (?, ?, ?)");
        $stmt->bind_param("sdi", $name, $betrag_pro_bahn, $limit);
        $stmt->execute();
        $stmt->close();

        // Weiterleitung zur Sponsorenliste
        header("Location: sponsorenliste.php");
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
        <title>Neuer Sponsor - VAIBad</title>
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body>';
}
?>

<div class="container">
    <h1>Neuer Sponsor</h1>

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
    <form method="POST" action="neuer_sponsor.php" class="form">
        <div class="form-group">
            <label for="name">Name:</label>
            <input type="text" id="name" name="name" required
                   value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="betrag_pro_bahn">Betrag pro Bahn (€):</label>
            <input type="text" id="betrag_pro_bahn" name="betrag_pro_bahn" required
                   value="<?php echo isset($betrag_pro_bahn) ? str_replace('.', ',', $betrag_pro_bahn) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="limit">Limit:</label>
            <input type="number" id="limit" name="limit" required
                   min="1" value="<?php echo isset($limit) ? $limit : ''; ?>">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Speichern</button>
            <a href="sponsorenliste.php" class="btn btn-secondary">Abbrechen</a>
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
