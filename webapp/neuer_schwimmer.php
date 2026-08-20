<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $vorname = trim($_POST['vorname']);
    $nachname = trim($_POST['nachname']);
    $geburtsjahr = intval($_POST['geburtsjahr']);
    $startnummer_input = isset($_POST['startnummer']) ? trim($_POST['startnummer']) : '';
    $team_ids_input = isset($_POST['team_ids']) ? $_POST['team_ids'] : [];

    // Validierung
    $fehler = [];
    if (empty($vorname)) $fehler[] = "Vorname ist erforderlich.";
    if (empty($nachname)) $fehler[] = "Nachname ist erforderlich.";
    if ($geburtsjahr < 1900 || $geburtsjahr > date('Y')) $fehler[] = "Ungültiges Geburtsjahr.";
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
        $stmt = $conn->prepare("INSERT INTO Schwimmer (startnummer, vorname, nachname, geburtsjahr) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $startnummer, $vorname, $nachname, $geburtsjahr);
        $stmt->execute();
        $neuer_schwimmer_id = $stmt->insert_id;
        $stmt->close();
        // Team-Zuordnungen (mehrfach möglich)
        if (!empty($team_ids_input)) {
            $team_insert = $conn->prepare("INSERT IGNORE INTO schwimmer_team (schwimmer_id, team_id) VALUES (?, ?)");
            foreach ($team_ids_input as $tid) {
                $tid_int = intval($tid);
                if ($tid_int > 0) {
                    $team_insert->bind_param("ii", $neuer_schwimmer_id, $tid_int);
                    $team_insert->execute();
                }
            }
            $team_insert->close();
        }
        // Weiterleitung zur Schwimmerliste
        header("Location: /schwimmerliste.php");
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
        <link rel="stylesheet" href="/css/style.css">
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
    <form method="POST" action="/neuer_schwimmer.php" class="form">
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
            <label for="team_ids">Teams (optional, Mehrfachauswahl möglich):</label>
            <select id="team_ids" name="team_ids[]" multiple size="5">
                <?php
                $teams_res = $conn->query("SELECT id, name FROM Teams ORDER BY name");
                if ($teams_res) {
                    $gewaehlt = isset($team_ids_input) ? array_map('intval', $team_ids_input) : [];
                    while ($t = $teams_res->fetch_assoc()) {
                        $sel = in_array(intval($t['id']), $gewaehlt, true) ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars($t['id']) . '"' . $sel . '>' . htmlspecialchars($t['name']) . '</option>';
                    }
                }
                ?>
            </select>
            <small>Mehrere Teams mit gedrückter Strg-/Cmd-Taste auswählen. Ein Schwimmer kann für mehrere Teams schwimmen.</small>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Speichern</button>
            <a href="/schwimmerliste.php" class="btn btn-secondary">Abbrechen</a>
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
