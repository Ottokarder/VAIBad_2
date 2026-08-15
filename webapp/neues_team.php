<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $betrag_pro_bahn = str_replace(',', '.', trim($_POST['betrag_pro_bahn']));
    $limit = (isset($_POST['limit']) && $_POST['limit'] != '') ? intval($_POST['limit']) : NULL;

    // Validierung
    $fehler = [];
    if (empty($name)) $fehler[] = "Name ist erforderlich.";
    if (!is_numeric($betrag_pro_bahn) || $betrag_pro_bahn <= 0) $fehler[] = "Spendensumme pro Bahn muss eine positive Zahl sein.";
    if (isset($_POST['limit']) && $_POST['limit'] != '' && ($limit <= 0)) {
        $fehler[] = "Limit muss größer als 0 sein.";
    }

    if (empty($fehler)) {
        // Team in die Datenbank einfügen
        $stmt = $conn->prepare("INSERT INTO Teams (name, betrag_pro_bahn, `limit`) VALUES (?, ?, ?)");
        $stmt->bind_param("sdi", $name, $betrag_pro_bahn, $limit);
        $stmt->execute();
        $stmt->close();

        // Weiterleitung zur Teamliste
        header("Location: /VAIBad_2/webapp/teams.php");
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
        <title>Neues Team - VAIBad</title>
        <link rel="stylesheet" href="/VAIBad_2/webapp/css/style.css">
    </head>
    <body>';
}
?>

<div class="container">
    <h1>Neues Team</h1>

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
    <form method="POST" action="/VAIBad_2/webapp/neues_team.php" class="form">
        <div class="form-group">
            <label for="name">Teamname:</label>
            <input type="text" id="name" name="name" required
                   value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="betrag_pro_bahn">Spendensumme pro Bahn (€):</label>
            <input type="text" id="betrag_pro_bahn" name="betrag_pro_bahn" required
                   value="<?php echo isset($betrag_pro_bahn) ? str_replace('.', ',', $betrag_pro_bahn) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="limit">Limit (optional, leer lassen für kein Limit):</label>
            <input type="number" id="limit" name="limit"
                   min="1" value="<?php echo isset($limit) ? $limit : ''; ?>">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Speichern</button>
            <a href="/VAIBad_2/webapp/teams.php" class="btn btn-secondary">Abbrechen</a>
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
