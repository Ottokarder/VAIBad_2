<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Gespeicherte Spenden-Ergebnisse abrufen (mit Schwimmer- und Sponsor-Namen).
$sql = "
    SELECT ss.schwimmer_id,
           ss.sponsoren_id,
           ss.spendenbetrag_vormittag,
           ss.spendenbetrag_nachmittag,
           ss.spendenbetrag_gesamt,
           ss.erstelldatum,
           CONCAT(sw.vorname, ' ', sw.nachname) AS schwimmer_name,
           sw.startnummer,
           sp.name AS sponsor_name
    FROM spenden_sponsoren ss
    JOIN Schwimmer sw ON ss.schwimmer_id = sw.id
    JOIN Sponsoren sp ON ss.sponsoren_id = sp.id
    ORDER BY sw.startnummer, sw.nachname, sw.vorname, sp.name
";
$result = $conn->query($sql);

// Gesamtsummen für die Fußzeile berechnen.
$summe_vormittag = 0.0;
$summe_nachmittag = 0.0;
$summe_gesamt = 0.0;
$anzahl = 0;
if ($result && $result->num_rows > 0) {
    // Ergebnis zwischenspeichern, um Summen zu bilden und danach auszugeben.
    $zeilen = [];
    while ($row = $result->fetch_assoc()) {
        $zeilen[] = $row;
        $summe_vormittag  += (float)$row['spendenbetrag_vormittag'];
        $summe_nachmittag += (float)$row['spendenbetrag_nachmittag'];
        $summe_gesamt     += (float)$row['spendenbetrag_gesamt'];
        $anzahl++;
    }
    $result->free();
} else {
    $zeilen = [];
}

// CSV-Export: wenn ?export=csv, Datei direkt zum Download ausliefern.
// Trenner Semikolon + UTF-8-BOM, damit Excel die Datei mit Umlauten korrekt öffnet.
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $dateiname = 'spendenbetr_' . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $dateiname . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $out = fopen('php://output', 'w');
    // UTF-8-BOM für Excel
    fwrite($out, "\xEF\xBB\xBF");
    // Spaltenüberschriften
    fputcsv($out, [
        'Startnummer', 'Schwimmer', 'Sponsor',
        'Spendenbetrag Vormittag', 'Spendenbetrag Nachmittag', 'Spendenbetrag Gesamt',
        'Erstelldatum'
    ], ';');
    // Datenzeilen
    foreach ($zeilen as $r) {
        fputcsv($out, [
            $r['startnummer'],
            $r['schwimmer_name'],
            $r['sponsor_name'],
            number_format($r['spendenbetrag_vormittag'], 2, ',', ''),
            number_format($r['spendenbetrag_nachmittag'], 2, ',', ''),
            number_format($r['spendenbetrag_gesamt'], 2, ',', ''),
            date('d.m.Y H:i', strtotime($r['erstelldatum']))
        ], ';');
    }
    // Summenzeile
    fputcsv($out, [
        '', 'Summe (' . $anzahl . ' Einträge)', '',
        number_format($summe_vormittag, 2, ',', ''),
        number_format($summe_nachmittag, 2, ',', ''),
        number_format($summe_gesamt, 2, ',', ''),
        ''
    ], ';');
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
        <title>Spendenbeträge - VAIBad</title>
        <link rel="stylesheet" href="/css/style.css">
    </head>
    <body>';
}
?>
<div class="container">
    <h1>Spendenbeträge</h1>

    <div class="action-bar">
        <a href="/spendenberechnung.php" class="btn btn-primary">Neu berechnen</a>
        <a href="/spenden_sponsoren.php?export=csv" class="btn btn-primary">Als CSV herunterladen</a>
        <a href="/index.php" class="btn btn-secondary">Startseite</a>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Startnr.</th>
                    <th>Schwimmer</th>
                    <th>Sponsor</th>
                    <th>Spendenbetrag Vormittag</th>
                    <th>Spendenbetrag Nachmittag</th>
                    <th>Spendenbetrag Gesamt</th>
                    <th>Erstelldatum</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($zeilen)): ?>
                    <?php foreach ($zeilen as $row): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['startnummer']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['schwimmer_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['sponsor_name']); ?></td>
                            <td><?php echo number_format($row['spendenbetrag_vormittag'], 2, ',', '.'); ?> €</td>
                            <td><?php echo number_format($row['spendenbetrag_nachmittag'], 2, ',', '.'); ?> €</td>
                            <td><strong><?php echo number_format($row['spendenbetrag_gesamt'], 2, ',', '.'); ?> €</strong></td>
                            <td><?php echo date('d.m.Y H:i', strtotime($row['erstelldatum'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <!-- Summenzeile -->
                    <tr style="font-weight: bold; background-color: #f0f0f0;">
                        <td colspan="3">Summe (<?php echo $anzahl; ?> Einträge)</td>
                        <td><?php echo number_format($summe_vormittag, 2, ',', '.'); ?> €</td>
                        <td><?php echo number_format($summe_nachmittag, 2, ',', '.'); ?> €</td>
                        <td><?php echo number_format($summe_gesamt, 2, ',', '.'); ?> €</td>
                        <td></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="no-data">
                            Noch keine Spendenbeträge berechnet.
                            <a href="/spendenberechnung.php">Jetzt berechnen</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
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
