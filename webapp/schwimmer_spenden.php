<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Schwimmer-ID prüfen
if (!isset($_GET['schwimmer_id']) || !is_numeric($_GET['schwimmer_id'])) {
    header("Location: /VAIBad_2/webapp/schwimmerliste.php");
    exit();
}

$schwimmer_id = intval($_GET['schwimmer_id']);

// Schwimmerdaten abrufen
$stmt = $conn->prepare("
    SELECT id, startnummer, vorname, nachname, geburtsjahr,
           schwimmleistung_vormittag, schwimmleistung_nachmittag, schwimmleistung_gesamt
    FROM Schwimmer WHERE id = ?
");
$stmt->bind_param("i", $schwimmer_id);
$stmt->execute();
$result = $stmt->get_result();
$schwimmer = $result->fetch_assoc();
$stmt->close();

if (!$schwimmer) {
    header("Location: /VAIBad_2/webapp/schwimmerliste.php");
    exit();
}

// Alter berechnen
$alter = (int)date('Y') - (int)$schwimmer['geburtsjahr'];

// Sponsoren-Spenden für diesen Schwimmer abrufen (nur Sponsoren, keine Teams/Hauptsponsoren)
$sponsoren_sql = "
    SELECT sp.name AS sponsor_name,
           ss.spendenbetrag_vormittag,
           ss.spendenbetrag_nachmittag,
           ss.spendenbetrag_gesamt
    FROM spenden_sponsoren ss
    JOIN Sponsoren sp ON ss.sponsoren_id = sp.id
    WHERE ss.schwimmer_id = ?
    ORDER BY sp.name
";
$stmt = $conn->prepare($sponsoren_sql);
$stmt->bind_param("i", $schwimmer_id);
$stmt->execute();
$sponsoren_result = $stmt->get_result();

// Summen berechnen
$sponsoren = [];
$sum_vormittag = 0.0;
$sum_nachmittag = 0.0;
$sum_gesamt = 0.0;
while ($row = $sponsoren_result->fetch_assoc()) {
    $sponsoren[] = $row;
    $sum_vormittag  += (float)$row['spendenbetrag_vormittag'];
    $sum_nachmittag += (float)$row['spendenbetrag_nachmittag'];
    $sum_gesamt     += (float)$row['spendenbetrag_gesamt'];
}
$stmt->close();

// CSV-Export: wenn ?export=csv, Datei direkt zum Download ausliefern.
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $dateiname = 'spenden_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $schwimmer['nachname'] . '_' . $schwimmer['vorname']) . '_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $dateiname . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8-BOM für Excel
    $euro = function($v) { return number_format((float)$v, 2, ',', ''); };

    fputcsv($out, ['Spenderliste für ' . $schwimmer['vorname'] . ' ' . $schwimmer['nachname'] . ' (Startnr. ' . $schwimmer['startnummer'] . ')'], ';');
    fputcsv($out, [], ';');
    fputcsv($out, ['Schwimmleistung Vormittag', $schwimmer['schwimmleistung_vormittag'] . ' Bahnen'], ';');
    fputcsv($out, ['Schwimmleistung Nachmittag', $schwimmer['schwimmleistung_nachmittag'] . ' Bahnen'], ';');
    fputcsv($out, ['Schwimmleistung Gesamt', $schwimmer['schwimmleistung_gesamt'] . ' Bahnen'], ';');
    fputcsv($out, [], ';');
    fputcsv($out, ['Sponsor', 'Vormittag', 'Nachmittag', 'Gesamt'], ';');
    foreach ($sponsoren as $r) {
        fputcsv($out, [
            $r['sponsor_name'],
            $euro($r['spendenbetrag_vormittag']),
            $euro($r['spendenbetrag_nachmittag']),
            $euro($r['spendenbetrag_gesamt'])
        ], ';');
    }
    fputcsv($out, ['Summe', $euro($sum_vormittag), $euro($sum_nachmittag), $euro($sum_gesamt)], ';');

    fclose($out);
    $conn->close();
    exit;
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
        <title>Spenden ' . htmlspecialchars($schwimmer['vorname'] . ' ' . $schwimmer['nachname']) . ' - VAIBad</title>
        <link rel="stylesheet" href="/VAIBad_2/webapp/css/style.css">
    </head>
    <body>';
}
?>
<div class="container">
    <h1>Spenden von <?php echo htmlspecialchars($schwimmer['vorname'] . ' ' . $schwimmer['nachname']); ?></h1>

    <div class="action-bar">
        <a href="/VAIBad_2/webapp/schwimmer_spenden.php?schwimmer_id=<?php echo $schwimmer_id; ?>&export=csv" class="btn btn-primary">Als CSV herunterladen</a>
        <a href="/VAIBad_2/webapp/schwimmerliste.php" class="btn btn-secondary">Zurück zur Schwimmerliste</a>
        <a href="/VAIBad_2/webapp/index.php" class="btn btn-secondary">Startseite</a>
    </div>

    <!-- Schwimmerdaten -->
    <h2 style="margin-top: 2rem;">Schwimmerdaten</h2>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Startnr.</th>
                    <th>Vorname</th>
                    <th>Nachname</th>
                    <th>Alter</th>
                    <th>Bahnen Vormittag</th>
                    <th>Bahnen Nachmittag</th>
                    <th>Bahnen Gesamt</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong><?php echo htmlspecialchars($schwimmer['startnummer']); ?></strong></td>
                    <td><?php echo htmlspecialchars($schwimmer['vorname']); ?></td>
                    <td><?php echo htmlspecialchars($schwimmer['nachname']); ?></td>
                    <td><?php echo htmlspecialchars($alter); ?></td>
                    <td><?php echo htmlspecialchars($schwimmer['schwimmleistung_vormittag']); ?></td>
                    <td><?php echo htmlspecialchars($schwimmer['schwimmleistung_nachmittag']); ?></td>
                    <td><strong><?php echo htmlspecialchars($schwimmer['schwimmleistung_gesamt']); ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Sponsoren-Spenden -->
    <h2 style="margin-top: 2rem;">Sponsoren und deren Spenden</h2>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sponsor</th>
                    <th>Spendenbetrag Vormittag</th>
                    <th>Spendenbetrag Nachmittag</th>
                    <th>Spendenbetrag Gesamt</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($sponsoren)): ?>
                    <?php foreach ($sponsoren as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['sponsor_name']); ?></td>
                            <td><?php echo number_format($r['spendenbetrag_vormittag'], 2, ',', '.'); ?> €</td>
                            <td><?php echo number_format($r['spendenbetrag_nachmittag'], 2, ',', '.'); ?> €</td>
                            <td><strong><?php echo number_format($r['spendenbetrag_gesamt'], 2, ',', '.'); ?> €</strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <!-- Summenzeile -->
                    <tr style="font-weight: bold; background-color: #f0f0f0;">
                        <td>Summe</td>
                        <td><?php echo number_format($sum_vormittag, 2, ',', '.'); ?> €</td>
                        <td><?php echo number_format($sum_nachmittag, 2, ',', '.'); ?> €</td>
                        <td><?php echo number_format($sum_gesamt, 2, ',', '.'); ?> €</td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="no-data">
                            Noch keine Sponsoren-Spenden für diesen Schwimmer berechnet.
                            <a href="/VAIBad_2/webapp/spendenberechnung.php">Jetzt berechnen</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Gesamtsumme -->
    <?php if (!empty($sponsoren)): ?>
    <div style="margin-top: 1.5rem; padding: 1rem; background-color: #e8f4e8; border-radius: 4px;">
        <strong style="font-size: 1.2rem;">Gesamtspende für <?php echo htmlspecialchars($schwimmer['vorname'] . ' ' . $schwimmer['nachname']); ?>:</strong>
        <?php echo number_format($sum_gesamt, 2, ',', '.'); ?> €
    </div>
    <?php endif; ?>
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
