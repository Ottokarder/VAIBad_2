<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Detailseite für einen einzelnen Spender / Team / Hauptsponsor.
// Zeigt die Einzelschwimmer und deren Beträge.

$typ = isset($_GET['typ']) ? $_GET['typ'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!in_array($typ, ['sponsor', 'team', 'hauptsponsor'], true) || $id <= 0) {
    header("Location: /VAIBad_2/webapp/spendenlisten.php");
    exit();
}

// Mapping: Typ -> Tabelle, ID-Spalte, Alias, Namenstabelle, Namen-Spalte
$config = [
    'sponsor'       => ['spenden_sponsoren', 'sponsoren_id', 'ss', 'Sponsoren', 'name', 'sp'],
    'team'          => ['spenden_teams', 'team_id', 'st', 'Teams', 'name', 't'],
    'hauptsponsor'  => ['spenden_hauptsponsoren', 'hauptsponsor_id', 'sh', 'Hauptsponsoren', 'name', 'h'],
];
$c = $config[$typ];
list($tabelle, $id_spalte, $alias, $namen_tabelle, $namen_spalte, $namen_alias) = $c;

// Betrag-Spalte: bei Sponsoren spendenbetrag_gesamt (Limit pro Zeile),
// bei Teams/Hauptsponsoren spendenbetrag_gedeckelt (Summen-Limit).
if ($typ === 'sponsor') {
    $betrag_spalte = $alias . '.spendenbetrag_gesamt';
} else {
    $betrag_spalte = $alias . '.spendenbetrag_gedeckelt';
}

// Spendername, Betrag pro Bahn und Limit holen
$stmt = $conn->prepare("SELECT " . $namen_spalte . ", betrag_pro_bahn, `limit` FROM " . $namen_tabelle . " WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$spender = $res->fetch_assoc();
$stmt->close();

if (!$spender) {
    header("Location: /VAIBad_2/webapp/spendenlisten.php");
    exit();
}

$typ_label = ['sponsor' => 'Sponsor', 'team' => 'Team', 'hauptsponsor' => 'Hauptsponsor'];

// Einzelschwimmer abrufen (inkl. Bahnen Vormittag/Nachmittag)
$stmt = $conn->prepare("
    SELECT sw.startnummer, CONCAT(sw.vorname, ' ', sw.nachname) AS schwimmer_name,
           sw.schwimmleistung_vormittag,
           sw.schwimmleistung_nachmittag,
           " . $alias . ".spendenbetrag_vormittag,
           " . $alias . ".spendenbetrag_nachmittag,
           " . $betrag_spalte . " AS betrag
    FROM " . $tabelle . " " . $alias . "
    JOIN Schwimmer sw ON " . $alias . ".schwimmer_id = sw.id
    WHERE " . $alias . "." . $id_spalte . " = ?
    ORDER BY sw.startnummer, sw.nachname, sw.vorname
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

$zeilen = [];
$sum_v = $sum_n = $sum_b = 0.0;
$sum_bahnen_v = $sum_bahnen_n = 0;
while ($row = $result->fetch_assoc()) {
    $zeilen[] = $row;
    $sum_v += (float)$row['spendenbetrag_vormittag'];
    $sum_n += (float)$row['spendenbetrag_nachmittag'];
    $sum_b += (float)$row['betrag'];
    $sum_bahnen_v += (int)$row['schwimmleistung_vormittag'];
    $sum_bahnen_n += (int)$row['schwimmleistung_nachmittag'];
}
$stmt->close();

// CSV-Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $dateiname = 'details_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $spender[$namen_spalte]) . '_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $dateiname . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $euro = function($v) { return number_format((float)$v, 2, ',', ''); };

    fputcsv($out, ['Details für ' . $typ_label[$typ] . ' ' . $spender[$namen_spalte]], ';');
    fputcsv($out, ['Betrag pro Bahn', $euro($spender['betrag_pro_bahn'])], ';');
    if ($spender['limit'] !== null) {
        fputcsv($out, ['Limit', $euro($spender['limit'])], ';');
    }
    fputcsv($out, [], ';');
    fputcsv($out, ['Startnr.', 'Schwimmer', 'Bahnen VM', 'Bahnen NM', 'Vormittag', 'Nachmittag', 'Betrag'], ';');
    foreach ($zeilen as $r) {
        fputcsv($out, [
            $r['startnummer'], $r['schwimmer_name'],
            $r['schwimmleistung_vormittag'], $r['schwimmleistung_nachmittag'],
            $euro($r['spendenbetrag_vormittag']), $euro($r['spendenbetrag_nachmittag']),
            $euro($r['betrag'])
        ], ';');
    }
    fputcsv($out, ['Summe', '', $sum_bahnen_v, $sum_bahnen_n, $euro($sum_v), $euro($sum_n), $euro($sum_b)], ';');
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
        <title>Details ' . htmlspecialchars($spender[$namen_spalte]) . ' - VAIBad</title>
        <link rel="stylesheet" href="/VAIBad_2/webapp/css/style.css">
    </head>
    <body>';
}
?>
<div class="container">
    <h1>Details: <?php echo htmlspecialchars($spender[$namen_spalte]); ?></h1>
    <p><strong><?php echo $typ_label[$typ]; ?></strong>
        &middot; Betrag pro Bahn: <?php echo number_format($spender['betrag_pro_bahn'], 2, ',', '.'); ?> €
    <?php if ($spender['limit'] !== null): ?>
        &middot; Limit: <?php echo htmlspecialchars($spender['limit']); ?> €
    <?php endif; ?>
    </p>

    <div class="action-bar">
        <a href="/VAIBad_2/webapp/spender_details.php?typ=<?php echo $typ; ?>&id=<?php echo $id; ?>&export=csv" class="btn btn-primary">Als CSV herunterladen</a>
        <a href="/VAIBad_2/webapp/spendenlisten.php" class="btn btn-secondary">Zurück zu den Spendenlisten</a>
        <a href="/VAIBad_2/webapp/index.php" class="btn btn-secondary">Startseite</a>
    </div>

    <div class="table-container" style="margin-top: 2rem;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Startnr.</th>
                    <th>Schwimmer</th>
                    <th>Bahnen VM</th>
                    <th>Bahnen NM</th>
                    <th>Vormittag</th>
                    <th>Nachmittag</th>
                    <th>Betrag</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($zeilen)): ?>
                    <?php foreach ($zeilen as $r): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($r['startnummer']); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['schwimmer_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['schwimmleistung_vormittag']); ?></td>
                            <td><?php echo htmlspecialchars($r['schwimmleistung_nachmittag']); ?></td>
                            <td><?php echo number_format($r['spendenbetrag_vormittag'], 2, ',', '.'); ?> €</td>
                            <td><?php echo number_format($r['spendenbetrag_nachmittag'], 2, ',', '.'); ?> €</td>
                            <td><strong><?php echo number_format($r['betrag'], 2, ',', '.'); ?> €</strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="font-weight: bold; background-color: #f0f0f0;">
                        <td colspan="2">Summe</td>
                        <td><?php echo number_format($sum_bahnen_v, 0, '', '.'); ?></td>
                        <td><?php echo number_format($sum_bahnen_n, 0, '', '.'); ?></td>
                        <td><?php echo number_format($sum_v, 2, ',', '.'); ?> €</td>
                        <td><?php echo number_format($sum_n, 2, ',', '.'); ?> €</td>
                        <td><?php echo number_format($sum_b, 2, ',', '.'); ?> €</td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="7" class="no-data">Keine Daten vorhanden.</td></tr>
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
