<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Spendenlisten: Sponsoren / Teams / Hauptsponsoren mit den einzelnen
// Schwimmern und Beträgen. Alle drei Listen auf einer Seite, gruppiert
// nach Sponsor / Team / Hauptsponsor mit Zwischensummen und Gesamtsumme.
// CSV-Download inklusive.

// --- Sponsoren (Ergebnistabelle spenden_sponsoren) ---
$sponsoren_sql = "
    SELECT sp.id AS gruppe_id, sp.name AS gruppe_name, NULL AS gruppe_limit,
           sw.startnummer, CONCAT(sw.vorname, ' ', sw.nachname) AS schwimmer_name,
           ss.spendenbetrag_vormittag, ss.spendenbetrag_nachmittag, ss.spendenbetrag_gesamt,
           ss.spendenbetrag_gesamt AS betrag_gedeckelt,
           ss.erstelldatum
    FROM spenden_sponsoren ss
    JOIN Sponsoren sp ON ss.sponsoren_id = sp.id
    JOIN Schwimmer sw ON ss.schwimmer_id = sw.id
    ORDER BY sp.name, sw.startnummer, sw.nachname, sw.vorname
";

// --- Teams (Ergebnistabelle spenden_teams) ---
$teams_sql = "
    SELECT t.id AS gruppe_id, t.name AS gruppe_name, t.`limit` AS gruppe_limit,
           sw.startnummer, CONCAT(sw.vorname, ' ', sw.nachname) AS schwimmer_name,
           st.spendenbetrag_vormittag, st.spendenbetrag_nachmittag, st.spendenbetrag_gesamt,
           st.spendenbetrag_gedeckelt AS betrag_gedeckelt,
           st.erstelldatum
    FROM spenden_teams st
    JOIN Teams t ON st.team_id = t.id
    JOIN Schwimmer sw ON st.schwimmer_id = sw.id
    ORDER BY t.name, sw.startnummer, sw.nachname, sw.vorname
";

// --- Hauptsponsoren (Ergebnistabelle spenden_hauptsponsoren) ---
$hauptsponsoren_sql = "
    SELECT h.id AS gruppe_id, h.name AS gruppe_name, h.`limit` AS gruppe_limit,
           sw.startnummer, CONCAT(sw.vorname, ' ', sw.nachname) AS schwimmer_name,
           sh.spendenbetrag_vormittag, sh.spendenbetrag_nachmittag, sh.spendenbetrag_gesamt,
           sh.spendenbetrag_gedeckelt AS betrag_gedeckelt,
           sh.erstelldatum
    FROM spenden_hauptsponsoren sh
    JOIN Hauptsponsoren h ON sh.hauptsponsor_id = h.id
    JOIN Schwimmer sw ON sh.schwimmer_id = sw.id
    ORDER BY h.name, sw.startnummer, sw.nachname, sw.vorname
";

// Hilfsfunktion: Ergebnis gruppieren und Summen bilden.
function gruppiere($conn, $sql) {
    $gruppen = [];
    $gesamt_gesamt = 0.0;
    $gesamt_gedeckelt = 0.0;
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $gid = $row['gruppe_id'];
            if (!isset($gruppen[$gid])) {
                $gruppen[$gid] = [
                    'name'  => $row['gruppe_name'],
                    'limit' => $row['gruppe_limit'],
                    'zeilen' => [],
                    'sum_vormittag'  => 0.0,
                    'sum_nachmittag' => 0.0,
                    'sum_gesamt'     => 0.0,
                    'sum_gedeckelt'  => 0.0,
                ];
            }
            $gruppen[$gid]['zeilen'][] = $row;
            $gruppen[$gid]['sum_vormittag']  += (float)$row['spendenbetrag_vormittag'];
            $gruppen[$gid]['sum_nachmittag'] += (float)$row['spendenbetrag_nachmittag'];
            $gruppen[$gid]['sum_gesamt']     += (float)$row['spendenbetrag_gesamt'];
            $gruppen[$gid]['sum_gedeckelt']  += (float)$row['betrag_gedeckelt'];
            $gesamt_gesamt    += (float)$row['spendenbetrag_gesamt'];
            $gesamt_gedeckelt += (float)$row['betrag_gedeckelt'];
        }
        $res->free();
    }
    return [$gruppen, $gesamt_gesamt, $gesamt_gedeckelt];
}

