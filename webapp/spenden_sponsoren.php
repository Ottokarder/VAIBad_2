<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Spendenlisten für Sponsoren - kompakte Übersicht
// Jeder Sponsor wird einzeln angezeigt (kein Zusammenfassen)
// Details-Button verlinkt zu spender_details.php?typ=sponsor&id=X

// --- Alle Sponsoren-Einträge abrufen ---
$sponsoren = [];
$res = $conn->query("
    SELECT sp.id AS sponsor_id, sp.name AS sponsor_name, sp.betrag_pro_bahn,
           ss.spendenbetrag_vormittag, ss.spendenbetrag_nachmittag, ss.spendenbetrag_gesamt,
           CONCAT(sw.vorname, ' ', sw.nachname) AS schwimmer_name,
           sw.startnummer, sw.schwimmleistung_vormittag, sw.schwimmleistung_nachmittag
    FROM spenden_sponsoren ss
    JOIN Sponsoren sp ON ss.sponsoren_id = sp.id
    JOIN Schwimmer sw ON ss.schwimmer_id = sw.id
    ORDER BY sp.name, sw.startnummer, sw.nachname, sw.vorname
");
if ($res) { while ($r = $res->fetch_assoc()) $sponsoren[] = $r; $res->free(); }

// --- Summen pro Sponsor berechnen (für die Zusammenfassung) ---
$sponsor_sums = [];
foreach ($sponsoren as $r) {
    $sid = $r['sponsor_id'];
    if (!isset($sponsor_sums[$sid])) {
        $sponsor_sums[$sid] = [
            'name' => $r['sponsor_name'],
            'sum_vormittag' => 0,
            'sum_nachmittag' => 0,
            'sum_gesamt' => 0,
            'anzahl' => 0,
            'betrag_pro_bahn' => $r['betrag_pro_bahn']
        ];
    }
    $sponsor_sums[$sid]['sum_vormittag'] += (float)$r['spendenbetrag_vormittag'];
    $sponsor_sums[$sid]['sum_nachmittag'] += (float)$r['spendenbetrag_nachmittag'];
    $sponsor_sums[$sid]['sum_gesamt'] += (float)$r['spendenbetrag_gesamt'];
    $sponsor_sums[$sid]['anzahl']++;
}

// Gesamtsummen berechnen
$g_vormittag = 0.0;
$g_nachmittag = 0.0;
$g_gesamt = 0.0;
foreach ($sponsor_sums as $r) {
    $g_vormittag += (float)$r['sum_vormittag'];
    $g_nachmittag += (float)$r['sum_nachmittag'];
    $g_gesamt += (float)$r['sum_gesamt'];
}

// Textfilter "enthält" aus GET holen (Filterung nach Sponsorname)
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';
if ($filter !== '') {
    $sponsor_sums = array_filter($sponsor_sums, function ($r) use ($filter) {
        return mb_stripos($r['name'], $filter) !== false;
    });
}

// CSV-Export
if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'csv-details'], true)) {
    $export_mode = $_GET['export'];
    $dateiname = ($export_mode === 'csv-details') ? 'sponsoren_mit_details_' . date('Y-m-d_His') . '.csv' : 'sponsoren_' . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $dateiname . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $euro = function($v) { return number_format((float)$v, 2, ',', ''); };

    // Abschnitt: nur Summen
    fputcsv($out, ['Spendenlisten - Sponsoren Summen'], ';');
    fputcsv($out, ['Name', 'Anzahl Schwimmer', 'Summe Vormittag', 'Summe Nachmittag', 'Summe Gesamt'], ';');
    foreach ($sponsor_sums as $r) {
        fputcsv($out, [
            $r['name'], $r['anzahl'],
            $euro($r['sum_vormittag']), $euro($r['sum_nachmittag']), $euro($r['sum_gesamt'])
        ], ';');
    }
    fputcsv($out, ['Gesamtsumme', '', '', '', $euro($g_gesamt)], ';');

    // Abschnitt: Details (nur bei csv-details)
    if ($export_mode === 'csv-details') {
        fputcsv($out, [], ';');
        fputcsv($out, ['Spendenlisten - Sponsoren Details'], ';');
        fputcsv($out, ['Sponsor', 'Startnr.', 'Schwimmer', 'Bahnen VM', 'Bahnen NM', 'Vormittag', 'Nachmittag', 'Betrag'], ';');

        foreach ($sponsor_sums as $sid => $sum) {
            foreach ($sponsoren as $r) {
                if ($r['sponsor_id'] == $sid) {
                    fputcsv($out, [
                        $r['sponsor_name'],
                        $r['startnummer'],
                        $r['schwimmer_name'],
                        $r['schwimmleistung_vormittag'],
                        $r['schwimmleistung_nachmittag'],
                        $euro($r['spendenbetrag_vormittag']),
                        $euro($r['spendenbetrag_nachmittag']),
                        $euro($r['spendenbetrag_gesamt'])
                    ], ';');
                }
            }
        }
    }

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
        <title>Spendenlisten - Sponsoren - VAIBad</title>
        <link rel="stylesheet" href="/css/style.css">
    </head>
    <body>';
}
?>
<div class="container">
    <h1>Spendenlisten - Sponsoren</h1>

    <div class="action-bar">
        <a href="/spenden_sponsoren.php?export=csv<?php echo $filter !== '' ? '&filter=' . urlencode($filter) : ''; ?>" class="btn btn-primary">CSV (nur Summen)</a>
        <a href="/spenden_sponsoren.php?export=csv-details<?php echo $filter !== '' ? '&filter=' . urlencode($filter) : ''; ?>" class="btn btn-primary">CSV (mit Details)</a>
        <a href="/index.php" class="btn btn-secondary">Startseite</a>
    </div>

    <!-- Filter (enthält Sponsorname) -->
    <div class="action-bar" style="margin-bottom: 1rem;">
        <form method="GET" action="/spenden_sponsoren.php" class="form-inline" style="display:flex; gap:.5rem; flex-wrap:wrap; align-items:center;">
            <input type="text" name="filter" placeholder="Filter (Sponsorname enthält)..." value="<?php echo htmlspecialchars($filter); ?>" style="flex:1; min-width:200px; padding:.4rem .6rem;">
            <button type="submit" class="btn btn-primary">Filtern</button>
            <?php if ($filter !== ''): ?>
                <a href="/spenden_sponsoren.php" class="btn btn-secondary">Zurücksetzen</a>
            <?php endif; ?>
        </form>
    </div>

    <?php
    $leer = empty($sponsor_sums);
    if ($leer):
    ?>
        <div class="error-box" style="margin: 1rem 0;">
            Hinweis: Die Listen sind leer. Bitte zuerst die Spendenberechnung durchführen:
            <a href="/spendenberechnung.php">Sponsoren</a>.
        </div>
    <?php endif; ?>

    <!-- Sponsoren-Tabelle -->
    <h2 style="margin-top: 2rem;">Sponsoren</h2>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Anzahl Schwimmer</th>
                    <th>Summe Vormittag</th>
                    <th>Summe Nachmittag</th>
                    <th>Summe Gesamt</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($sponsor_sums)): ?>
                    <?php foreach ($sponsor_sums as $sid => $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['name']); ?></td>
                            <td><?php echo htmlspecialchars($r['anzahl']); ?></td>
                            <td><?php echo number_format($r['sum_vormittag'], 2, ',', '.'); ?> €</td>
                            <td><?php echo number_format($r['sum_nachmittag'], 2, ',', '.'); ?> €</td>
                            <td><strong><?php echo number_format($r['sum_gesamt'], 2, ',', '.'); ?> €</strong></td>
                            <td>
                                <a href="/spender_details.php?typ=sponsor&id=<?php echo $sid; ?>" class="btn btn-primary">Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <!-- Summenzeile -->
                    <tr style="font-weight: bold; background-color: #f0f0f0;">
                        <td>Gesamtsumme</td>
                        <td></td>
                        <td><?php echo number_format($g_vormittag, 2, ',', '.'); ?> €</td>
                        <td><?php echo number_format($g_nachmittag, 2, ',', '.'); ?> €</td>
                        <td><?php echo number_format($g_gesamt, 2, ',', '.'); ?> €</td>
                        <td></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="no-data">Noch keine Daten. Bitte zuerst die Spendenberechnung durchführen.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Gesamtsumme aller Spenden -->
    <div style="margin-top: 1.5rem; padding: 1rem; background-color: #e8f4e8; border-radius: 4px;">
        <strong style="font-size: 1.2rem;">Gesamtsumme aller Sponsoren-Spenden:</strong>
        <?php echo number_format($g_gesamt, 2, ',', '.'); ?> €
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