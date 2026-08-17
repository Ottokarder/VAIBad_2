<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Schwimmer-ID prüfen
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: /schwimmerliste.php");
    exit();
}
$id = intval($_GET['id']);

// Schwimmerdaten abrufen
$stmt = $conn->prepare("SELECT id, startnummer, vorname, nachname, geburtsjahr, schwimmleistung_vormittag, schwimmleistung_nachmittag, schwimmleistung_gesamt FROM Schwimmer WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$schwimmer = $result->fetch_assoc();
$stmt->close();

if (!$schwimmer) {
    header("Location: /schwimmerliste.php");
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
    $team_ids_input = isset($_POST['team_ids']) ? $_POST['team_ids'] : [];

    // Validierung
    $fehler = [];
    if (empty($vorname)) $fehler[] = "Vorname ist erforderlich.";
    if (empty($nachname)) $fehler[] = "Nachname ist erforderlich.";
    if ($geburtsjahr < 1900 || $geburtsjahr > date('Y')) $fehler[] = "Ungültiges Geburtsjahr.";
    if ($schwimmleistung_vormittag < 0 || $schwimmleistung_nachmittag < 0) {
        $fehler[] = "Schwimmleistung darf nicht negativ sein.";
    }

    if (empty($fehler)) {
        // Schwimmer aktualisieren (Startnummer bleibt unverändert)
        $stmt = $conn->prepare("UPDATE Schwimmer SET vorname = ?, nachname = ?, geburtsjahr = ?, schwimmleistung_vormittag = ?, schwimmleistung_nachmittag = ? WHERE id = ?");
        $stmt->bind_param("ssiiii", $vorname, $nachname, $geburtsjahr, $schwimmleistung_vormittag, $schwimmleistung_nachmittag, $id);
        $stmt->execute();
        $stmt->close();
        // Team-Zuordnungen synchronisieren (mehrfach möglich)
        $conn->query("DELETE FROM schwimmer_team WHERE schwimmer_id = " . intval($id));
        if (!empty($team_ids_input)) {
            $team_insert = $conn->prepare("INSERT IGNORE INTO schwimmer_team (schwimmer_id, team_id) VALUES (?, ?)");
            foreach ($team_ids_input as $tid) {
                $tid_int = intval($tid);
                if ($tid_int > 0) {
                    $team_insert->bind_param("ii", $id, $tid_int);
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
        <title>Schwimmer bearbeiten - VAIBad</title>
        <link rel="stylesheet" href="/css/style.css">
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
    <form method="POST" action="/bearbeiten_schwimmer.php?id=<?php echo $id; ?>" class="form">
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
            <label for="team_ids">Teams (optional, Mehrfachauswahl möglich):</label>
            <select id="team_ids" name="team_ids[]" multiple size="5">
                <?php
                $teams_res = $conn->query("SELECT id, name FROM Teams ORDER BY name");
                // Bereits zugeordnete Teams ermitteln
                $gewaehlte_teams = [];
                $zt_stmt = $conn->prepare("SELECT team_id FROM schwimmer_team WHERE schwimmer_id = ?");
                $zt_stmt->bind_param("i", $id);
                $zt_stmt->execute();
                $zt_res = $zt_stmt->get_result();
                while ($zt = $zt_res->fetch_assoc()) {
                    $gewaehlte_teams[] = intval($zt['team_id']);
                }
                $zt_stmt->close();
                if ($teams_res) {
                    while ($t = $teams_res->fetch_assoc()) {
                        $sel = in_array(intval($t['id']), $gewaehlte_teams, true) ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars($t['id']) . '"' . $sel . '>' . htmlspecialchars($t['name']) . '</option>';
                    }
                }
                ?>
            </select>
            <small>Mehrere Teams mit gedrückter Strg-/Cmd-Taste auswählen. Ein Schwimmer kann für mehrere Teams schwimmen.</small>
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
            <a href="/schwimmerliste.php" class="btn btn-secondary">Abbrechen</a>
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
                                <a href="/entfernen_verknuepfung.php?schwimmer_id=<?php echo $id; ?>&sponsor_id=<?php echo $verknuepfung['id']; ?>"
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

    <!-- Zugeordnete Teams -->
    <?php
    $teams_sql = "
        SELECT t.id, t.name, t.betrag_pro_bahn, t.`limit`
        FROM schwimmer_team st
        JOIN Teams t ON st.team_id = t.id
        WHERE st.schwimmer_id = ?
        ORDER BY t.name";
    $teams_stmt = $conn->prepare($teams_sql);
    $teams_stmt->bind_param("i", $id);
    $teams_stmt->execute();
    $teams_verknuepfungen_result = $teams_stmt->get_result();
    $teams_stmt->close();
    ?>
    <div class="verknuepfungen-liste">
        <h3>Zugeordnete Teams</h3>
        <?php if ($teams_verknuepfungen_result->num_rows > 0): ?>
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
                    <?php
                    $teams_stmt2 = $conn->prepare($teams_sql);
                    $teams_stmt2->bind_param("i", $id);
                    $teams_stmt2->execute();
                    $teams_verknuepfungen_result = $teams_stmt2->get_result();
                    while ($team_verknuepfung = $teams_verknuepfungen_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($team_verknuepfung['name']); ?></td>
                            <td><?php echo number_format($team_verknuepfung['betrag_pro_bahn'], 2, ',', '.'); ?></td>
                            <td>
                                <?php echo ($team_verknuepfung['limit'] !== null) ? htmlspecialchars($team_verknuepfung['limit']) : 'Ohne Limit'; ?>
                            </td>
                            <td class="actions">
                                <a href="/entfernen_verknuepfung_team.php?schwimmer_id=<?php echo $id; ?>&team_id=<?php echo $team_verknuepfung['id']; ?>"
                                   class="btn btn-delete"
                                   onclick="return confirm('Möchtest du diese Team-Zuordnung wirklich entfernen?')"
                                   title="Team-Zuordnung entfernen">
                                    Entfernen
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
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
