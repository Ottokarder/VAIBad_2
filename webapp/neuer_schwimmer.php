<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $vorname = trim($_POST['vorname']);
    $nachname = trim($_POST['nachname']);
    $geburtsjahr = intval($_POST['geburtsjahr']);
    $schwimmleistung_vormittag = isset($_POST['schwimmleistung_vormittag']) ? max(0, intval($_POST['schwimmleistung_vormittag'])) : 0;
    $schwimmleistung_nachmittag = isset($_POST['schwimmleistung_nachmittag']) ? max(0, intval($_POST['schwimmleistung_nachmittag'])) : 0;
    $startnummer_input = isset($_POST['startnummer']) ? trim($_POST['startnummer']) : '';

    // Validierung
    $fehler = [];
    if (empty($vorname)) $fehler[] = "Vorname ist erforderlich.";
    if (empty($nachname)) $fehler[] = "Nachname ist erforderlich.";
    if ($geburtsjahr < 1900 || $geburtsjahr > date('Y')) $fehler[] = "Ungültiges Geburtsjahr.";
    if ($schwimmleistung_vormittag < 0 || $schwimmleistung_nachmittag < 0) {
        $fehler[] = "Schwimmleistung darf nicht negativ sein.";
    }

    $startnummer = null;
    if ($startnummer_input !== '') {
        $startnummer = intval($startnummer_input);
        if ($startnummer <= 0) {
            $fehler[] = "Bitte geben Sie eine gültige Startnummer ein.";
        } else {
            $check = $conn->prepare("SELECT id FROM Schwimmer WHERE startnummer = ?");
            $check->bind_param("i", $startnummer);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $fehler[] = "Die Startnummer " . $startnummer . " ist bereits vergeben.";
            }
            $check->close();
        }
    }

    if (empty($fehler)) {
        if ($startnummer === null) {
            $res = $conn->query("SELECT COALESCE(MAX(startnummer), 0) + 1 AS next_nr FROM Schwimmer");
            $row = $res->fetch_assoc();
            $startnummer = intval($row['next_nr']);
        }
        // Schwimmer in die Datenbank einfügen
        $stmt = $conn->prepare("INSERT INTO Schwimmer (startnummer, vorname, nachname, geburtsjahr, schwimmleistung_vormittag, schwimmleistung_nachmittag) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issiii", $startnummer, $vorname, $nachname, $geburtsjahr, $schwimmleistung_vormittag, $schwimmleistung_nachmittag);
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
            <label for="startnummer">Startnummer (leer = automatisch):</label>
            <input type="number" id="startnummer" name="startnummer" min="1"
                   value="<?php echo isset($startnummer_input) ? htmlspecialchars($startnummer_input) : ''; ?>"
                   placeholder="automatisch">
            <small>Beim Anlegen festgelegt, danach nicht mehr änderbar.</small>
        </div>
        <div class="form-group">
            <label for="schwimmleistung_vormittag">Schwimmleistung Vormittag (Bahnen):</label>
            <input type="number" id="schwimmleistung_vormittag" name="schwimmleistung_vormittag"
                   min="0" value="<?php echo isset($schwimmleistung_vormittag) ? htmlspecialchars($schwimmleistung_vormittag) : '0'; ?>">
            <small>0 = hat vormittags nicht geschwommen.</small>
        </div>
        <div class="form-group">
            <label for="schwimmleistung_nachmittag">Schwimmleistung Nachmittag (Bahnen):</label>
            <input type="number" id="schwimmleistung_nachmittag" name="schwimmleistung_nachmittag"
                   min="0" value="<?php echo isset($schwimmleistung_nachmittag) ? htmlspecialchars($schwimmleistung_nachmittag) : '0'; ?>">
            <small>0 = hat nachmittags nicht geschwommen.</small>
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
