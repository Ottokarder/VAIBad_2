<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Team-ID prüfen
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: teams.php");
    exit();
}

$id = intval($_GET['id']);

// Teamdaten abrufen
$stmt = $conn->prepare("SELECT id, name, betrag_pro_bahn, `limit` FROM Teams WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$team = $result->fetch_assoc();
$stmt->close();

if (!$team) {
    header("Location: teams.php");
    exit();
}

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
        // Team aktualisieren
        $stmt = $conn->prepare("UPDATE Teams SET name = ?, betrag_pro_bahn = ?, `limit` = ? WHERE id = ?");
        $stmt->bind_param("sdii", $name, $betrag_pro_bahn, $limit, $id);
        $stmt->execute();
        $stmt->close();

        // Weiterleitung zur Teamliste
        header("Location: teams.php");
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
        <title>Team bearbeiten - VAIBad</title>
        <link rel="stylesheet" href="/css/style.css">
    </head>
    <body>';
}
?>

<div class="container">
    <h1>Team bearbeiten</h1>

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
    <form method="POST" action="bearbeiten_team.php?id=<?php echo $id; ?>" class="form">
        <div class="form-group">
            <label for="name">Teamname:</label>
            <input type="text" id="name" name="name" required
                   value="<?php echo htmlspecialchars($team['name']); ?>">
        </div>

        <div class="form-group">
            <label for="betrag_pro_bahn">Spendensumme pro Bahn (€):</label>
            <input type="text" id="betrag_pro_bahn" name="betrag_pro_bahn" required
                   value="<?php echo str_replace('.', ',', $team['betrag_pro_bahn']); ?>">
        </div>

        <div class="form-group">
            <label for="limit">Limit (optional, leer lassen für kein Limit):</label>
            <input type="number" id="limit" name="limit"
                   min="1" value="<?php echo ($team['limit'] !== null) ? htmlspecialchars($team['limit']) : ''; ?>">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Speichern</button>
            <a href="teams.php" class="btn btn-secondary">Abbrechen</a>
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
