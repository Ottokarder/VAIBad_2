<?php
// Datenbankverbindung einbinden
require_once 'config.php';

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
        // Schwimmer in die Datenbank einfügen
        $stmt = $conn->prepare("INSERT INTO Schwimmer (vorname, nachname, geburtsjahr, schwimmleistung) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssii", $vorname, $nachname, $geburtsjahr, $schwimmleistung);
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
        <title>Neuer Schwimmer - VAIBad</title>
        <link rel="stylesheet" href="/VAIBad_2/webapp/css/style.css">
    </head>
    <body>';
}
?>

<div class="container">
    <h1>Neuer Schwimmer</h1>

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
    <form method="POST" action="/VAIBad_2/webapp/neuer_schwimmer.php" class="form">
        <div class="form-group">
            <label for="vorname">Vorname:</label>
            <input type="text" id="vorname" name="vorname" required
                   value="<?php echo isset($vorname) ? htmlspecialchars($vorname) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="nachname">Nachname:</label>
            <input type="text" id="nachname" name="nachname" required
                   value="<?php echo isset($nachname) ? htmlspecialchars($nachname) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="geburtsjahr">Geburtsjahr:</label>
            <input type="number" id="geburtsjahr" name="geburtsjahr" required
                   min="1900" max="<?php echo date('Y'); ?>"
                   value="<?php echo isset($geburtsjahr) ? $geburtsjahr : ''; ?>">
        </div>

        <div class="form-group">
            <label for="schwimmleistung">Schwimmleistung (Bahnen, optional):</label>
            <input type="number" id="schwimmleistung" name="schwimmleistung"
                   min="1" value="<?php echo isset($schwimmleistung) ? $schwimmleistung : ''; ?>">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Speichern</button>
            <a href="/VAIBad_2/webapp/schwimmerliste.php" class="btn btn-secondary">Abbrechen</a>
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
