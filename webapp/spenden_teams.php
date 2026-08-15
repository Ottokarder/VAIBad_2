<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Gespeicherte Team-Spenden-Ergebnisse abrufen (mit Team- und Schwimmer-Namen).
$sql = "
    SELECT st.team_id,
           st.schwimmer_id,
           st.spendenbetrag_vormittag,
           st.spendenbetrag_nachmittag,
           st.spendenbetrag_gesamt,
           st.spendenbetrag_gedeckelt,
           st.erstelldatum,
           t.name AS team_name,
           t.`limit` AS team_limit,
           CONCAT(sw.vorname, ' ', sw.nachname) AS schwimmer_name,
           sw.startnummer
    FROM spenden_teams st
    JOIN Teams t ON st.team_id = t.id
    JOIN Schwimmer sw ON st.schwimmer_id = sw.id
    ORDER BY t.name, sw.startnummer, sw.nachname, sw.vorname
";
$result = $conn->query($sql);

// Ergebnisse zwischenspeichern und nach Team gruppieren (für Zwischensummen).
$gruppen = [];        // team_id => [info => [...], zeilen => [...], summen => [...]]
$gesamt_summe = 0.0;
$gesamt_gedeckelt = 0.0;
$anzahl = 0;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tid = $row['team_id'];
        if (!isset($gruppen[$tid])) {
            $gruppen[$tid] = [
                'info'    => ['team_name' => $row['team_name'], 'team_limit' => $row['team_limit']],
                'zeilen'  => [],
                'sum_gesamt'    => 0.0,
                'sum_gedeckelt' => 0.0,
            ];
        }
        $gruppen[$tid]['zeilen'][] = $row;
        $gruppen[$tid]['sum_gesamt']    += (float)$row['spendenbetrag_gesamt'];
        $gruppen[$tid]['sum_gedeckelt'] += (float)$row['spendenbetrag_gedeckelt'];
        $gesamt_summe      += (float)$row['spendenbetrag_gesamt'];
        $gesamt_gedeckelt  += (float)$row['spendenbetrag_gedeckelt'];
        $anzahl++;
    }
    $result->free();
}

// CSV-Export: wenn ?export=csv, Datei direkt zum Download ausliefern.
// Trenner Semikolon + UTF-8-BOM, damit Excel die Datei mit Umlauten korrekt öffnet.
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $dateiname = 'team_spendenbetr_' . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $dateiname . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $out = fopen('php://output', 'w');
    // UTF-8-BOM für Excel
    fwrite($out, "\xEF\xBB\xBF");
    // Spaltenüberschriften
    fputcsv($out, [
        'Team', 'Startnummer', 'Schwimmer',
        'Spendenbetrag Vormittag', 'Spendenbetrag Nachmittag', 'Spendenbetrag Gesamt',
        'Spendenbetrag gedeckelt', 'Erstelldatum'
    ], ';');

    foreach ($gruppen as $tid => $g) {
        foreach ($g['zeilen'] as $r) {
            fputcsv($out, [
                $r['team_name'],
                $r['startnummer'],
                $r['schwimmer_name'],
                number_format($r['spendenbetrag_vormittag'], 2, ',', ''),
                number_format($r['spendenbetrag_nachmittag'], 2, ',', ''),
                number_format($r['spendenbetrag_gesamt'], 2, ',', ''),
                number_format($r['spendenbetrag_gedeckelt'], 2, ',', ''),
                date('d.m.Y H:i', strtotime($r['erstelldatum']))
            ], ';');
        }
        // Team-Zwischensumme
        fputcsv($out, [
            $g['info']['team_name'] . ' - Summe', '', '',
            '', '',
            number_format($g['sum_gesamt'], 2, ',', ''),
            number_format($g['sum_gedeckelt'], 2, ',', ''),
            ''
        ], ';');
    }
    // Gesamtsumme
    fputcsv($out, [
        'Gesamtsumme (' . $anzahl . ' Einträge)', '', '',
        '', '',
        number_format($gesamt_summe, 2, ',', ''),
        number_format($gesamt_gedeckelt, 2, ',', ''),
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
        <title>Team-Spendenbeträge - VAIBad</title>
        <link rel="stylesheet" href="/VAIBad_2/webapp/css/style.css">
    </head>
    <body>';
}
?>
<div class="container">
    <h1>Team-Spendenbeträge</h1>

    <div class="action-bar">
        <a href="/VAIBad_2/webapp/spendenberechnung_teams.php" class="btn btn-primary">Neu berechnen</a>
        <a href="/VAIBad_2/webapp/spenden_teams.php?export=csv" class="btn btn-primary">Als CSV herunterladen</a>
        <a href="/VAIBad_2/webapp/index.php" class="btn btn-secondary">Startseite</a>
    </div>

    <?php if (!empty($gruppen)): ?>
        <?php foreach ($gruppen as $tid => $g): ?>
            <h2 style="margin-top: 2rem;">
                <?php echo htmlspecialchars($g['info']['team_name']); ?>
                <span style="font-size:.9rem; font-weight:normal; color:#666;">
                    (Limit: <?php echo ($g['info']['team_limit'] !== null) ? htmlspecialchars($g['info']['team_limit']) . ' €' : 'kein Limit'; ?>)
                </span>
            </h2>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Startnr.</th>
                            <th>Schwimmer</th>
                            <th>Vormittag</th>
                            <th>Nachmittag</th>
                            <th>Gesamt</th>
                            <th>gedeckelt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($g['zeilen'] as $row): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['startnummer']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['schwimmer_name']); ?></td>
                                <td><?php echo number_format($row['spendenbetrag_vormittag'], 2, ',', '.'); ?> €</td>
                                <td><?php echo number_format($row['spendenbetrag_nachmittag'], 2, ',', '.'); ?> €</td>
                                <td><?php echo number_format($row['spendenbetrag_gesamt'], 2, ',', '.'); ?> €</td>
                                <td><strong><?php echo number_format($row['spendenbetrag_gedeckelt'], 2, ',', '.'); ?> €</strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- Team-Zwischensumme -->
                        <tr style="font-weight: bold; background-color: #f0f0f0;">
                            <td colspan="4">Team-Summe</td>
                            <td><?php echo number_format($g['sum_gesamt'], 2, ',', '.'); ?> €</td>
                            <td><?php echo number_format($g['sum_gedeckelt'], 2, ',', '.'); ?> €</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>

        <!-- Gesamtsumme über alle Teams -->
        <div style="margin-top: 1.5rem; padding: 1rem; background-color: #e8f4e8; border-radius: 4px;">
            <strong>Gesamtsumme (<?php echo $anzahl; ?> Einträge):</strong>
            <?php echo number_format($gesamt_summe, 2, ',', '.'); ?> €
            (ungekürzt) |
            <strong><?php echo number_format($gesamt_gedeckelt, 2, ',', '.'); ?> €</strong> (gedeckelt)
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Startnr.</th>
                        <th>Schwimmer</th>
                        <th>Vormittag</th>
                        <th>Nachmittag</th>
                        <th>Gesamt</th>
                        <th>gedeckelt</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="6" class="no-data">
                            Noch keine Team-Spendenbeträge berechnet.
                            <a href="/VAIBad_2/webapp/spendenberechnung_teams.php">Jetzt berechnen</a>
                        </td>
                    </tr>
                </tbody>
            </table>
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
