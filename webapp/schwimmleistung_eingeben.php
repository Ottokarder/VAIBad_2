<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Schwimmer-ID prüfen
if (!isset($_GET['schwimmer_id']) || !is_numeric($_GET['schwimmer_id'])) {
    header("Location: /schwimmerliste.php");
    exit();
}

$schwimmer_id = intval($_GET['schwimmer_id']);

// Schwimmerdaten abrufen
$stmt = $conn->prepare("SELECT id, startnummer, vorname, nachname FROM Schwimmer WHERE id = ?");
$stmt->bind_param("i", $schwimmer_id);
$stmt->execute();
$result = $stmt->get_result();
$schwimmer = $result->fetch_assoc();
$stmt->close();

if (!$schwimmer) {
    header("Location: /schwimmerliste.php");
    exit();
}

// Aktuelle Schwimmleistung abrufen
$stmt = $conn->prepare("SELECT schwimmleistung_vormittag, schwimmleistung_nachmittag FROM Schwimmer WHERE id = ?");
$stmt->bind_param("i", $schwimmer_id);
$stmt->execute();
$result = $stmt->get_result();
$leistung = $result->fetch_assoc();
$stmt->close();

$vormittag_aktuell = $leistung['schwimmleistung_vormittag'] ?? 0;
$nachmittag_aktuell = $leistung['schwimmleistung_nachmittag'] ?? 0;

// Verknüpfte Sponsoren abrufen
$verknuepfungen_sql = "
    SELECT s.id, s.name, s.betrag_pro_bahn, s.`limit`
    FROM schwimmer_sponsor ss
    JOIN Sponsoren s ON ss.sponsoren_id = s.id
    WHERE ss.schwimmer_id = ?
    ORDER BY s.name";
$stmt = $conn->prepare($verknuepfungen_sql);
$stmt->bind_param("i", $schwimmer_id);
$stmt->execute();
$verknuepfungen_result = $stmt->get_result();
$verknuepfungen = [];
while ($row = $verknuepfungen_result->fetch_assoc()) {
    $verknuepfungen[] = $row;
}
$stmt->close();

