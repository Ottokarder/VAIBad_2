<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Spendenlisten (kompakt): nur Summen pro Spender/Team/Hauptsponsor.
// Details sind auf einer eigenen Seite (spender_details.php) aufrufbar.
// CSV: zwei Varianten - nur Summen (?export=csv) und mit Details (?export=csv-details).

// --- Summen pro Sponsor ---
$sponsoren = [];
$res = $conn->query("
    SELECT sp.id AS spender_id, 'sponsor' AS spender_typ, sp.name AS spender_name, NULL AS spender_limit,
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

// --- Summen pro Team (gedeckelt) ---
$teams = [];
$res = $conn->query("
    SELECT t.id AS spender_id, 'team' AS spender_typ, t.name AS spender_name, t.`limit` AS spender_limit,
           SUM(st.spendenbetrag_vormittag) AS sum_vormittag,
           SUM(st.spendenbetrag_nachmittag) AS sum_nachmittag,
           SUM(st.spendenbetrag_gedeckelt) AS sum_gesamt,
           COUNT(*) AS anzahl
    FROM spenden_teams st
    JOIN Teams t ON st.team_id = t.id
    GROUP BY t.id, t.name
    ORDER BY t.name
");
if ($res) { while ($r = $res->fetch_assoc()) $teams[] = $r; $res->free(); }

// --- Summen pro Hauptsponsor (gedeckelt) ---
$hauptsponsoren = [];
$res = $conn->query("
    SELECT h.id AS spender_id, 'hauptsponsor' AS spender_typ, h.name AS spender_name, h.`limit` AS spender_limit,
           SUM(sh.spendenbetrag_vormittag) AS sum_vormittag,
           SUM(sh.spendenbetrag_nachmittag) AS sum_nachmittag,
           SUM(sh.spendenbetrag_gedeckelt) AS sum_gesamt,
           COUNT(*) AS anzahl
    FROM spenden_hauptsponsoren sh
    JOIN Hauptsponsoren h ON sh.hauptsponsor_id = h.id
    GROUP BY h.id, h.name
    ORDER BY h.name
");
if ($res) { while ($r = $res->fetch_assoc()) $hauptsponsoren[] = $r; $res->free(); }

// Alle Gruppen für CSV zusammenführen
$alle = array_merge($sponsoren, $teams, $hauptsponsoren);
$g_sp = 0; $g_t = 0; $g_h = 0;
foreach ($sponsoren as $r) $g_sp += (float)$r['sum_gesamt'];
foreach ($teams as $r) $g_t += (float)$r['sum_gesamt'];
foreach ($hauptsponsoren as $r) $g_h += (float)$r['sum_gesamt'];

// Hilfsfunktion: Typ-Label
$typ_label = ['sponsor' => 'Sponsor', 'team' => 'Team', 'hauptsponsor' => 'Hauptsponsor'];

// CSV-Export
if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'csv-details'], true)) {
    $export_mode = $_GET['export'];
    $dateiname = ($export_mode === 'csv-details') ? 'spendenlisten_mit_details_' . date('Y-m-d_His') . '.csv' : 'spendenlisten_' . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $dateiname . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8-BOM für Excel/LibreOffice
    $euro = function($v) { return number_format((float)$v, 2, ',', ''); };

    // === Abschnitt: nur Summen ===
    fputcsv($out, ['Spendenlisten – Summen'], ';');
    fputcsv($out, ['Typ', 'Name', 'Anzahl Schwimmer', 'Summe Vormittag', 'Summe Nachmittag', 'Summe Gesamt'], ';');
    foreach ($alle as $r) {
        fputcsv($out, [
            $typ_label[$r['spender_typ']], $r['spender_name'], $r['anzahl'],
            $euro($r['sum_vormittag']), $euro($r['sum_nachmittag']), $euro($r['sum_gesamt'])
        ], ';');
    }
    fputcsv($out, ['Gesamtsumme', '', '', '', '', $euro($g_sp + $g_t + $g_h)], ';');
    fputcsv($out, ['davon Sponsoren', '', '', '', '', $euro($g_sp)], ';');
    fputcsv($out, ['davon Teams', '', '', '', '', $euro($g_t)], ';');
    fputcsv($out, ['davon Hauptsponsoren', '', '', '', '', $euro($g_h)], ';');

    // === Abschnitt: Details (nur bei csv-details) ===
    if ($export_mode === 'csv-details') {
        fputcsv($out, [], ';');
        fputcsv($out, ['Spendenlisten – Details'], ';');

        $detail_abfragen = [
            ['Sponsoren', $sponsoren, 'spenden_sponsoren', 'sponsoren_id', 'ss'],
            ['Teams', $teams, 'spenden_teams', 'team_id', 'st'],
            ['Hauptsponsoren', $hauptsponsoren, 'spenden_hauptsponsoren', 'hauptsponsor_id', 'sh'],
        ];

        foreach ($detail_abfragen as $da) {
            list($titel, $liste, $tabelle, $id_spalte, $alias) = $da;
            fputcsv($out, [], ';');
            fputcsv($out, [$titel . ' – Details'], ';');
            fputcsv($out, ['Name', 'Startnr.', 'Schwimmer', 'Vormittag', 'Nachmittag', 'Betrag'], ';');

            foreach ($liste as $spender) {
                $sid = (int)$spender['spender_id'];
                $stmt = $conn->prepare("
                    SELECT sw.startnummer, CONCAT(sw.vorname, ' ', sw.nachname) AS schwimmer_name,
                           " . $alias . ".spendenbetrag_vormittag,
                           " . $alias . ".spendenbetrag_nachmittag,
                           " . $alias . ".spendenbetrag_gesamt AS betrag
                    FROM " . $tabelle . " " . $alias . "
                    JOIN Schwimmer sw ON " . $alias . ".schwimmer_id = sw.id
                    WHERE " . $alias . "." . $id_spalte . " = ?
                    ORDER BY sw.startnummer, sw.nachname, sw.vorname
                ");
                $stmt->bind_param("i", $sid);
                $stmt->execute();
                $dres = $stmt->get_result();
                while ($dr = $dres->fetch_assoc()) {
                    fputcsv($out, [
                        $spender['spender_name'], $dr['startnummer'], $dr['schwimmer_name'],
                        $euro($dr['spendenbetrag_vormittag']), $euro($dr['spendenbetrag_nachmittag']),
                        $euro($dr['betrag'])
                    ], ';');
                }
                $stmt->close();
                // Zwischensumme
                fputcsv($out, [$spender['spender_name'] . ' - Summe', '', '', '', '', $euro($spender['sum_gesamt'])], ';');
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
        <title>Spendenlisten - VAIBad</title>
        <link rel="stylesheet" href="/VAIBad_2/webapp/css/style.css">
    </head>
    <body>';
}

// Hilfsfunktion: einen Listen-Abschnitt als kompakte Tabelle ausgeben
function zeige_summen_abschnitt($titel, $gruppen) {
    global $typ_label;
    echo '<h2 style="margin-top: 2rem;">' . htmlspecialchars($titel) . '</h2>';
    echo '<div class="table-container"><table class="data-table">
        <thead><tr>
            <th>Name</th><th>Anzahl Schwimmer</th>
            <th>Summe Vormittag</th><th>Summe Nachmittag</th><th>Summe Gesamt</th><th>Details</th>
        </tr></thead><tbody>';
    if (empty($gruppen)) {
        echo '<tr><td colspan="6" class="no-data">Noch keine Daten. Bitte zuerst die Spendenberechnung durchführen.</td></tr>';
    } else {
        foreach ($gruppen as $r) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($r['spender_name']) . '</td>';
            echo '<td>' . htmlspecialchars($r['anzahl']) . '</td>';
            echo '<td>' . number_format($r['sum_vormittag'], 2, ',', '.') . ' €</td>';
            echo '<td>' . number_format($r['sum_nachmittag'], 2, ',', '.') . ' €</td>';
            echo '<td><strong>' . number_format($r['sum_gesamt'], 2, ',', '.') . ' €</strong></td>';
            echo '<td><a href="/VAIBad_2/webapp/spender_details.php?typ=' . $r['spender_typ'] . '&id=' . $r['spender_id'] . '" class="btn btn-primary">Details</a></td>';
            echo '</tr>';
        }
        // Summenzeile
        $sv = $sn = $sg = 0;
        foreach ($gruppen as $r) { $sv += (float)$r['sum_vormittag']; $sn += (float)$r['sum_nachmittag']; $sg += (float)$r['sum_gesamt']; }
        echo '<tr style="font-weight: bold; background-color: #f0f0f0;">';
        echo '<td>Gesamtsumme</td><td></td>';
        echo '<td>' . number_format($sv, 2, ',', '.') . ' €</td>';
        echo '<td>' . number_format($sn, 2, ',', '.') . ' €</td>';
        echo '<td>' . number_format($sg, 2, ',', '.') . ' €</td><td></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}
?>
<div class="container">
    <h1>Spendenlisten</h1>

    <div class="action-bar">
        <a href="/VAIBad_2/webapp/spendenlisten.php?export=csv" class="btn btn-primary">CSV (nur Summen)</a>
        <a href="/VAIBad_2/webapp/spendenlisten.php?export=csv-details" class="btn btn-primary">CSV (mit Details)</a>
        <a href="/VAIBad_2/webapp/index.php" class="btn btn-secondary">Startseite</a>
    </div>

    <?php
    $leer = (empty($sponsoren) && empty($teams) && empty($hauptsponsoren));
    if ($leer):
    ?>
        <div class="error-box" style="margin: 1rem 0;">
            Hinweis: Die Listen sind leer. Bitte zuerst die Spendenberechnungen durchführen:
            <a href="/VAIBad_2/webapp/spendenberechnung.php">Sponsoren</a>,
            <a href="/VAIBad_2/webapp/spendenberechnung_teams.php">Teams</a>,
            <a href="/VAIBad_2/webapp/spendenberechnung_hauptsponsoren.php">Hauptsponsoren</a>.
        </div>
    <?php endif; ?>

    <?php zeige_summen_abschnitt('Sponsoren', $sponsoren); ?>
    <?php zeige_summen_abschnitt('Teams', $teams); ?>
    <?php zeige_summen_abschnitt('Hauptsponsoren', $hauptsponsoren); ?>

    <!-- Gesamtsumme aller Spenden -->
    <div style="margin-top: 1.5rem; padding: 1rem; background-color: #e8f4e8; border-radius: 4px;">
        <strong>Gesamtsumme aller Spenden (Sponsoren):</strong>
        <?php echo number_format($g_sp, 2, ',', '.'); ?> €<br>
        <strong>Gesamtsumme aller Spenden (Teams, gedeckelt):</strong>
        <?php echo number_format($g_t, 2, ',', '.'); ?> €<br>
        <strong>Gesamtsumme aller Spenden (Hauptsponsoren, gedeckelt):</strong>
        <?php echo number_format($g_h, 2, ',', '.'); ?> €<br><br>
        <strong style="font-size: 1.2rem;">Gesamtsumme aller Spenden (Sponsoren + Teams + Hauptsponsoren):</strong>
        <?php echo number_format($g_sp + $g_t + $g_h, 2, ',', '.'); ?> €
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
