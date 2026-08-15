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
$stmt = $conn->prepare("SELECT id, startnummer, vorname, nachname, geburtsjahr, schwimmleistung_vormittag, schwimmleistung_nachmittag, schwimmleistung_gesamt, team_id FROM Schwimmer WHERE id = ?");
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
    WHERE ss.schwimmer_id = ?";
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
    $schwimmleistung_vormittag = isset($_POST['schwimmleistung_vormittag']) ? max(0, intval($_POST['schwimmleistung_vormittag'])) : 0;
    $schwimmleistung_nachmittag = isset($_POST['schwimmleistung_nachmittag']) ? max(0, intval($_POST['schwimmleistung_nachmittag'])) : 0;
    $team_id_input = isset($_POST['team_id']) ? trim($_POST['team_id']) : '';

    // Validierung
    $fehler = [];
    if (empty($vorname)) $fehler[] = "Vorname ist erforderlich.";
    if (empty($nachname)) $fehler[] = "Nachname ist erforderlich.";
    if ($geburtsjahr < 1900 || $geburtsjahr > date('Y')) $fehler[] = "Ungültiges Geburtsjahr.";
    if ($schwimmleistung_vormittag < 0 || $schwimmleistung_nachmittag < 0) {
        $fehler[] = "Schwimmleistung darf nicht negativ sein.";
    }

    if (empty($fehler)) {
        // Team-Zuordnung (optional)
        $team_id = null;
        if ($team_id_input !== '') {
            $team_id = intval($team_id_input);
            $team_check = $conn->prepare("SELECT id FROM Teams WHERE id = ?");
            $team_check->bind_param("i", $team_id);
            $team_check->execute();
            $team_check->store_result();
            if ($team_check->num_rows === 0) {
                $team_id = null;
            }
            $team_check->close();
        }
        // Schwimmer aktualisieren (Startnummer bleibt unverändert)
        $stmt = $conn->prepare("UPDATE Schwimmer SET vorname = ?, nachname = ?, geburtsjahr = ?, schwimmleistung_vormittag = ?, schwimmleistung_nachmittag = ?, team_id = ? WHERE id = ?");
        $stmt->bind_param("ssiiiii", $vorname, $nachname, $geburtsjahr, $schwimmleistung_vormittag, $schwimmleistung_nachmittag, $team_id, $id);
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
            <label for="startnummer">Startnummer:</label>
            <input type="text" id="startnummer" name="startnummer"
                   value="<?php echo htmlspecialchars($schwimmer['startnummer']); ?>" readonly>
            <small>Die Startnummer wird beim Anlegen festgelegt und kann nicht geändert werden.</small>
        </div>
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
            <label for="team_id">Team (optional):</label>
            <select id="team_id" name="team_id">
                <option value="">Kein Team</option>
                <?php
                $teams_res = $conn->query("SELECT id, name FROM Teams ORDER BY name");
                if ($teams_res) {
                    while ($t = $teams_res->fetch_assoc()) {
                        $cur = isset($schwimmer['team_id']) ? $schwimmer['team_id'] : null;
                        $sel = ($cur !== null && intval($cur) === intval($t['id'])) ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars($t['id']) . '"' . $sel . '>' . htmlspecialchars($t['name']) . '</option>';
                    }
                }
                ?>
            </select>
            <small>Ein Schwimmer kann einem Team zugeordnet sein, muss aber nicht.</small>
        </div>
        <div class="form-group">
            <label for="schwimmleistung_vormittag">Schwimmleistung Vormittag (Bahnen):</label>
            <input type="number" id="schwimmleistung_vormittag" name="schwimmleistung_vormittag"
                   min="0" value="<?php echo htmlspecialchars($schwimmer['schwimmleistung_vormittag']); ?>">
            <small>0 = hat vormittags nicht geschwommen.</small>
        </div>
        <div class="form-group">
            <label for="schwimmleistung_nachmittag">Schwimmleistung Nachmittag (Bahnen):</label>
            <input type="number" id="schwimmleistung_nachmittag" name="schwimmleistung_nachmittag"
                   min="0" value="<?php echo htmlspecialchars($schwimmer['schwimmleistung_nachmittag']); ?>">
            <small>0 = hat nachmittags nicht geschwommen.</small>
        </div>
        <div class="form-group">
            <label>Gesamtleistung (automatisch):</label>
            <input type="text" value="<?php echo htmlspecialchars($schwimmer['schwimmleistung_gesamt']); ?> Bahnen" readonly>
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

    <!-- Zugeordnetes Team -->
    <?php
    $team_sql = "SELECT id, name, betrag_pro_bahn, `limit` FROM Teams WHERE id = ?";
    $team_stmt = $conn->prepare($team_sql);
    $team_stmt->bind_param("i", $schwimmer['team_id']);
    $team_stmt->execute();
    $team_result = $team_stmt->get_result();
    $team_row = $team_result->fetch_assoc();
    $team_stmt->close();
    ?>
    <div class="verknuepfungen-liste">
        <h3>Zugeordnetes Team</h3>
        <?php if ($team_row): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Spendensumme pro Bahn (€)</th>
                        <th>Limit</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo htmlspecialchars($team_row['name']); ?></td>
                        <td><?php echo number_format($team_row['betrag_pro_bahn'], 2, ',', '.'); ?></td>
                        <td>
                            <?php echo ($team_row['limit'] !== null) ? htmlspecialchars($team_row['limit']) : 'Ohne Limit'; ?>
                        </td>
                        <td class="actions">
                            <a href="/VAIBad_2/webapp/bearbeiten_team.php?id=<?php echo $team_row['id']; ?>"
                               class="btn btn-edit" title="Team bearbeiten">
                                Bearbeiten
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php else: ?>
            <p>keinem Team zugeordnet</p>
        <?php endif; ?>
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