// Formular verarbeiten
$fehler = [];
$erfolg = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $vormittag = isset($_POST['vormittag']) ? intval($_POST['vormittag']) : 0;
    $nachmittag = isset($_POST['nachmittag']) ? intval($_POST['nachmittag']) : 0;

    // Validierung
    if ($vormittag < 0) {
        $fehler[] = "Schwimmleistung Vormittag darf nicht negativ sein.";
    }
    if ($nachmittag < 0) {
        $fehler[] = "Schwimmleistung Nachmittag darf nicht negativ sein.";
    }

    if (empty($fehler)) {
        // Schwimmleistung in der Schwimmer-Tabelle aktualisieren
        $stmt = $conn->prepare("UPDATE Schwimmer SET schwimmleistung_vormittag = ?, schwimmleistung_nachmittag = ? WHERE id = ?");
        $stmt->bind_param("iii", $vormittag, $nachmittag, $schwimmer_id);
        $stmt->execute();
        $stmt->close();

        // Spenden für alle verknüpften Sponsoren berechnen und in spenden_sponsoren speichern
        // Zuerst alte Einträge für diesen Schwimmer löschen
        $conn->query("DELETE FROM spenden_sponsoren WHERE schwimmer_id = $schwimmer_id");

        // Neue Spenden berechnen und speichern
        $insert = $conn->prepare("
            INSERT INTO spenden_sponsoren
                (schwimmer_id, sponsoren_id, spendenbetrag_vormittag, spendenbetrag_nachmittag, spendenbetrag_gesamt)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($verknuepfungen as $sponsor) {
            // Vormittag: roh = bahnen * betrag_pro_bahn; gedeckelt auf limit (falls gesetzt)
            $roh_v = $vormittag * (float)$sponsor['betrag_pro_bahn'];
            if ($sponsor['limit'] !== null) {
                $betrag_v = min($roh_v, (float)$sponsor['limit']);
            } else {
                $betrag_v = $roh_v;
            }

            // Nachmittag: selbe Logik
            $roh_n = $nachmittag * (float)$sponsor['betrag_pro_bahn'];
            if ($sponsor['limit'] !== null) {
                $betrag_n = min($roh_n, (float)$sponsor['limit']);
            } else {
                $betrag_n = $roh_n;
            }

            $gesamt = $betrag_v + $betrag_n;

            $insert->bind_param("iiddd",
                $schwimmer_id,
                $sponsor['id'],
                $betrag_v,
                $betrag_n,
                $gesamt
            );
            $insert->execute();
        }
        $insert->close();

        // Aktualisierte Leistungen neu laden
        $vormittag_aktuell = $vormittag;
        $nachmittag_aktuell = $nachmittag;
        $erfolg = true;

        // Verknüpfungen neu laden für die Anzeige
        $stmt = $conn->prepare($verknuepfungen_sql);
        $stmt->bind_param("i", $schwimmer_id);
        $stmt->execute();
        $verknuepfungen_result = $stmt->get_result();
        $verknuepfungen = [];
        while ($row = $verknuepfungen_result->fetch_assoc()) {
            $verknuepfungen[] = $row;
        }
        $stmt->close();
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
        <title>Schwimmleistung eingeben - VAIBad</title>
        <link rel="stylesheet" href="/css/style.css">
    </head>
    <body>';
}
?>

<div class="container">
    <h1>Schwimmleistung eingeben für <?php echo htmlspecialchars($schwimmer['vorname'] . ' ' . $schwimmer['nachname']); ?></h1>
    <p style="margin-bottom: 2rem;">Startnummer: <strong><?php echo htmlspecialchars($schwimmer['startnummer']); ?></strong></p>

    <!-- Erfolgsmeldung -->
    <?php if ($erfolg): ?>
        <div class="success-box">
            Schwimmleistung gespeichert und Spenden automatisch berechnet!
        </div>
    <?php endif; ?>

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

    <!-- Formular für Schwimmleistung -->
    <form method="POST" action="/schwimmleistung_eingeben.php?schwimmer_id=<?php echo $schwimmer_id; ?>" class="form">
        <div class="form-group">
            <label for="vormittag">Schwimmleistung Vormittag (Bahnen):</label>
            <input type="number" id="vormittag" name="vormittag" value="<?php echo htmlspecialchars($vormittag_aktuell); ?>" min="0" required>
        </div>

        <div class="form-group">
            <label for="nachmittag">Schwimmleistung Nachmittag (Bahnen):</label>
            <input type="number" id="nachmittag" name="nachmittag" value="<?php echo htmlspecialchars($nachmittag_aktuell); ?>" min="0" required>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Schwimmleistung speichern</button>
            <a href="/schwimmerliste.php" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>

    <!-- Liste der zugeordneten Sponsoren mit Spendenberechnung -->
    <?php if (!empty($verknuepfungen)): ?>
    <h2 style="margin-top: 2rem;">Zugeteilte Sponsoren und berechnete Spenden</h2>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sponsor</th>
                    <th>Betrag pro Bahn (€)</th>
                    <th>Limit</th>
                    <th>Spende Vormittag (€)</th>
                    <th>Spende Nachmittag (€)</th>
                    <th>Spende Gesamt (€)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sum_vormittag = 0.0;
                $sum_nachmittag = 0.0;
                $sum_gesamt = 0.0;
                
                foreach ($verknuepfungen as $sponsor):
                    // Berechnung für die Anzeige
                    $roh_v = $vormittag_aktuell * (float)$sponsor['betrag_pro_bahn'];
                    if ($sponsor['limit'] !== null) {
                        $betrag_v = min($roh_v, (float)$sponsor['limit']);
                    } else {
                        $betrag_v = $roh_v;
                    }

                    $roh_n = $nachmittag_aktuell * (float)$sponsor['betrag_pro_bahn'];
                    if ($sponsor['limit'] !== null) {
                        $betrag_n = min($roh_n, (float)$sponsor['limit']);
                    } else {
                        $betrag_n = $roh_n;
                    }

                    $gesamt = $betrag_v + $betrag_n;
                    
                    $sum_vormittag += $betrag_v;
                    $sum_nachmittag += $betrag_n;
                    $sum_gesamt += $gesamt;
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($sponsor['name']); ?></td>
                        <td><?php echo number_format($sponsor['betrag_pro_bahn'], 2, ',', '.'); ?></td>
                        <td>
                            <?php echo ($sponsor['limit'] !== null) ? number_format($sponsor['limit'], 2, ',', '.') : 'Ohne Limit'; ?>
                        </td>
                        <td><?php echo number_format($betrag_v, 2, ',', '.'); ?></td>
                        <td><?php echo number_format($betrag_n, 2, ',', '.'); ?></td>
                        <td><strong><?php echo number_format($gesamt, 2, ',', '.'); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                <!-- Summenzeile -->
                <tr style="font-weight: bold; background-color: #f0f0f0;">
                    <td>Summe</td>
                    <td colspan="2"></td>
                    <td><?php echo number_format($sum_vormittag, 2, ',', '.'); ?></td>
                    <td><?php echo number_format($sum_nachmittag, 2, ',', '.'); ?></td>
                    <td><strong><?php echo number_format($sum_gesamt, 2, ',', '.'); ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div style="margin-top: 2rem; padding: 1rem; background-color: #fff3cd; border-radius: 4px;">
        <p><strong>Hinweis:</strong> Dieser Schwimmer hat noch keine Sponsoren zugeteilt. 
        Bitte verknüpfen Sie zunächst Sponsoren über die <a href="/neue_verknuepfung.php?schwimmer_id=<?php echo $schwimmer_id; ?>">Verknüpfungsseite</a>.</p>
    </div>
    <?php endif; ?>

    <!-- Link zurück zur Schwimmerliste -->
    <div class="action-bar" style="margin-top: 2rem;">
        <a href="/schwimmerliste.php" class="btn btn-secondary">Zurück zur Schwimmerliste</a>
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