list($sponsoren, $sp_gesamt, $sp_gedeckelt) = gruppiere($conn, $sponsoren_sql);
list($teams, $t_gesamt, $t_gedeckelt) = gruppiere($conn, $teams_sql);
list($hauptsponsoren, $h_gesamt, $h_gedeckelt) = gruppiere($conn, $hauptsponsoren_sql);

// CSV-Export: wenn ?export=csv, Datei direkt zum Download ausliefern.
// Trenner Semikolon + UTF-8-BOM, damit Excel die Datei mit Umlauten korrekt öffnet.
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $dateiname = 'spendenlisten_' . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $dateiname . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8-BOM für Excel
    $euro = function($v) { return ($v === null) ? '' : number_format((float)$v, 2, ',', ''); };

    $schreibe_abschnitt = function($titel, $gruppen, $gesamt_gesamt, $gesamt_gedeckelt) use ($out, $euro) {
        fputcsv($out, [$titel], ';');
        fputcsv($out, ['Sponsor/Team/Hauptsponsor', 'Startnr.', 'Schwimmer',
            'Vormittag', 'Nachmittag', 'Gesamt', 'gedeckelt'], ';');
        foreach ($gruppen as $g) {
            foreach ($g['zeilen'] as $r) {
                fputcsv($out, [
                    $r['gruppe_name'], $r['startnummer'], $r['schwimmer_name'],
                    $euro($r['spendenbetrag_vormittag']), $euro($r['spendenbetrag_nachmittag']),
                    $euro($r['spendenbetrag_gesamt']), $euro($r['betrag_gedeckelt'])
                ], ';');
            }
            fputcsv($out, [
                $g['name'] . ' - Summe', '', '',
                $euro($g['sum_vormittag']), $euro($g['sum_nachmittag']),
                $euro($g['sum_gesamt']), $euro($g['sum_gedeckelt'])
            ], ';');
        }
        fputcsv($out, ['Gesamtsumme', '', '', '', '', $euro($gesamt_gesamt), $euro($gesamt_gedeckelt)], ';');
        fputcsv($out, [], ';');
    };

    $schreibe_abschnitt('Sponsoren', $sponsoren, $sp_gesamt, $sp_gedeckelt);
    $schreibe_abschnitt('Teams', $teams, $t_gesamt, $t_gedeckelt);
    $schreibe_abschnitt('Hauptsponsoren', $hauptsponsoren, $h_gesamt, $h_gedeckelt);

    // Gesamtsumme über alle drei Listen
    fputcsv($out, ['Gesamtsumme aller Spenden'], ';');
    fputcsv($out, ['Sponsoren (gesamt)', $euro($sp_gesamt)], ';');
    fputcsv($out, ['Teams (gesamt)', $euro($t_gesamt)], ';');
    fputcsv($out, ['Hauptsponsoren (gesamt)', $euro($h_gesamt)], ';');
    fputcsv($out, ['Sponsoren + Teams + Hauptsponsoren', $euro($sp_gesamt + $t_gesamt + $h_gesamt)], ';');

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

