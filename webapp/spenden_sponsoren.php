<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Spendenlisten für Sponsoren - kompakte Übersicht mit Details-Funktion
// Sponsoren werden nach Namen zusammengefasst (GROUP BY)

// --- Summen pro Sponsor ---
$sponsoren = [];
$res = $conn->query("
    SELECT sp.id AS sponsor_id, sp.name AS sponsor_name,
           SUM(ss.spendenbetrag_vormittag) AS sum_vormittag,
           SUM(ss.spendenbetrag_nachmittag) AS sum_nachmittag,
           SUM(ss.spendenbetrag_gesamt) AS sum_gesamt,
           COUNT(*) AS anzahl
    FROM spenden_sponsoren ss
    JOIN Sponsoren sp ON ss.sponsoren_id = sp.id
    GROUP BY sp.id, sp.name
    ORDER BY sp.name
");
if ($res) { while ($r = $res->fetch_assoc()) $sponsoren[] = $r; $res->free(); }

// Gesamtsummen berechnen
$g_vormittag = 0.0;
$g_nachmittag = 0.0;
$g_gesamt = 0.0;
foreach ($sponsoren as $r) {
    $g_vormittag += (float)$r['sum_vormittag'];
    $g_nachmittag += (float)$r['sum_nachmittag'];
    $g_gesamt += (float)$r['sum_gesamt'];
}

// --- Details für alle Sponsoren abrufen (für Modal-Ansicht) ---
$sponsor_details = [];
foreach ($sponsoren as $sponsor) {
    $sid = (int)$sponsor['sponsor_id'];
    $stmt = $conn->prepare("
        SELECT ss.schwimmer_id,
               CONCAT(sw.vorname, ' ', sw.nachname) AS schwimmer_name,
               sw.startnummer,
               sw.schwimmleistung_vormittag,
               sw.schwimmleistung_nachmittag,
               ss.spendenbetrag_vormittag,
               ss.spendenbetrag_nachmittag,
               ss.spendenbetrag_gesamt
        FROM spenden_sponsoren ss
        JOIN Schwimmer sw ON ss.schwimmer_id = sw.id
        WHERE ss.sponsoren_id = ?
        ORDER BY sw.startnummer, sw.nachname, sw.vorname
    ");
    $stmt->bind_param("i", $sid);
    $stmt->execute();
    $dres = $stmt->get_result();
    $details = [];
    while ($dr = $dres->fetch_assoc()) {
        $details[] = $dr;
    }
    $stmt->close();
    $sponsor_details[$sid] = $details;
}

// Textfilter "enthält" aus GET holen (Filterung nach Sponsorname)
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';
if ($filter !== '') {
    $sponsoren = array_values(array_filter($sponsoren, function ($r) use ($filter) {
        return mb_stripos($r['sponsor_name'], $filter) !== false;
    }));
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
    foreach ($sponsoren as $r) {
        fputcsv($out, [
            $r['sponsor_name'], $r['anzahl'],
            $euro($r['sum_vormittag']), $euro($r['sum_nachmittag']), $euro($r['sum_gesamt'])
        ], ';');
    }
    fputcsv($out, ['Gesamtsumme', '', '', '', $euro($g_gesamt)], ';');

    // Abschnitt: Details (nur bei csv-details)
    if ($export_mode === 'csv-details') {
        fputcsv($out, [], ';');
        fputcsv($out, ['Spendenlisten - Sponsoren Details'], ';');
        fputcsv($out, ['Sponsor', 'Startnr.', 'Schwimmer', 'Bahnen VM', 'Bahnen NM', 'Vormittag', 'Nachmittag', 'Betrag'], ';');

        foreach ($sponsoren as $sponsor) {
            $sid = (int)$sponsor['sponsor_id'];
            if (isset($sponsor_details[$sid])) {
                foreach ($sponsor_details[$sid] as $dr) {
                    fputcsv($out, [
                        $sponsor['sponsor_name'],
                        $dr['startnummer'],
                        $dr['schwimmer_name'],
                        $dr['schwimmleistung_vormittag'],
                        $dr['schwimmleistung_nachmittag'],
                        $euro($dr['spendenbetrag_vormittag']),
                        $euro($dr['spendenbetrag_nachmittag']),
                        $euro($dr['spendenbetrag_gesamt'])
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
        <style>
            .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
            .modal-content { background-color: #fff; margin: 5% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 1000px; max-height: 80vh; overflow: auto; }
            .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
            .close:hover { color: #000; }
        </style>
    </head>
    <body>';
}
?>
<?php
// JavaScript für Modal-Funktion
if (!file_exists('includes/header.php')) {
    echo '<script>
    function showDetails(sponsorId, sponsorName) {
        document.getElementById("modal-title").textContent = "Details: " + sponsorName;
        document.getElementById("modal-body").innerHTML = document.getElementById("details-" + sponsorId).innerHTML;
        document.getElementById("detailsModal").style.display = "block";
    }
    function hideModal() {
        document.getElementById("detailsModal").style.display = "none";
    }
    window.onclick = function(event) {
        var modal = document.getElementById("detailsModal");
        if (event.target == modal) {
            hideModal();
        }
    }
    </script>';
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
    $leer = empty($sponsoren);
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
                <?php if (!empty($sponsoren)): ?>
                    <?php foreach ($sponsoren as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['sponsor_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['anzahl']); ?></td>
                            <td><?php echo number_format($r['sum_vormittag'], 2, ',', '.'); ?> €</td>
                            <td><?php echo number_format($r['sum_nachmittag'], 2, ',', '.'); ?> €</td>
                            <td><strong><?php echo number_format($r['sum_gesamt'], 2, ',', '.'); ?> €</strong></td>
                            <td>
                                <button onclick="showDetails(<?php echo $r['sponsor_id']; ?>, '<?php echo addslashes($r['sponsor_name']); ?>')" class="btn btn-primary">Details</button>
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

<!-- Modal für Details -->
<div id="detailsModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal()">&times;</span>
        <h2 id="modal-title">Details</h2>
        <div id="modal-body"></div>
    </div>
</div>

<!-- Versteckte Details-Tabellen für jeden Sponsor -->
<?php foreach ($sponsoren as $sponsor): ?>
    <?php $sid = (int)$sponsor['sponsor_id']; ?>
    <?php $details = isset($sponsor_details[$sid]) ? $sponsor_details[$sid] : []; ?>
    <div id="details-<?php echo $sid; ?>" style="display: none;">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Startnr.</th>
                        <th>Schwimmer</th>
                        <th>Bahnen VM</th>
                        <th>Bahnen NM</th>
                        <th>Spende Vormittag</th>
                        <th>Spende Nachmittag</th>
                        <th>Spendensumme</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($details)): ?>
                        <?php foreach ($details as $dr): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($dr['startnummer']); ?></strong></td>
                                <td><?php echo htmlspecialchars($dr['schwimmer_name']); ?></td>
                                <td><?php echo htmlspecialchars($dr['schwimmleistung_vormittag']); ?></td>
                                <td><?php echo htmlspecialchars($dr['schwimmleistung_nachmittag']); ?></td>
                                <td><?php echo number_format($dr['spendenbetrag_vormittag'], 2, ',', '.'); ?> €</td>
                                <td><?php echo number_format($dr['spendenbetrag_nachmittag'], 2, ',', '.'); ?> €</td>
                                <td><strong><?php echo number_format($dr['spendenbetrag_gesamt'], 2, ',', '.'); ?> €</strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="font-weight: bold; background-color: #f0f0f0;">
                            <td colspan="2">Summe</td>
                            <td><?php echo number_format(array_sum(array_column($details, 'schwimmleistung_vormittag')), 0, '', '.'); ?></td>
                            <td><?php echo number_format(array_sum(array_column($details, 'schwimmleistung_nachmittag')), 0, '', '.'); ?></td>
                            <td><?php echo number_format(array_sum(array_column($details, 'spendenbetrag_vormittag')), 2, ',', '.'); ?> €</td>
                            <td><?php echo number_format(array_sum(array_column($details, 'spendenbetrag_nachmittag')), 2, ',', '.'); ?> €</td>
                            <td><?php echo number_format(array_sum(array_column($details, 'spendenbetrag_gesamt')), 2, ',', '.'); ?> €</td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-data">Keine Details verfügbar.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>

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