// Hilfsfunktion: einen Listen-Abschnitt als HTML-Tabelle ausgeben.
function zeige_abschnitt($titel, $gruppen, $gesamt_gesamt, $gesamt_gedeckelt) {
    echo '<h2 style="margin-top: 2rem;">' . htmlspecialchars($titel) . '</h2>';
    if (empty($gruppen)) {
        echo '<div class="table-container"><table class="data-table"><thead><tr>
                <th>Name</th><th>Startnr.</th><th>Schwimmer</th>
                <th>Vormittag</th><th>Nachmittag</th><th>Gesamt</th><th>gedeckelt</th>
              </tr></thead><tbody>
              <tr><td colspan="7" class="no-data">Noch keine Daten. Bitte zuerst die Spendenberechnung durchführen.</td></tr>
              </tbody></table></div>';
        return;
    }
    echo '<div class="table-container"><table class="data-table">
        <thead><tr>
            <th>Name</th><th>Startnr.</th><th>Schwimmer</th>
            <th>Vormittag</th><th>Nachmittag</th><th>Gesamt</th><th>gedeckelt</th>
        </tr></thead><tbody>';
    foreach ($gruppen as $g) {
        $limit_txt = ($g['limit'] !== null) ? ' (Limit: ' . htmlspecialchars($g['limit']) . ' €)' : '';
        echo '<tr style="font-weight: bold; background-color: #e8e8e8;">';
        echo '<td colspan="7">' . htmlspecialchars($g['name']) . $limit_txt . '</td>';
        echo '</tr>';
        foreach ($g['zeilen'] as $r) {
            echo '<tr>';
            echo '<td></td>';
            echo '<td>' . htmlspecialchars($r['startnummer']) . '</td>';
            echo '<td>' . htmlspecialchars($r['schwimmer_name']) . '</td>';
            echo '<td>' . number_format($r['spendenbetrag_vormittag'], 2, ',', '.') . ' €</td>';
            echo '<td>' . number_format($r['spendenbetrag_nachmittag'], 2, ',', '.') . ' €</td>';
            echo '<td>' . number_format($r['spendenbetrag_gesamt'], 2, ',', '.') . ' €</td>';
            echo '<td>' . number_format($r['betrag_gedeckelt'], 2, ',', '.') . ' €</td>';
            echo '</tr>';
        }
        // Zwischensumme
        echo '<tr style="font-weight: bold; background-color: #f0f0f0;">';
        echo '<td colspan="3">Summe ' . htmlspecialchars($g['name']) . '</td>';
        echo '<td>' . number_format($g['sum_vormittag'], 2, ',', '.') . ' €</td>';
        echo '<td>' . number_format($g['sum_nachmittag'], 2, ',', '.') . ' €</td>';
        echo '<td>' . number_format($g['sum_gesamt'], 2, ',', '.') . ' €</td>';
        echo '<td>' . number_format($g['sum_gedeckelt'], 2, ',', '.') . ' €</td>';
        echo '</tr>';
    }
    // Gesamtsumme
    echo '<tr style="font-weight: bold; background-color: #d8e8d8;">';
    echo '<td colspan="3">Gesamtsumme</td>';
    echo '<td></td><td></td>';
    echo '<td>' . number_format($gesamt_gesamt, 2, ',', '.') . ' €</td>';
    echo '<td>' . number_format($gesamt_gedeckelt, 2, ',', '.') . ' €</td>';
    echo '</tr>';
    echo '</tbody></table></div>';
}
?>
<div class="container">
    <h1>Spendenlisten</h1>

    <div class="action-bar">
        <a href="/VAIBad_2/webapp/spendenlisten.php?export=csv" class="btn btn-primary">Als CSV herunterladen</a>
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

    <?php zeige_abschnitt('Sponsoren', $sponsoren, $sp_gesamt, $sp_gedeckelt); ?>
    <?php zeige_abschnitt('Teams', $teams, $t_gesamt, $t_gedeckelt); ?>
    <?php zeige_abschnitt('Hauptsponsoren', $hauptsponsoren, $h_gesamt, $h_gedeckelt); ?>

    <!-- Gesamtsumme aller Spenden -->
    <div style="margin-top: 1.5rem; padding: 1rem; background-color: #e8f4e8; border-radius: 4px;">
        <strong>Gesamtsumme aller Spenden (Sponsoren, gesamt):</strong>
        <?php echo number_format($sp_gesamt, 2, ',', '.'); ?> €<br>
        <strong>Gesamtsumme aller Spenden (Teams, gesamt):</strong>
        <?php echo number_format($t_gesamt, 2, ',', '.'); ?> €<br>
        <strong>Gesamtsumme aller Spenden (Hauptsponsoren, gesamt):</strong>
        <?php echo number_format($h_gesamt, 2, ',', '.'); ?> €<br><br>
        <strong style="font-size: 1.2rem;">Gesamtsumme aller Spenden (Sponsoren + Teams + Hauptsponsoren):</strong>
        <?php echo number_format($sp_gesamt + $t_gesamt + $h_gesamt, 2, ',', '.'); ?> €
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
